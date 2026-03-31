<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\User\IndexUserRequest;
use App\Http\Requests\PaginatedRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Validation\Factory as ValidationFactory;
use Symfony\Component\HttpFoundation\Request;

covers(PaginatedRequest::class);

describe('Http | Requests | PaginatedRequest', function (): void {
    it('has correct constants', function (): void {
        expect(PaginatedRequest::DEFAULT_PAGE)->toBe(1)
            ->and(PaginatedRequest::DEFAULT_PER_PAGE)->toBe(15)
            ->and(PaginatedRequest::MAX_PER_PAGE)->toBe(100);
    });

    it('returns page and perPage from request with fallback to defaults', function (): void {
        $withDefaults = IndexUserRequest::create('/test');
        $withValues = IndexUserRequest::create('/test', Request::METHOD_GET, ['page' => 3, 'per_page' => 25]);

        expect($withDefaults->page())->toBe(PaginatedRequest::DEFAULT_PAGE)
            ->and($withDefaults->perPage())->toBe(PaginatedRequest::DEFAULT_PER_PAGE)
            ->and($withValues->page())->toBe(3)
            ->and($withValues->perPage())->toBe(25);
    });

    it('rules returns empty array', function (): void {
        expect(new IndexUserRequest()->rules())->toBeEmpty();
    });

    it('validates page', function (mixed $value, bool $passes): void {
        $request = IndexUserRequest::create('/test', Request::METHOD_GET, ['page' => $value]);

        expect($request->validator(App::make(ValidationFactory::class))->passes())->toBe($passes);
    })->with([
        'valid 1' => [1, true],
        'valid 5' => [5, true],
        'invalid 0' => [0, false],
        'invalid negative' => [-1, false],
        'invalid string' => ['abc', false],
    ]);

    it('validates per_page', function (mixed $value, bool $passes): void {
        $request = IndexUserRequest::create('/test', Request::METHOD_GET, ['per_page' => $value]);

        expect($request->validator(App::make(ValidationFactory::class))->passes())->toBe($passes);
    })->with([
        'valid 1' => [1, true],
        'valid 50' => [50, true],
        'valid max' => [100, true],
        'invalid 0' => [0, false],
        'invalid exceeds max' => [101, false],
        'invalid string' => ['abc', false],
    ]);
});
