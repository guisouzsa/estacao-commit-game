<?php

namespace Tests\Feature;

use App\Events\UserNoticesChanged;
use App\Models\Follow;
use App\Models\GuestProfile;
use App\Models\User;
use App\Notifications\NewFollower;
use App\Notifications\RoomDeleted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

/** Seguir pessoas e a central de notificações (só contas permanentes). */
class SocialTest extends TestCase
{
    use RefreshDatabase;

    private const INERTIA = ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'];

    private function follow(User $who, User $target)
    {
        return $this->actingAs($who)->post(route('follow.store', $target->username));
    }

    // ---- seguir e deixar de seguir ---------------------------------------------

    public function test_a_user_can_follow_another_and_the_target_is_notified_once(): void
    {
        Event::fake([UserNoticesChanged::class]);
        $ana = User::factory()->create(['username' => 'ana']);
        $bia = User::factory()->create(['username' => 'bia']);

        $this->follow($ana, $bia)->assertRedirect();

        $this->assertDatabaseHas('follows', ['follower_id' => $ana->id, 'followed_id' => $bia->id]);
        $notification = $bia->unreadNotifications()->firstOrFail();
        $this->assertSame(NewFollower::class, $notification->type);
        $this->assertSame('@ana começou a seguir você.', $notification->data['message']);
        Event::assertDispatched(UserNoticesChanged::class, fn ($event) => $event->userId === $bia->id);
        $this->assertSame('success', session('toast.tone'));
    }

    public function test_nobody_can_follow_themselves(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);

        $this->follow($ana, $ana)->assertRedirect();

