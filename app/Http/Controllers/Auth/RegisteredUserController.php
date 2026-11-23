<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\EmailDomainAcceptsMail;
use App\Rules\PlayerNameAllowed;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'status' => session('status'),
            'googleConfigured' => filled(config('services.google.client_id'))
                && filled(config('services.google.client_secret'))
                && filled(config('services.google.redirect')),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/\A[a-zA-Z0-9_-]+\z/',
                new PlayerNameAllowed,
                function ($attribute, $value, $fail): void {
                    if (User::query()
                        ->whereRaw('LOWER(username) = ?', [strtolower($value)])
                        ->exists()) {
                        $fail('Este nome de usuário já está sendo utilizado. Escolha outro.');
                    }
                },
            ],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                new EmailDomainAcceptsMail,
                function ($attribute, $value, $fail): void {
                    if (User::query()
                        ->whereRaw('LOWER(email) = ?', [strtolower($value)])
                        ->exists()) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'username.unique' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
        ]);

        try {
            $user = User::create([
                'username' => strtolower($validated['username']),
                'display_name' => strtolower($validated['username']),
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'password_set' => true,
            ]);
        } catch (QueryException $exception) {
            if (str_contains(strtolower($exception->getMessage()), 'users_username_lower_unique')) {
                throw ValidationException::withMessages([
                    'username' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
                ]);
            }
            $message = strtolower($exception->getMessage());

            if (
                str_contains($message, 'users_email_lower_unique')
                || str_contains($message, 'users_email_unique')
            ) {
                throw ValidationException::withMessages([
                    'email' => 'The email has already been taken.',
                ]);
            }
            throw $exception;
        }

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
