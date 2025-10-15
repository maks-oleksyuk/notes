<?php

declare(strict_types=1);

namespace App\Helpers\Http;

final readonly class RequestHelper
{
    public static function toBoolean(mixed $possibleBoolean): ?bool
    {
        return filter_var($possibleBoolean, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    }

    /**
     * @return array<string>
     */
    public static function parseStringIntoArray(string $inputString): array
    {
        return array_values(
            array_filter(
                array_map(trim(...), explode(',', $inputString))
            )
        );
    }

    /**
     * @return array<int>
     */
    public static function parseStringIntoIntArray(string $inputString): array
    {
        return array_values(
            array_map(
                intval(...),
                array_filter(self::parseStringIntoArray($inputString), is_numeric(...))
            )
        );
    }
}
