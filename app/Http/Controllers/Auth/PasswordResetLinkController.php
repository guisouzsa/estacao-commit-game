<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $email = Str::lower($request->string('email')->trim());
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user && $user->isGoogleOnly()) {
            return back()->with(
                'status',
                'Esta conta utiliza o login com Google. Acesse utilizando a opção "Continuar com Google".'
            );
        }

        Password::sendResetLink(
            ['email' => $email]
        );

        return back()->with('status', 'Se este e-mail estiver cadastrado, enviaremos um link para redefinir sua senha.');
    }
}
