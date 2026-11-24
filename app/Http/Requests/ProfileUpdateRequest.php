<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\EmailDomainAcceptsMail;
use App\Rules\PlayerNameAllowed;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
                // Só valida quando o nome realmente muda (não mexe em nomes já existentes).
                fn ($attribute, $value, $fail) => strtolower((string) $value) === strtolower((string) $this->user()->username)
                    ? null
                    : (new PlayerNameAllowed)->validate($attribute, $value, $fail),
                function ($attribute, $value, $fail): void {
                    $exists = User::query()
                        ->whereRaw('LOWER(username) = ?', [strtolower($value)])
                        ->where('id', '!=', $this->user()->id)
                        ->exists();

                    if ($exists) {
                        $fail('Este nome de usuário já está sendo utilizado. Escolha outro.');
                    }
                },
            ],
            'bio' => ['nullable', 'string', 'max:500'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                // Só consulta o DNS quando o endereço realmente muda.
                fn ($attribute, $value, $fail) => strtolower((string) $value) === strtolower((string) $this->user()->email)
                    ? null
                    : (new EmailDomainAcceptsMail)->validate($attribute, $value, $fail),
                function ($attribute, $value, $fail): void {
                    $exists = User::query()
                        ->whereRaw('LOWER(email) = ?', [strtolower($value)])
                        ->where('id', '!=', $this->user()->id)
                        ->exists();

                    if ($exists) {
                        $fail('Este e-mail já está cadastrado.');
                    }
                },
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
