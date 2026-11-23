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
    public const TYPES = [NewFollower::class];

    private const PER_PAGE = 15;

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Paginado: a central nunca busca tudo de uma vez.
        $page = $user->notifications()
            ->whereIn('type', self::TYPES)
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn ($notification) => [
                'id' => $notification->id,
                'message' => (string) ($notification->data['message'] ?? ''),
                'username' => $notification->data['username'] ?? null,
                'avatar_seed' => $notification->data['avatar_seed'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toIso8601String(),
            ]);

        return Inertia::render('Notifications/Index', [
            'notifications' => $page,
            'unread' => $user->unreadNotifications()->whereIn('type', self::TYPES)->count(),
        ]);
    }

    /** Últimas notificações para o popover da navbar (JSON leve, buscado só ao abrir). */
    public function recent(Request $request): JsonResponse
    {
        $items = $request->user()->notifications()
            ->whereIn('type', self::TYPES)
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'message' => (string) ($notification->data['message'] ?? ''),
                'username' => $notification->data['username'] ?? null,
                'avatar_seed' => $notification->data['avatar_seed'] ?? null,
                'read' => $notification->read_at !== null,
                'created_at' => $notification->created_at->toIso8601String(),
            ]);

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
