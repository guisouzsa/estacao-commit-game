<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileAppearanceUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'sometimes', 'string', 'min:3', 'max:30', 'regex:/\A[a-zA-Z0-9_-]+\z/',
                function ($attribute, $value, $fail): void {
                    $exists = \App\Models\User::query()
                        ->whereRaw('LOWER(username) = ?', [strtolower($value)])
                        ->where('id', '!=', $this->user()->id)
                        ->exists();

                    if ($exists) {
                        $fail('Este nome de usuário já está sendo utilizado. Escolha outro.');
                    }
                },
            ],
            'bio' => ['nullable', 'string', 'max:500'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096', 'dimensions:max_width=4000,max_height=4000'],
            'remove_avatar' => ['sometimes', 'boolean'],
            'remove_cover' => ['sometimes', 'boolean'],
            'avatar_position_x' => ['required', 'integer', 'between:0,100'],
            'avatar_position_y' => ['required', 'integer', 'between:0,100'],
            'avatar_zoom' => ['sometimes', 'integer', 'between:100,200'],
            'cover_position_x' => ['sometimes', 'integer', 'between:0,100'],
            'cover_position_y' => ['sometimes', 'integer', 'between:0,100'],
            'profile_frame' => ['required', Rule::in(array_keys(config('profiles.frames')))],
            'primary_badge' => ['required', Rule::in(array_keys(config('profiles.badges')))],
            'profile_title' => ['nullable', Rule::in(array_keys(config('profiles.titles')))],
            'achievement_badge_id' => [
                'nullable',
                'integer',
                Rule::exists('badge_user', 'badge_id')->where(function ($query): void {
                    $query->where('user_id', $this->user()->id)
                        ->whereExists(function ($query): void {
                            $query->selectRaw('1')
                                ->from('badges')
                                ->whereColumn('badges.id', 'badge_user.badge_id')
                                ->where('badges.active', true);
                        });
                }),
            ],
            'links' => ['sometimes', 'array', 'max:8'],
            'links.*.platform' => ['required', Rule::in(['github','linkedin','instagram','youtube','twitch','portfolio','website','other'])],
            'links.*.label' => ['required', 'string', 'max:60'],
            'links.*.url' => ['required', 'url:https', 'max:500'],
            'links.*.is_public' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
            'avatar.image' => 'Escolha uma imagem válida para o avatar.',
            'avatar.mimes' => 'O avatar deve estar em JPG, PNG ou WebP.',
            'avatar.max' => 'O avatar deve ter no máximo 2 MB.',
            'avatar.dimensions' => 'O avatar não pode exceder 4000 × 4000 pixels.',
            'cover.image' => 'Escolha uma imagem válida para a capa.',
            'cover.mimes' => 'A capa deve estar em JPG, PNG ou WebP.',
            'cover.max' => 'A capa deve ter no máximo 4 MB.',
            'cover.dimensions' => 'A capa não pode exceder 4000 × 4000 pixels.',
            'profile_frame.in' => 'A moldura selecionada não está disponível.',
            'primary_badge.in' => 'O selo selecionado não está disponível.',
            'profile_title.in' => 'O título selecionado não está disponível.',
        ];
    }
}
