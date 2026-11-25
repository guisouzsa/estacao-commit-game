<?php

namespace Tests\Feature;

use App\Models\Badge;
use App\Models\GameMatch;
use App\Models\GameRoom;
use App\Models\User;
use App\Notifications\AchievementUnlocked;
use App\Services\Achievements;
use App\Services\GameEngine;
use App\Services\MatchStatistics;
use App\Support\AchievementPresenter;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AchievementsTest extends TestCase
{
    use RefreshDatabase;

    private int $clock = 0; // cada partida termina um minuto depois da anterior (ordem estável para a sequência)

    private function owned(User $user): array
    {
        return DB::table('badge_user')->join('badges', 'badges.id', '=', 'badge_user.badge_id')
            ->where('user_id', $user->id)->pluck('badges.slug')->all();
    }

    /** Termina uma partida (1 humano + 3 bots) e soma o resultado, como o servidor faz no fim real. */
    private function finishMatch(User $user, bool $won): GameMatch
    {
        $room = GameRoom::create(['host_user_id' => $user->id, 'status' => 'waiting']);
        $room->participants()->create(['user_id' => $user->id, 'kind' => 'user', 'is_host' => true, 'seat' => 1, 'joined_at' => now()]);
        foreach ([2, 3, 4] as $seat) {
            $room->participants()->create(['kind' => 'bot', 'bot_key' => 'bot'.$seat.uniqid(), 'is_host' => false, 'seat' => $seat, 'joined_at' => now()]);
        }
        $match = app(GameEngine::class)->create($room);
        $winner = $room->participants()->where('seat', $won ? 1 : 2)->firstOrFail();
        $match->forceFill(['status' => 'finished', 'winner_participant_id' => $winner->id, 'finished_at' => now()->addMinutes(++$this->clock)])->save();
        MatchStatistics::record($room->fresh(), $match->fresh());
        $user->refresh();

        return $match;
    }

    // ---------------------------------------------------------------- catálogo

    public function test_the_catalog_is_synced_once_and_retires_old_unobtainable_badges(): void
    {
        foreach (config('achievements.catalog') as $item) {
            $this->assertSame(1, Badge::where('slug', $item['slug'])->count(), $item['slug']);
        }
        foreach (config('achievements.retired') as $slug) {
            $this->assertFalse((bool) Badge::where('slug', $slug)->value('active'), "$slug deve estar inativa");
        }
    }

    public function test_win_tiers_grow_with_effort_and_every_slug_is_unique(): void
    {
        $order = ['starter' => 0, 'bronze' => 1, 'silver' => 2, 'gold' => 3, 'platinum' => 4, 'legend' => 5];
        $wins = collect(config('achievements.catalog'))->where('metric', 'wins')->sortBy('threshold')->values();

        $previous = -1;
        foreach ($wins as $item) {
            $this->assertGreaterThanOrEqual($previous, $order[$item['tier']], 'a raridade nunca cai quando as vitórias exigidas sobem');
            $previous = $order[$item['tier']];
        }
        $slugs = collect(config('achievements.catalog'))->pluck('slug');
        $this->assertSame($slugs->count(), $slugs->unique()->count());
        // cada ícone e cada tier existem no desenho do front
        $this->assertEmpty(collect(config('achievements.catalog'))->pluck('icon')->diff(['star', 'rocket', 'branch', 'trophy', 'crown', 'flame', 'commit']));
    }

    // ---------------------------------------------------------------- cadastro

    public function test_registration_grants_the_initial_star_once_with_one_notification(): void
    {
        $this->post('/register', ['username' => 'novato', 'email' => 'novato@gmail.com', 'password' => 'Senha-forte-123', 'password_confirmation' => 'Senha-forte-123']);
        $user = User::where('username', 'novato')->firstOrFail();

        $this->assertSame(['first-access'], $this->owned($user));
        $this->assertSame(1, $user->notifications()->where('type', AchievementUnlocked::class)->count());

        // outro aparelho / novo login / evento repetido: nada duplica
        event(new Registered($user));
        $this->post('/logout');
        $this->post('/login', ['email' => 'novato@gmail.com', 'password' => 'Senha-forte-123']);
        $this->assertSame(['first-access'], $this->owned($user->fresh()));
        $this->assertSame(1, $user->notifications()->where('type', AchievementUnlocked::class)->count());
    }

    // ---------------------------------------------------------------- partidas e vitórias

    public function test_first_match_and_first_win_are_granted_by_the_server_after_the_result_is_recorded(): void
    {
        $user = User::factory()->create();

        $this->finishMatch($user, won: false);
        $this->assertContains('first-step', $this->owned($user));
        $this->assertNotContains('first-win', $this->owned($user));

        $this->finishMatch($user, won: true);
        $this->assertContains('first-win', $this->owned($user));
        $this->assertSame(1, $user->notifications()->where('data->slug', 'first-win')->count());
    }

    public function test_win_milestones_follow_the_real_counters_in_order(): void
    {
        $user = User::factory()->create();
        $service = app(Achievements::class);

        foreach ([[1, 'first-win'], [5, '5-wins'], [10, '10-wins'], [25, '25-wins'], [50, '50-wins'], [100, '100-wins']] as [$wins, $slug]) {
            $user->forceFill(['matches_won' => $wins - 1, 'matches_played' => $wins - 1])->save();
            $service->onMatchFinished($user->fresh());
            $this->assertNotContains($slug, $this->owned($user), "$slug não pode vir antes do marco");

            $user->forceFill(['matches_won' => $wins, 'matches_played' => $wins])->save();
            $service->onMatchFinished($user->fresh());
            $this->assertContains($slug, $this->owned($user), "$slug ao chegar a $wins vitórias");
        }
    }

    public function test_calling_the_evaluation_again_never_duplicates_anything(): void
    {
        $user = User::factory()->create(['matches_won' => 100, 'matches_played' => 300]);
        $service = app(Achievements::class);

        $first = $service->evaluate($user, ['matches_played', 'wins']);
        $second = $service->evaluate($user, ['matches_played', 'wins']);

        $this->assertNotEmpty($first);
        $this->assertSame([], $second);
        $this->assertSame(count($this->owned($user)), $user->notifications()->count());
        $this->assertSame(count($first), count(array_unique($this->owned($user))));
    }

    public function test_win_streak_needs_consecutive_wins(): void
    {
        $user = User::factory()->create();
        $this->finishMatch($user, won: true);
        $this->finishMatch($user, won: true);
        $this->finishMatch($user, won: false);
        $this->finishMatch($user, won: true);
        $this->finishMatch($user, won: true);
        $this->assertNotContains('streak-3', $this->owned($user), 'uma derrota no meio quebra a sequência');

        $this->finishMatch($user, won: true);
        $this->assertContains('streak-3', $this->owned($user));
    }

    public function test_an_achievement_failure_never_breaks_the_end_of_a_match(): void
    {
        $this->app->instance(Achievements::class, new class extends Achievements
        {
            public function grant(User $user, string $slug): bool
            {
                throw new \RuntimeException('falha simulada na concessão');
            }
        });
        $user = User::factory()->create();

        $match = $this->finishMatch($user, won: true);

        $this->assertSame('finished', $match->fresh()->room->status);
        $this->assertSame(1, (int) $user->fresh()->matches_won, 'o resultado da partida foi gravado mesmo assim');
        $this->assertSame([], $this->owned($user));
    }

    // ---------------------------------------------------------------- aviso e popup

    public function test_the_popup_is_offered_once_and_the_notification_stays_in_the_list(): void
    {
        $user = User::factory()->create();
        $this->finishMatch($user, won: true);
        $popups = fn () => collect($this->actingAs($user->fresh())->get(route('dashboard'))->original->getData()['page']['props']['auth']['achievement_popups']);

        $first = $popups();
        $this->assertContains('first-win', $first->pluck('slug')->all());

        // o front marca como lida ao exibir; depois disso não volta, nem ao recarregar
        $this->app['auth']->forgetGuards();
        foreach ($first as $popup) {
            $this->actingAs($user)->post(route('notifications.read', $popup['id']));
            $this->app['auth']->forgetGuards();
        }
        $this->assertSame(0, $popups()->count());
        $this->assertSame(0, $popups()->count());
        $this->assertGreaterThanOrEqual(2, $user->notifications()->where('type', AchievementUnlocked::class)->count(), 'continua na central');
    }

    public function test_someone_offline_finds_the_notification_on_the_next_visit(): void
    {
        $user = User::factory()->create();
        $this->finishMatch($user, won: true); // aconteceu enquanto estava offline

        $this->actingAs($user->fresh())->get(route('notifications.index'))->assertOk();
        $this->assertGreaterThan(0, $user->unreadNotifications()->where('type', AchievementUnlocked::class)->count());
    }

    public function test_the_bell_payload_carries_the_badge_data(): void
    {
        $user = User::factory()->create();
        $this->finishMatch($user, won: true);

        $json = $this->actingAs($user->fresh())->getJson(route('notifications.recent'))->assertOk()->json('data');

        $achievement = collect($json)->firstWhere('achievement.slug', 'first-win');
        $this->assertNotNull($achievement);
        $this->assertSame('Primeira Vitória', $achievement['achievement']['name']);
        $this->assertArrayHasKey('tier', $achievement['achievement']);
    }

    // ---------------------------------------------------------------- destaques no perfil

    public function test_featuring_is_limited_and_only_for_unlocked_badges_of_the_same_person(): void
    {
        $user = User::factory()->create(['matches_won' => 10, 'matches_played' => 30]);
        app(Achievements::class)->evaluate($user, ['matches_played', 'wins']);
        $mine = DB::table('badge_user')->where('user_id', $user->id)->pluck('badge_id')->values();
        $locked = Badge::where('slug', '100-wins')->value('id');
        $other = User::factory()->create(['matches_won' => 10, 'matches_played' => 30]);
        app(Achievements::class)->evaluate($other, ['matches_played', 'wins']);
        $othersOnly = DB::table('badge_user')->where('user_id', $other->id)->whereNotIn('badge_id', $mine)->value('badge_id');

        $put = fn (array $ids) => $this->actingAs($user)->put(route('profile.achievements.featured'), ['mode' => 'custom', 'featured' => $ids]);

        $put($mine->take(5)->all())->assertSessionHasNoErrors();
        $this->assertSame(5, DB::table('badge_user')->where('user_id', $user->id)->whereNotNull('featured_order')->count());

        $this->app['auth']->forgetGuards();
        $put($mine->take(6)->all())->assertSessionHasErrors('featured'); // acima do limite
        $this->app['auth']->forgetGuards();
        $put([$locked])->assertSessionHasErrors('featured'); // bloqueada
        if ($othersOnly) {
            $this->app['auth']->forgetGuards();
            $put([$othersOnly])->assertSessionHasErrors('featured'); // desbloqueio de outra pessoa
        }
        $this->app['auth']->forgetGuards();
        $put([])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('badge_user')->where('user_id', $user->id)->whereNotNull('featured_order')->count());
    }

    public function test_a_guest_or_anonymous_visitor_cannot_change_featured_badges(): void
    {
        $this->put(route('profile.achievements.featured'), ['mode' => 'none', 'featured' => []])->assertRedirect(route('login'));
    }

    public function test_the_public_profile_exposes_criteria_dates_and_featured_order(): void
    {
        $user = User::factory()->create(['matches_won' => 5, 'matches_played' => 12]);
        app(Achievements::class)->evaluate($user, ['matches_played', 'wins']);
        $badge = Badge::where('slug', '5-wins')->firstOrFail();
        $this->actingAs($user)->put(route('profile.achievements.featured'), ['mode' => 'custom', 'featured' => [$badge->id]]);
        $this->app['auth']->forgetGuards();

        $props = [];
        $this->actingAs(User::factory()->create())->get(route('profile.show', $user->username))
            ->assertInertia(function ($page) use (&$props) {
                $props = $page->toArray()['props']['profile']['badges'];
            });

        $row = collect($props)->firstWhere('slug', '5-wins');
        $this->assertTrue($row['unlocked']);
        $this->assertSame(1, $row['featured_order']);
        $this->assertTrue($row['displayed']);
        $this->assertSame('Vencer 5 partidas.', $row['criterion']);
        $this->assertNotNull($row['unlocked_at']);
        $this->assertCount(1, $props);
    }

    public function test_unlocked_achievements_are_hidden_by_default_even_from_the_owner(): void
    {
        $user = User::factory()->create(['matches_won' => 5, 'matches_played' => 12]);
        app(Achievements::class)->evaluate($user, ['matches_played', 'wins']);

        $badges = collect(AchievementPresenter::forUser($user->fresh()));

        $this->assertGreaterThan(0, $badges->where('unlocked', true)->count());
        $this->assertSame(0, $badges->where('displayed', true)->count());
    }

    public function test_all_mode_displays_every_current_and_future_unlocked_achievement(): void
    {
        $user = User::factory()->create(['matches_played' => 0, 'matches_won' => 0]);
        app(Achievements::class)->evaluate($user, ['registered']);

        $this->actingAs($user)->put(route('profile.achievements.featured'), [
            'mode' => 'all',
            'featured' => [],
        ])->assertSessionHasNoErrors();

        $before = collect(AchievementPresenter::forUser($user->fresh()));
        $this->assertSame($before->where('unlocked', true)->count(), $before->where('displayed', true)->count());

        $user->forceFill(['matches_played' => 1])->save();
        app(Achievements::class)->evaluate($user->fresh(), ['matches_played']);
        $after = collect(AchievementPresenter::forUser($user->fresh()));

        $this->assertGreaterThan($before->where('displayed', true)->count(), $after->where('displayed', true)->count());
        $this->assertSame($after->where('unlocked', true)->count(), $after->where('displayed', true)->count());
    }

    public function test_none_mode_hides_previous_custom_selections_without_removing_unlocks(): void
    {
        $user = User::factory()->create(['matches_won' => 5, 'matches_played' => 12]);
        app(Achievements::class)->evaluate($user, ['matches_played', 'wins']);
        $id = DB::table('badge_user')->where('user_id', $user->id)->value('badge_id');

        $this->actingAs($user)->put(route('profile.achievements.featured'), ['mode' => 'custom', 'featured' => [$id]]);
        $this->actingAs($user)->put(route('profile.achievements.featured'), ['mode' => 'none', 'featured' => []]);

        $badges = collect(AchievementPresenter::forUser($user->fresh()));
        $this->assertSame(0, $badges->where('displayed', true)->count());
        $this->assertGreaterThan(0, $badges->where('unlocked', true)->count());
    }

    public function test_public_highlights_follow_the_saved_mode_for_every_viewer(): void
    {
        $owner = User::factory()->create();
        $visitor = User::factory()->create();
        $url = route('profile.public', $owner->username);
        $endpoint = route('profile.achievements.featured');
        $ids = Badge::query()->whereRaw('active IS TRUE AND secret IS FALSE')->pluck('id');
        $count = fn (int $expected) => $this->get($url)
            ->assertInertia(fn ($page) => $page->has('profile.badges', $expected));

        $count(0);
        $owner->badges()->attach($ids->all(), ['unlocked_at' => now(), 'granted_by' => 'test']);
        $this->actingAs($owner);
        $count(0);
        $this->put($endpoint, ['mode' => 'custom', 'featured' => $ids->take(2)->all()])->assertSessionHasNoErrors();
        $count(2);
        $this->put($endpoint, ['mode' => 'custom', 'featured' => [$ids[1]]])->assertSessionHasNoErrors();
        $this->post(route('logout'))->assertRedirect();
        $this->post(route('login'), ['email' => $owner->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $count(1);

        $this->actingAs($visitor)->put($endpoint, [
            'user_id' => $owner->id,
            'mode' => 'custom',
            'featured' => [$ids[1]],
        ])->assertSessionHasErrors('featured');
        $count(1);

        $this->actingAs($owner)->put($endpoint, ['mode' => 'all', 'featured' => []])->assertSessionHasNoErrors();
        $count($ids->count());
        $this->put($endpoint, ['mode' => 'none', 'featured' => []])->assertSessionHasNoErrors();
        $count(0);
    }

    public function test_custom_mode_never_adds_new_unlocks_and_rejects_an_unknown_mode(): void
    {
        $user = User::factory()->create(['matches_played' => 0, 'matches_won' => 0]);
        app(Achievements::class)->evaluate($user, ['registered']);
        $initial = Badge::query()->where('slug', 'first-access')->value('id');
        $endpoint = route('profile.achievements.featured');
        $this->actingAs($user)->put($endpoint, ['mode' => 'automatic', 'featured' => [$initial]])
            ->assertSessionHasErrors('mode');
        $this->put($endpoint, ['mode' => 'custom', 'featured' => [$initial]])->assertSessionHasNoErrors();

        $user->forceFill(['matches_played' => 1])->save();
        app(Achievements::class)->evaluate($user->fresh(), ['matches_played']);

        $this->get(route('profile.public', $user->username))->assertInertia(fn ($page) => $page
            ->has('profile.badges', 1)
            ->where('profile.badges.0.slug', 'first-access'));
    }
}
