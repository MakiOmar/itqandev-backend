<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /auth/login`. `remember` keeps the web guard's remember-me cookie; the Qwik frontend
 * uses the same flag to make its `auth_session` cookie persistent.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array{email: string, password: string}
     */
    public function credentials(): array
    {
        return $this->only(['email', 'password']);
    }

    public function remember(): bool
    {
        return $this->boolean('remember');
    }
}
