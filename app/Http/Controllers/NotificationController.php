<?php

namespace App\Http\Controllers;

use App\Notifications\NewFollower;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Central de notificações: só avisos que merecem ser consultados depois.
 * Eventos passageiros (alguém entrou na sala, etc.) NÃO passam por aqui.
 */
class NotificationController extends Controller
{
    /** Tipos exibidos na central. (RoomDeleted é um aviso de Dashboard, não da central.) */
    public const TYPES = [NewFollower::class, \App\Notifications\AchievementUnlocked::class];

    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Paginado: a central nunca busca tudo de uma vez. A lista vem DEPOIS da casca da página
        // (esqueleto no meio tempo); só o contador (barato) vai na primeira resposta.
        $build = fn () => $user->notifications()
            ->whereIn('type', self::TYPES)
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn ($notification) => $this->present($notification));

        return Inertia::render('Notifications/Index', [
            'notifications' => config('ui.defer_lists') ? Inertia::defer($build) : $build(),
            'unread' => $user->unreadNotifications()->whereIn('type', self::TYPES)->count(),
        ]);
    }

    /** Formato único de uma notificação para a central e para o sino (inclui a insígnia quando for conquista). */
    private function present($notification): array
    {
        $data = $notification->data;

        return [
            'id' => $notification->id,
            'message' => (string) ($data['message'] ?? ''),
            'username' => $data['username'] ?? null,
            'avatar_seed' => $data['avatar_seed'] ?? null,
            'achievement' => ($data['kind'] ?? null) === 'achievement'
                ? \Illuminate\Support\Arr::only($data, ['slug', 'name', 'description', 'rarity', 'tier', 'icon'])
                : null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at->toIso8601String(),
        ];
    }

    /** Últimas notificações para o popover da navbar (JSON leve, buscado só ao abrir). */
    public function recent(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()
            ->whereIn('type', self::TYPES)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => $this->present($notification));

        return response()->json(['data' => $items])->header('Cache-Control', 'no-store');
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        // Só as notificações da própria pessoa: o id de outra pessoa é um 404.
        $notification = $request->user()->notifications()
            ->whereIn('type', self::TYPES)
            ->whereKey($id)
            ->first();

        abort_unless($notification, 404);
        $notification->markAsRead();

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()
            ->whereIn('type', self::TYPES)
            ->update(['read_at' => now()]);

        return back()->with('toast', [
            'id' => (string) Str::uuid(),
            'tone' => 'success',
            'message' => 'Todas as notificações foram marcadas como lidas.',
        ]);
    }
}
