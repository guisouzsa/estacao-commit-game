<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Concede ou remove o selo de verificado. Só a conta administradora (gate `manage-verified`). */
class VerifiedController extends Controller
{
    public function update(Request $request, string $username): RedirectResponse
    {
        $data = $request->validate(['verified' => ['required', 'boolean']]);

        $user = User::query()->whereRaw('LOWER(username) = ?', [strtolower($username)])->firstOrFail();

        // forceFill: a coluna não é preenchível por formulário, só por esta rota protegida.
        $user->forceFill(['verified_at' => $data['verified'] ? ($user->verified_at ?? now()) : null])->save();

        return back()->with('status', $data['verified']
            ? "@{$user->username} agora é verificado."
            : "Verificação de @{$user->username} removida.");
    }
}