        $this->assertDatabaseCount('follows', 0);
        $this->assertCount(0, $ana->notifications);
        $this->assertSame('Você não pode seguir a si mesmo.', session('toast.message'));
    }

    public function test_following_twice_creates_one_relationship_and_one_notification(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);
        $bia = User::factory()->create(['username' => 'bia']);

        $this->follow($ana, $bia);
        $this->follow($ana, $bia)->assertRedirect();

        $this->assertSame(1, Follow::count());
        $this->assertCount(1, $bia->notifications);
        $this->assertSame('Você já segue @bia.', session('toast.message'));
    }

    public function test_the_database_itself_rejects_a_duplicate_relationship(): void
    {
        $ana = User::factory()->create();
        $bia = User::factory()->create();
        Follow::create(['follower_id' => $ana->id, 'followed_id' => $bia->id]);

        $this->expectException(QueryException::class);
        Follow::create(['follower_id' => $ana->id, 'followed_id' => $bia->id]);
    }

    public function test_unfollowing_removes_the_relationship_and_is_idempotent(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);
        $bia = User::factory()->create(['username' => 'bia']);
        $this->follow($ana, $bia);

        $this->actingAs($ana)->delete(route('follow.destroy', 'bia'))->assertRedirect();
        $this->assertDatabaseCount('follows', 0);

        $this->actingAs($ana)->delete(route('follow.destroy', 'bia'))->assertRedirect();
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_following_unfollowing_and_following_again_does_not_notify_twice(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);
        $bia = User::factory()->create(['username' => 'bia']);

        $this->follow($ana, $bia);
        $this->actingAs($ana)->delete(route('follow.destroy', 'bia'));
        $this->follow($ana, $bia);

        $this->assertSame(1, Follow::count());
        $this->assertCount(1, $bia->notifications, 'nenhuma notificação repetida');
    }

    public function test_usernames_are_matched_case_insensitively_and_unknown_ones_are_a_404(): void
    {
        $ana = User::factory()->create();
        $bia = User::factory()->create(['username' => 'bia']);

        $this->actingAs($ana)->post(route('follow.store', 'BIA'))->assertRedirect();
        $this->assertDatabaseHas('follows', ['followed_id' => $bia->id]);

        $this->actingAs($ana)->withHeaders(self::INERTIA)->post(route('follow.store', 'ninguem_assim'))->assertRedirect();
        $this->assertSame('error', session('toast.tone'));
        $this->assertDatabaseCount('follows', 1);
    }

    // ---- quem pode participar ------------------------------------------------------

    public function test_anonymous_people_must_log_in_to_follow(): void
    {
        $bia = User::factory()->create(['username' => 'bia']);

        $this->post(route('follow.store', 'bia'))->assertRedirect(route('login'));
        $this->assertDatabaseCount('follows', 0);
    }

    public function test_visitors_cannot_follow_and_have_no_notification_center(): void
    {
        $bia = User::factory()->create(['username' => 'bia']);
        $response = $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);
        $token = $response->getCookie(config('players.guest_cookie'))->getValue();

        $this->withCookie(config('players.guest_cookie'), $token)->post(route('follow.store', 'bia'))->assertRedirect(route('login'));
        $this->withCookie(config('players.guest_cookie'), $token)->get(route('notifications.index'))->assertRedirect(route('login'));

        $this->assertDatabaseCount('follows', 0);
    }

    public function test_visitors_and_bots_cannot_be_followed(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);
        $this->defaultCookies = [];
        $this->app['auth']->forgetGuards();

        $this->actingAs($ana)->withHeaders(self::INERTIA)->post(route('follow.store', 'Viajante'))->assertRedirect();
        $this->actingAs($ana)->withHeaders(self::INERTIA)->post(route('follow.store', 'CommitZilla'))->assertRedirect();

        $this->assertDatabaseCount('follows', 0);
        $this->assertSame('error', session('toast.tone'));
    }

    // ---- listas ----------------------------------------------------------------------

    private function followersOf(User $target, int $count, string $prefix = 'seguidor'): array
    {
        $people = [];
        for ($i = 0; $i < $count; $i++) {
            $person = User::factory()->create(['username' => sprintf('%s%02d', $prefix, $i)]);
            Follow::create(['follower_id' => $person->id, 'followed_id' => $target->id]);
            $people[] = $person;
        }

        return $people;
    }

    public function test_the_followers_list_is_paginated_and_shows_counts(): void
    {
        $star = User::factory()->create(['username' => 'estrela']);
        $this->followersOf($star, 25);
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('profile.followers', 'estrela'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Profile/Connections')
            ->where('kind', 'followers')
            ->has('people.data', 20)
            ->where('people.last_page', 2)
            ->where('people.total', 25)
            ->where('counts.followers', 25)
            ->where('counts.following', 0));

        $this->actingAs($viewer)->get(route('profile.followers', 'estrela').'?page=2')->assertInertia(fn ($page) => $page
            ->has('people.data', 5));
    }

    public function test_the_following_list_shows_who_a_user_follows(): void
    {
        $ana = User::factory()->create(['username' => 'ana']);
        $bia = User::factory()->create(['username' => 'bia']);
        $caio = User::factory()->create(['username' => 'caio']);
        Follow::create(['follower_id' => $ana->id, 'followed_id' => $bia->id]);
        Follow::create(['follower_id' => $ana->id, 'followed_id' => $caio->id]);

        $this->actingAs($caio)->get(route('profile.following', 'ana'))->assertInertia(fn ($page) => $page
            ->where('kind', 'following')
            ->has('people.data', 2)
            ->where('counts.following', 2));
    }

    public function test_lists_flag_who_the_viewer_already_follows_without_leaking_private_data(): void
    {
        $star = User::factory()->create(['username' => 'estrela']);
        [$first, $second] = $this->followersOf($star, 2);
        $viewer = User::factory()->create(['username' => 'eu']);
        Follow::create(['follower_id' => $viewer->id, 'followed_id' => $first->id]);

        $this->actingAs($viewer)->get(route('profile.followers', 'estrela'))->assertInertia(fn ($page) => $page
            ->where('people.data.0.username', 'seguidor01')   // mais recente primeiro
            ->where('people.data.0.is_following', false)
            ->where('people.data.1.username', 'seguidor00')
            ->where('people.data.1.is_following', true)
            ->missing('people.data.0.email')
            ->missing('people.data.0.key'));
    }

    public function test_the_list_does_not_run_more_queries_when_it_has_more_people(): void
    {
        $small = User::factory()->create(['username' => 'pequena']);
        $this->followersOf($small, 3, 'peq');
        $big = User::factory()->create(['username' => 'grande']);
        $this->followersOf($big, 18, 'gra');
        $viewer = User::factory()->create();
        $this->actingAs($viewer);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get(route('profile.followers', 'pequena'))->assertOk();
        $few = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->get(route('profile.followers', 'grande'))->assertOk();
        $many = count(DB::getQueryLog());

        $this->assertSame($few, $many, 'consultas não podem crescer com o tamanho da lista (N+1)');
    }

    public function test_the_public_profile_shows_counts_and_who_can_follow(): void
    {
        $star = User::factory()->create(['username' => 'estrela']);
        $this->followersOf($star, 2);
        $viewer = User::factory()->create();

        $this->get('/perfil/estrela')->assertInertia(fn ($page) => $page
            ->where('social.followers_count', 2)
            ->where('social.logged_in', false)
            ->where('social.can_follow', false));

        $this->actingAs($viewer)->get('/perfil/estrela')->assertInertia(fn ($page) => $page
            ->where('social.logged_in', true)
            ->where('social.can_follow', true)
            ->where('social.is_following', false));

        $this->actingAs($star)->get('/perfil/estrela')->assertInertia(fn ($page) => $page
            ->where('social.is_me', true)
            ->where('social.can_follow', false));
    }

    // ---- central de notificações -------------------------------------------------------

    private function giveFollowerNotices(User $user, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $follower = User::factory()->create(['username' => sprintf('fa%02d', $i)]);
            $this->travel(1)->minutes();
            $user->notify(NewFollower::from($follower));
        }
    }

    public function test_the_center_lists_notifications_paginated_newest_first(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 20);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->has('notifications.data', 15)
            ->where('notifications.total', 20)
            ->where('notifications.data.0.message', '@fa19 começou a seguir você.')
            ->where('unread', 20));

        $this->actingAs($user)->get(route('notifications.index').'?page=2')->assertInertia(fn ($page) => $page
            ->has('notifications.data', 5));
    }

    public function test_the_center_only_shows_notifications_worth_keeping(): void
    {
        $user = User::factory()->create();
        $user->notify(new RoomDeleted((string) Str::uuid(), RoomDeleted::EMPTY_TIMEOUT));
        $this->giveFollowerNotices($user, 1);

        $this->actingAs($user)->get(route('notifications.index'))->assertInertia(fn ($page) => $page
            ->has('notifications.data', 1)
            ->where('unread', 1));
    }

    public function test_a_single_notification_can_be_marked_as_read(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 2);
        $first = $user->notifications()->latest()->firstOrFail();

        $this->actingAs($user)->post(route('notifications.read', $first->id))->assertRedirect();

        $this->assertNotNull($first->fresh()->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_all_notifications_can_be_marked_as_read_at_once(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 5);

        $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame('success', session('toast.tone'));
    }

    public function test_marking_all_as_read_does_not_touch_other_kinds_of_notice(): void
    {
        $user = User::factory()->create();
        $user->notify(new RoomDeleted((string) Str::uuid(), RoomDeleted::EMPTY_TIMEOUT));
        $this->giveFollowerNotices($user, 1);

        $this->actingAs($user)->post(route('notifications.read-all'));

        $this->assertSame(1, $user->unreadNotifications()->where('type', RoomDeleted::class)->count());
    }

    public function test_nobody_can_read_someone_elses_notifications(): void
    {
        $owner = User::factory()->create();
        $this->giveFollowerNotices($owner, 1);
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs(User::factory()->create())->post(route('notifications.read', $notification->id))->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_the_unread_counter_reaches_the_navbar_only_for_accounts(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 3);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.unread_notifications', 3));
    }

    public function test_the_dashboard_notices_do_not_consume_social_notifications(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 1);

        $this->actingAs($user)->get(route('dashboard'))->assertInertia(fn ($page) => $page->has('notices', 0));

        $this->assertSame(1, $user->unreadNotifications()->where('type', NewFollower::class)->count());
    }

    public function test_no_social_records_exist_for_visitors(): void
    {
        $this->post(route('guest.store'), ['name' => 'Viajante', 'avatar_style' => 'pixel', 'avatar_seed' => 'abc123xyz']);

        $this->assertDatabaseCount('follows', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertSame(1, GuestProfile::count());
    }

    public function test_the_navbar_popover_gets_only_the_latest_eight_as_light_json(): void
    {
        $user = User::factory()->create();
        $this->giveFollowerNotices($user, 12);
        $user->notify(new RoomDeleted((string) Str::uuid(), RoomDeleted::EMPTY_TIMEOUT));

        $response = $this->actingAs($user)->getJson(route('notifications.recent'))->assertOk();

        $response->assertJsonCount(8, 'data');
        $response->assertJsonPath('data.0.message', '@fa11 começou a seguir você.');
        $response->assertJsonStructure(['data' => [['id', 'message', 'username', 'avatar_seed', 'read', 'created_at']]]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_popover_never_shows_someone_elses_notifications_and_needs_an_account(): void
    {
        $other = User::factory()->create();
        $this->giveFollowerNotices($other, 3);

        $this->actingAs(User::factory()->create())->getJson(route('notifications.recent'))->assertJsonCount(0, 'data');
    }

    public function test_the_popover_endpoint_requires_login(): void
    {
        $this->getJson(route('notifications.recent'))->assertUnauthorized();
    }
}
