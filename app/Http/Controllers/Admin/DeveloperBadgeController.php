<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeveloperBadgeController extends Controller
{
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'developer_badge' => [
                'present',
                'nullable',
                Rule::in(array_keys(config('profiles.developer_badges'))),
            ],
        ]);

        $user->developer_badge = $validated['developer_badge'];
        $user->save();

        return back()->with('status', 'O badge especial foi atualizado.');
    }
}
