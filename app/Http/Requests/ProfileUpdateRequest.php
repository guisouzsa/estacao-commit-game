<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'min:3',
                'max:30',
                'regex:/\A[a-zA-Z0-9_-]+\z/',
                Rule::unique('users', 'username')->where(fn ($query) => $query->whereRaw('LOWER(username) = ?', [strtolower($this->input('username'))]))->ignore($this->user()->id),
            ],
            'display_name' => ['required', 'string', 'min:2', 'max:50'],
            'bio' => ['nullable', 'string', 'max:500'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->where(
                    fn ($query) => $query->whereRaw('LOWER(email) = ?', [strtolower($this->input('email'))])
                )->ignore($this->user()->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Este nome de usuário já está sendo utilizado. Escolha outro.',
        ];
    }
}
