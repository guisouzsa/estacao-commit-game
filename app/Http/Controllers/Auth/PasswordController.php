<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        // forceFill: remember_token não é preenchível em massa de propósito.
        $request->user()->forceFill([
            'password' => Hash::make($validated['password']),
            'remember_token' => \Illuminate\Support\Str::random(60), // cookies "lembrar-me" antigos morrem
        ])->save();
        // Sessões abertas em outros aparelhos (e cookies "lembrar-me") deixam de valer.
        \Illuminate\Support\Facades\Auth::logoutOtherDevices($validated['password']);

        return back();
    }
}
