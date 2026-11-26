<?php

namespace Tests\Feature;

use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Services\GameEngine;
use App\Services\GameInactivityService;
use App\Support\ScheduledJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/** Agendador econômico: registro das rotinas, `schedule:run` sem ninguém na página, travas, isolamento de falhas e logs. */
class CronSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private function events(): \Illuminate\Support\Collection
    {
        return collect(app(Schedule::class)->events());
    }

    private function event(string $name)
    {
        return $this->events()->first(fn ($e) => $e->description === $name);
    }

    /** @return array{0: GameRoom, 1: GameMatch, 2: \Illuminate\Support\Collection} */
    private function table(int $humans, int $currentSeat = 1): array
    {
        $users = User::factory()->count(max(1, $humans))->create()->values();
        $room = GameRoom::create(['host_user_id' => $users[0]->id, 'status' => 'waiting']);
        for ($seat = 1; $seat <= 4; $seat++) {
            $human = $seat <= $humans;
            $room->participants()->create([
                'user_id' => $human ? $users[$seat - 1]->id : null,
                'kind' => $human ? 'user' : 'bot',
                'bot_key' => $human ? null : 'bot'.$seat.uniqid(),
                'is_host' => $seat === 1, 'seat' => $seat, 'joined_at' => now()->subMinutes(10 - $seat),
            ]);
        }
        $match = app(GameEngine::class)->create($room);
        $room->update(['status' => 'active', 'started_at' => now()]);
        $match->forceFill(['current_seat' => $currentSeat, 'turn_started_at' => now()])->save();

        return [$room, $match, $room->participants()->orderBy('seat')->get()->keyBy('seat')];
    }

    // ---------------------------------------------------------------- o que está agendado

    public function test_each_routine_is_scheduled_exactly_once_with_the_expected_cadence(): void
    {
        $expected = [
            'rooms:expire-waiting' => '* * * * *',
            'games:sweep' => '* * * * *',
            'rooms:purge-finished' => '* * * * *',
            'guests:prune' => '*/15 * * * *',
            'cron:heartbeat' => '*/15 * * * *',
        ];
        $named = $this->events()->filter(fn ($e) => $e->description !== null && isset($expected[$e->description]));

        $this->assertCount(count($expected), $named, 'nenhuma rotina duplicada ou faltando');
        foreach ($expected as $name => $expression) {
            $this->assertSame($expression, $this->event($name)->expression, $name);
        }
        $this->assertSame($named->count(), $this->events()->count(), 'não há outra tarefa agendada fora desta lista');
    }

    public function test_only_the_game_sweep_needs_the_overlap_lock(): void
    {
        $this->assertTrue($this->event('games:sweep')->withoutOverlapping);
        foreach (['rooms:expire-waiting', 'rooms:purge-finished', 'guests:prune'] as $idempotent) {
            $this->assertFalse($this->event($idempotent)->withoutOverlapping, "$idempotent é idempotente e não precisa de trava");
        }
    }

    public function test_routines_run_inside_the_schedule_run_process_without_one_php_subprocess_each(): void
    {
        foreach ($this->events() as $event) {
            $this->assertNull($event->command, "{$event->description} não deve abrir um subprocesso PHP");
        }
    }

    // ---------------------------------------------------------------- o laço do Supervisor

    public function test_the_loop_script_is_light_bounded_and_does_not_use_schedule_work(): void
    {
        $script = file_get_contents(base_path('docker/supervisor/scheduler-loop.sh'));
        $code = preg_replace('/^\s*#.*$/m', '', $script);

        $this->assertStringNotContainsString("\r", $script, 'o script precisa de fim de linha LF');
        $this->assertStringContainsString('artisan schedule:run', $code);
        $this->assertStringNotContainsString('schedule:work', $code);
        $this->assertStringContainsString('nice -n', $code);               // prioridade baixa de CPU
        $this->assertStringContainsString('timeout -k', $code);            // uma execução travada é encerrada
        $this->assertStringContainsString('memory_limit=', $code);         // teto de memória por execução
        $this->assertStringContainsString('sleep $((60 - now % 60))', $code); // alinhado ao minuto, sem deriva
        $this->assertStringContainsString('SCHEDULER_ENABLED', $code);     // desligável sem novo deploy de código
        $this->assertSame(1, substr_count($code, 'while true'), 'um único laço');
        $this->assertSame(1, substr_count($code, 'artisan schedule:run'), 'uma única chamada por minuto, sequencial');
    }

    public function test_the_image_ships_the_loop_script_with_unix_line_endings(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertStringContainsString('COPY docker/supervisor/scheduler-loop.sh /usr/local/bin/scheduler-loop.sh', $dockerfile);
        $this->assertStringContainsString("sed -i 's/\\r$//' /usr/local/bin/scheduler-loop.sh", $dockerfile);
    }

    // ---------------------------------------------------------------- schedule:run de verdade

    public function test_a_timed_out_turn_is_resolved_by_schedule_run_with_no_player_online(): void
    {
        [, $match, $seats] = $this->table(4);
        $this->travel(26)->seconds();

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(2, (int) $match->fresh()->current_seat);
        $this->assertSame(1, (int) $seats[1]->fresh()->timeout_streak);
    }

    public function test_running_the_schedule_twice_never_counts_the_same_timeout_twice(): void
    {
        [, , $seats] = $this->table(4);
        $this->travel(26)->seconds();

        $this->artisan('schedule:run')->assertSuccessful();
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(1, (int) $seats[1]->fresh()->timeout_streak);
    }

    public function test_a_running_sweep_blocks_a_second_one_and_a_released_lock_lets_it_continue(): void
    {
        [, $match] = $this->table(4);
        $this->travel(26)->seconds();
        $sweep = $this->event('games:sweep');

        $this->assertTrue($sweep->mutex->create($sweep), 'a primeira instância pega a trava');
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(1, (int) $match->fresh()->current_seat, 'com a trava ocupada o sweep não roda');

        $sweep->mutex->forget($sweep);
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, (int) $match->fresh()->current_seat, 'solta a trava, a execução seguinte recupera o atraso');
    }

    public function test_a_failing_routine_does_not_stop_the_others_and_the_next_run_recovers(): void
    {
        Log::spy();
        [, $match] = $this->table(4);
        $this->travel(26)->seconds();
        // sala encerrada há muito tempo: a limpeza (outra rotina) precisa rodar mesmo com o sweep quebrando
        $done = GameRoom::create(['host_user_id' => User::factory()->create()->id, 'status' => 'finished', 'finished_at' => now()->subHour()]);

        $this->app->instance(GameInactivityService::class, new class(app(GameEngine::class)) extends GameInactivityService
        {
            public function sweepStalled(): array
            {
                throw new \RuntimeException('falha simulada token=SEGREDO');
            }
        });
        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertNull(GameRoom::find($done->id), 'a limpeza de salas rodou apesar da falha do sweep');
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg, $ctx) => $msg === 'cron.job_failed' && $ctx['job'] === 'games:sweep'
            && ! str_contains(json_encode($ctx), 'SEGREDO'))->once();

        // a falha não deixou a partida em estado estranho: depois que o serviço volta, a execução seguinte resolve
        $this->app->forgetInstance(GameInactivityService::class);
        $this->app->bind(GameInactivityService::class, fn () => new GameInactivityService(app(GameEngine::class)));
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, (int) $match->fresh()->current_seat);
    }

    public function test_bots_are_never_penalized_by_the_human_timeout_when_the_cron_runs(): void
    {
        [, $match, $seats] = $this->table(1, currentSeat: 2); // vez do bot do assento 2
        $this->travel(600)->seconds();                         // muito além dos 25 s de qualquer humano

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame('bot', $seats[2]->fresh()->kind);
        $this->assertSame(0, (int) $seats[2]->fresh()->timeout_streak, 'bot nunca acumula timeout');
        $this->assertNotNull(GameRoom::find($seats[2]->room_id));
        $this->assertNotSame(2, (int) $match->fresh()->current_seat, 'o servidor jogou o passo do bot parado');
    }

    public function test_active_matches_inside_the_deadline_are_left_alone(): void
    {
        [, $match, $seats] = $this->table(4);
        $this->travel(8)->seconds();
        $version = $match->fresh()->version;

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame($version, $match->fresh()->version);
        $this->assertSame(1, (int) $match->fresh()->current_seat);
        $this->assertSame(0, (int) $seats[1]->fresh()->timeout_streak);
    }

    // ---------------------------------------------------------------- logs

    public function test_an_idle_run_writes_nothing_and_a_productive_run_writes_one_compact_line(): void
    {
        Log::spy();

        ScheduledJob::run('guests:prune', fn () => 0);
        Log::shouldNotHaveReceived('log');

        ScheduledJob::run('guests:prune', fn () => 3);
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg, $ctx) => $msg === 'cron.job' && $ctx['job'] === 'guests:prune'
            && $ctx['processed'] === 3 && isset($ctx['duration_ms'], $ctx['peak_memory_mb']))->once();
    }

    public function test_counters_with_only_checked_matches_are_not_logged(): void
    {
        Log::spy();

        ScheduledJob::run('games:sweep', fn () => ['checked' => 5, 'timeouts' => 0, 'bot_steps' => 0]);
        Log::shouldNotHaveReceived('log');

        ScheduledJob::run('games:sweep', fn () => ['checked' => 5, 'timeouts' => 1, 'bot_steps' => 2]);
        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg, $ctx) => $msg === 'cron.job' && $ctx['timeouts'] === 1 && $ctx['bot_steps'] === 2)->once();
    }

    public function test_the_heartbeat_is_a_single_line_without_sensitive_data(): void
    {
        Log::spy();

        ScheduledJob::heartbeat();

        Log::shouldHaveReceived('log')->withArgs(fn ($level, $msg, $ctx) => $msg === 'cron.heartbeat' && isset($ctx['peak_memory_mb']))->once();
    }

    public function test_the_manual_commands_still_exist_and_share_the_same_services(): void
    {
        foreach (['rooms:expire-waiting', 'games:sweep', 'rooms:purge-finished', 'guests:prune'] as $command) {
            $this->artisan($command)->assertSuccessful();
        }
    }

    // ---------------------------------------------------------------- trava de inatividade (site acordado por monitor externo)

    private function lastActivity(?int $secondsAgo): void
    {
        $path = storage_path('framework/last-activity');
        @unlink($path);
        if ($secondsAgo !== null) {
            touch($path, time() - $secondsAgo);
        }
        clearstatcache(true, $path);
    }

    public function test_without_recent_use_the_database_routines_wait_and_run_once_people_return(): void
    {
        config(['game.scheduler_idle_minutes' => 20]);
        $this->travelTo(now()->setTime(14, 7)); // fora da rodada de hora em hora
        [, $match] = $this->table(4);
        $this->travel(26)->seconds();

        $this->lastActivity(null); // ninguém usou o site
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(1, (int) $match->fresh()->current_seat, 'sem uso real: não consulta o banco');

        $this->lastActivity(60 * 25); // último uso há 25 min
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(1, (int) $match->fresh()->current_seat);

        $this->lastActivity(30); // alguém voltou
        $this->artisan('schedule:run')->assertSuccessful();
        $this->assertSame(2, (int) $match->fresh()->current_seat, 'com uso recente, a rotina recupera o atraso');
        $this->lastActivity(null);
    }

    public function test_the_hourly_maintenance_run_happens_even_without_anyone_online(): void
    {
        config(['game.scheduler_idle_minutes' => 20]);
        $this->travelTo(now()->setTime(15, 0));
        [, $match] = $this->table(4);
        $this->travel(26)->seconds();
        $this->lastActivity(null);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertSame(2, (int) $match->fresh()->current_seat);
    }

    public function test_web_requests_mark_activity_but_the_health_check_does_not(): void
    {
        $this->lastActivity(null);

        $this->get('/up')->assertOk();
        $this->assertFalse(\App\Support\SiteActivity::recent(20), 'o monitor externo não conta como uso');

        $this->get('/')->assertOk();
        $this->assertTrue(\App\Support\SiteActivity::recent(20));
        $this->lastActivity(null);
    }
}
