<?php

namespace Tests\Feature;

use App\Models\Follow;
use App\Models\User;
use App\Notifications\NewFollower;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Notificações, seguidores e seguindo: a casca abre na hora e as listas chegam depois (com esqueleto). */
class DeferredListsTest extends TestCase
{
    use RefreshDatabase;

    public function test_followers_and_following_open_the_shell_first_and_load_the_lists_afterwards(): void
    {
        $me = User::factory()->create(['username' => 'meuusuario']);
        $fan = User::factory()->create(['username' => 'seufa']);
        Follow::create(['follower_id' => $fan->id, 'followed_id' => $me->id]);
        Follow::create(['follower_id' => $me->id, 'followed_id' => $fan->id]);

        foreach (['profile.followers', 'profile.following'] as $route) {
            $this->actingAs($me)->get(route($route, 'meuusuario'))->assertInertia(fn ($page) => $page
                ->component('Profile/Connections')
                ->where('profile.username', 'meuusuario')->where('counts.followers', 1)->where('counts.following', 1) // casca
                ->missing('people')
                ->loadDeferredProps(fn ($d) => $d->has('people.data', 1)->where('people.data.0.username', 'seufa')));
        }
    }

    public function test_notifications_open_the_shell_first_and_load_the_list_afterwards(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create(['username' => 'seguidor']);
        $me->notify(NewFollower::from($other));

        $this->actingAs($me)->get(route('notifications.index'))->assertInertia(fn ($page) => $page
            ->component('Notifications/Index')
            ->where('unread', 1)
            ->missing('notifications')
            ->loadDeferredProps(fn ($d) => $d->has('notifications.data', 1)->where('notifications.data.0.read', false)));
    }

    public function test_the_deferral_can_be_turned_off(): void
    {
        config(['ui.defer_lists' => false]);
        $me = User::factory()->create();

        $this->actingAs($me)->get(route('notifications.index'))->assertInertia(fn ($page) => $page->has('notifications.data', 0));
    }
}
