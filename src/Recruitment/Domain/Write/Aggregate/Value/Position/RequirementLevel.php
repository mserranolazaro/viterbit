<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\Position;

enum RequirementLevel: string
{
    case Must = 'must';
    case Nice = 'nice';
}
