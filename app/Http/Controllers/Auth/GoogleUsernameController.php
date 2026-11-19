<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GoogleUsernameController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $pendingUser = $request->session()->get('pending_google_user');

        if (! is_array($pendingUser) || ! isset($pendingUser['google_id'], $pendingUser['email'])) {
            return Redirect::route('login')->with('status', 'Inicie a autenticação com Google novamente.');
        }

        return Inertia::render('Auth/GoogleUsername', [
            'displayName' => $pendingUser['display_name'] ?? '',
            'email' => $pendingUser['email'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $pendingUser = $request->session()->get('pending_google_user');

        if (! is_array($pendingUser) || ! isset($pendingUser['google_id'], $pendingUser['email'])) {
            return Redirect::route('login')->with('status', 'Inicie a autenticação com Google novamente.');
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/\A[a-zA-Z0-9_-]+\z/',
                Rule::unique('users', 'username')->where(
                    fn ($query) => $query->whereRaw('LOWER(username) = ?', [Str::lower($request->string('username')->toString())])
                ),
            ],
        ], [
            'username.unique' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
        ]);

        try {
            $user = DB::transaction(function () use ($validated, $pendingUser): User {
                $user = User::create([
                    'username' => Str::lower($validated['username']),
                    'display_name' => Str::lower($validated['username']),
                    'email' => $pendingUser['email'],
                    'google_id' => $pendingUser['google_id'],
                    'password' => Str::random(64),
                    'password_set' => false,
                ]);

                $user->email_verified_at = now();
                $user->save();

                return $user;
            });
        } catch (QueryException $exception) {
            $message = Str::lower($exception->getMessage());

            if (str_contains($message, 'users_username_lower_unique')) {
                throw ValidationException::withMessages([
                    'username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
                ]);
            }

            if (
                str_contains($message, 'users_email_unique')
                || str_contains($message, 'users_email_lower_unique')
                || str_contains($message, 'users_google_id_unique')
            ) {
                $request->session()->forget('pending_google_user');

                return Redirect::route('login')->with(
                    'status',
                    'Não foi possível concluir o cadastro com esta conta Google. Entre na conta existente ou tente novamente.'
                );
            }

            throw $exception;
        }

        event(new Registered($user));

        $request->session()->forget('pending_google_user');
        Auth::login($user);
        $request->session()->regenerate();

        return Redirect::intended(route('dashboard', absolute: false));
    }
}
