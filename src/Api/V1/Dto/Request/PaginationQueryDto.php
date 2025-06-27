<?php

declare(strict_types=1);

namespace App\Api\V1\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class PaginationQueryDto
{
    public const int DEFAULT_LIMIT = 10;

    #[Assert\Positive(message: 'Page must be greater than 0')]
    public int $page = 1;

    #[Assert\Positive(message: 'Limit must be greater than 0')]
    public int $limit = self::DEFAULT_LIMIT;
}
