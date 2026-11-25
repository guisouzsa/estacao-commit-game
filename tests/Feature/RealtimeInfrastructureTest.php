<?php

namespace Tests\Feature;

use App\Models\GameRoom;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Garante a configuração de infraestrutura do tempo real e da expiração
 * automática: endereço interno de publicação, scheduler e Supervisor.
 */
class RealtimeInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLISH_VARS = ['REVERB_PUBLISH_HOST', 'REVERB_PUBLISH_PORT', 'REVERB_PUBLISH_SCHEME'];

    private const PUBLIC_VARS = ['REVERB_HOST', 'REVERB_PORT', 'REVERB_SCHEME'];

    private array $originalEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([...self::PUBLISH_VARS, ...self::PUBLIC_VARS] as $name) {
            $this->originalEnv[$name] = getenv($name);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalEnv as $name => $value) {
            $value === false ? putenv($name) : putenv("$name=$value");
        }

        parent::tearDown();
    }

    public function test_publishing_targets_the_internal_reverb_even_when_public_reverb_vars_point_to_the_domain(): void
    {
        // Cenário do Render: REVERB_HOST/PORT/SCHEME públicos e REVERB_PUBLISH_* da imagem.
        putenv('REVERB_HOST=jogo.onrender.com');
        putenv('REVERB_PORT=443');
        putenv('REVERB_SCHEME=https');
        putenv('REVERB_PUBLISH_HOST=127.0.0.1');
        putenv('REVERB_PUBLISH_PORT=8080');
        putenv('REVERB_PUBLISH_SCHEME=http');

        $options = $this->reverbConnectionOptions();

        $this->assertSame('127.0.0.1', $options['host']);
        $this->assertEquals(8080, $options['port']);
        $this->assertSame('http', $options['scheme']);
        $this->assertFalse($options['useTLS']);
    }

    public function test_publishing_falls_back_to_the_previous_variables_when_publish_vars_are_absent(): void
    {
        foreach (self::PUBLISH_VARS as $name) {
            putenv($name);
        }
        putenv('REVERB_HOST=127.0.0.1');
        putenv('REVERB_PORT=8081');
        putenv('REVERB_SCHEME=http');

        $options = $this->reverbConnectionOptions();

        $this->assertSame('127.0.0.1', $options['host']);
        $this->assertEquals(8081, $options['port']);
        $this->assertSame('http', $options['scheme']);
    }

    public function test_docker_image_defaults_publishing_to_the_internal_reverb(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));

        $this->assertMatchesRegularExpression('/REVERB_PUBLISH_HOST=127\.0\.0\.1/', $dockerfile);
        $this->assertMatchesRegularExpression('/REVERB_PUBLISH_PORT=8080/', $dockerfile);
        $this->assertMatchesRegularExpression('/REVERB_PUBLISH_SCHEME=http\b/', $dockerfile);
        $this->assertStringNotContainsString('REVERB_APP_SECRET', $dockerfile);
    }

    public function test_supervisor_internal_reverb_port_matches_the_proxy_and_publishing_defaults(): void
    {
        $supervisor = file_get_contents(base_path('docker/supervisor/supervisord.conf'));
        $proxy = file_get_contents(base_path('docker/apache/reverb-proxy.conf'));

        $this->assertStringContainsString('reverb:start --host=127.0.0.1 --port=8080', $supervisor);
        $this->assertStringContainsString('ws://127.0.0.1:8080/app/', $proxy);
    }

    public function test_the_scheduler_runs_the_room_expiration_every_minute(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'rooms:expire-waiting') || $event->description === 'rooms:expire-waiting');

        $this->assertNotNull($event, 'rooms:expire-waiting precisa estar agendado.');
        $this->assertSame('* * * * *', $event->expression);
    }

    public function test_abandoned_room_is_expired_by_the_scheduled_command_without_any_request(): void
    {
        $host = User::factory()->create();
        $room = GameRoom::create(['host_user_id' => $host->id, 'status' => 'waiting', 'empty_since' => now()]);
        $room->participants()->create(['user_id' => $host->id, 'seat' => 1, 'joined_at' => now()]);

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'rooms:expire-waiting') || $event->description === 'rooms:expire-waiting');

        // O agendador executa a rotina no processo do schedule:run; aqui o comando equivalente
        // roda em processo para ser verificado com o banco do teste.
        $this->travel(4)->minutes();
        $this->assertTrue($event->isDue($this->app));
        $this->artisan('rooms:expire-waiting')->assertSuccessful();
        $this->assertSame('waiting', $room->fresh()->status);

        $this->travel(1)->minutes();
        $this->assertTrue($event->isDue($this->app));
        $this->artisan('rooms:expire-waiting')->assertSuccessful();
        $this->assertNull($room->fresh());
    }

    public function test_supervisor_runs_exactly_one_scheduler_alongside_apache_and_reverb(): void
    {
        $supervisor = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

        foreach (['reverb', 'scheduler', 'apache'] as $program) {
            $this->assertSame(1, substr_count($supervisor, "[program:$program]"), "programa $program deve existir uma vez");
        }
        // Um único laço leve que roda `schedule:run` a cada minuto; nenhum processo PHP residente (`schedule:work`).
        $this->assertSame(1, preg_match_all('/^command=\/bin\/sh \/usr\/local\/bin\/scheduler-loop\.sh\s*$/m', $supervisor));
        $this->assertStringNotContainsString('schedule:work', preg_replace('/^#.*$/m', '', $supervisor));
        $this->assertSame(1, preg_match('/\[program:scheduler\].*?numprocs=1/s', $supervisor));

        // Cada programa é monitorado, reinicia e escreve nos logs do container.
        $this->assertSame(3, substr_count($supervisor, 'autorestart=true') - 1 /* watchdog */);
        $this->assertSame(3, substr_count($supervisor, 'stopasgroup=true'));
        $this->assertSame(4, substr_count($supervisor, 'stderr_logfile=/dev/fd/2'));
    }

    public function test_watchdog_stops_the_container_when_an_essential_process_is_fatal(): void
    {
        $supervisor = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

        $this->assertStringContainsString('[eventlistener:essential-watchdog]', $supervisor);
        $this->assertStringContainsString('events=PROCESS_STATE_FATAL', $supervisor);
        $this->assertStringContainsString('essential-watchdog.py apache reverb', $supervisor);
        $this->assertFileExists(base_path('docker/supervisor/essential-watchdog.py'));
        $this->assertStringContainsString(
            'COPY docker/supervisor/essential-watchdog.py',
            file_get_contents(base_path('Dockerfile')),
        );
    }

    public function test_reverb_connection_limit_stays_conservative_by_default(): void
    {
        $original = getenv('REVERB_APP_MAX_CONNECTIONS');
        putenv('REVERB_APP_MAX_CONNECTIONS');

        try {
            $config = require base_path('config/reverb.php');
        } finally {
            if ($original !== false) {
                putenv("REVERB_APP_MAX_CONNECTIONS=$original");
            }
        }

        $this->assertSame(25, $config['apps']['apps'][0]['max_connections']);
    }

    public function test_programs_running_as_www_data_have_a_readable_home_for_the_database_client(): void
    {
        $supervisor = file_get_contents(base_path('docker/supervisor/supervisord.conf'));

        // Sem HOME próprio, a libpq tenta ler /root/.postgresql/postgresql.crt
        // ("Permission denied") e o Reverb e o scheduler não conectam ao banco.
        foreach (['reverb', 'scheduler'] as $program) {
            $this->assertSame(1, preg_match('/\[program:'.$program.'\].*?(?=
\[|\z)/s', $supervisor, $section), "seção $program");
            $this->assertStringContainsString('user=www-data', $section[0]);
            $this->assertStringContainsString('environment=HOME="/var/www"', $section[0], "$program precisa de HOME legível");
        }
    }

    public function test_postgres_keeps_emulated_prepares_for_the_pooler_and_sends_booleans_as_text(): void
    {
        $database = require base_path('config/database.php');
        $options = $database['connections']['pgsql']['options'];

        // Prepares nativos + pooler do Neon abortam transações (25P02).
        if (extension_loaded('pdo_pgsql')) {
            $this->assertTrue($options[\PDO::ATTR_EMULATE_PREPARES]);
        }

        $connection = new \App\Database\PooledPostgresConnection(fn () => null, 'neondb');
        $this->assertSame(['true', 'false', 1, 'x'], $connection->prepareBindings([true, false, 1, 'x']));
        $this->assertInstanceOf(\App\Database\PooledPostgresConnection::class, \Illuminate\Database\Connection::getResolver('pgsql')(fn () => null, 'neondb', '', []));
    }

    private function reverbConnectionOptions(): array
    {
        return (require base_path('config/broadcasting.php'))['connections']['reverb']['options'];
    }
}
