<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($this->route('user')),
            ],
            'password' => ['sometimes', Password::defaults()],
        ];
    }

    /**
     * @return array<string, array{description: string, example: mixed}>
     */
    public function bodyParameters(): array
    {
        return [
            'name' => [
                'description' => 'Full name.',
                'example' => 'Jane Doe',
            ],
            'email' => [
                'description' => 'Unique email address.',
                'example' => 'jane@example.com',
            ],
            'password' => [
                'description' => 'New password that meets the default password rules.',
                'example' => 'Secret-Password!123',
            ],
        ];
    }
}
