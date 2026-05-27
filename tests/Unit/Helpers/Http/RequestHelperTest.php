<?php

declare(strict_types=1);

use App\Helpers\Http\RequestHelper;

covers(RequestHelper::class);

describe('Helpers | Http | Request', function (): void {
    it('converts truthy values to boolean true', function (): void {
        expect(RequestHelper::toBoolean('true'))->toBeTrue()
            ->and(RequestHelper::toBoolean(1))->toBeTrue()
            ->and(RequestHelper::toBoolean(true))->toBeTrue();
    });

    it('converts falsy values to boolean false', function (): void {
        expect(RequestHelper::toBoolean('false'))->toBeFalse()
            ->and(RequestHelper::toBoolean(0))->toBeFalse()
            ->and(RequestHelper::toBoolean(false))->toBeFalse();
    });

    it('returns null for invalid boolean values', function (): void {
        expect(RequestHelper::toBoolean('invalid'))->toBeNull();
    });

    it('parses a comma-separated string into an array of strings', function (): void {
        $input = 'apple, banana, orange';
        $expected = ['apple', 'banana', 'orange'];
        expect(RequestHelper::parseStringIntoArray($input))->toBe($expected);
    });

    it('trims items and filters out empty strings', function (): void {
        $input = '  apple  , , banana,   orange,';
        $expected = ['apple', 'banana', 'orange'];
        expect(RequestHelper::parseStringIntoArray($input))->toBe($expected);
    });

    it('parses a comma-separated string into an array of integers', function (): void {
        $input = '1,2,3';
        $expected = [1, 2, 3];
        expect(RequestHelper::parseStringIntoIntArray($input))->toBe($expected);
    });

    it('filters out non-numeric values when parsing into int array', function (): void {
        $input = '1, foo, 3, 4bar, 5';
        $expected = [1, 3, 5];
        expect(RequestHelper::parseStringIntoIntArray($input))->toBe($expected);
    });
});
