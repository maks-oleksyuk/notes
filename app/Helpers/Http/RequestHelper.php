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
        return $inputString
            |> (fn (string $str): array => explode(',', $str))
            |> (fn (array $arr): array => array_map(trim(...), $arr))
            |> array_filter(...)
            |> array_values(...);
    }

    /**
     * @return array<int>
     */
    public static function parseStringIntoIntArray(string $inputString): array
    {
        return $inputString
            |> self::parseStringIntoArray(...)
            |> (fn (array $arr): array => array_filter($arr, is_numeric(...)))
            |> (fn (array $arr): array => array_map(intval(...), $arr))
            |> array_values(...);
    }
}
