<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Factory as ValidationFactory;

abstract class PaginatedRequest extends FormRequest
{
    const int DEFAULT_PAGE = 1;

    const int DEFAULT_PER_PAGE = 15;

    const int MAX_PER_PAGE = 100;

    public function page(): int
    {
        return $this->integer('page', static::DEFAULT_PAGE);
    }

    public function perPage(): int
    {
        return $this->integer('per_page', static::DEFAULT_PER_PAGE);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }

    public function validator(ValidationFactory $factory): Validator
    {
        return $factory->make(
            $this->validationData(),
            array_merge($this->rules(), $this->paginationRules()),
            $this->messages(),
            $this->attributes(),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function paginationRules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:'.static::MAX_PER_PAGE],
        ];
    }
}
