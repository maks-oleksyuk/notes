<?php

declare(strict_types=1);

use App\Http\Requests\Api\V1\User\IndexUserRequest;
use App\Http\Requests\PaginatedRequest;
use Pest\Expectation;

covers(IndexUserRequest::class);

describe('API | V1 | User | IndexUserRequest', function (): void {
    arch('extends PaginatedRequest')
        ->expect(IndexUserRequest::class)
        ->toExtend(PaginatedRequest::class);

    it('authorizes all requests', fn (): Expectation => expect(new IndexUserRequest()->authorize())->toBeTrue());

    it('has no additional rules', fn (): Expectation => expect(new IndexUserRequest()->rules())->toBeEmpty());
});
