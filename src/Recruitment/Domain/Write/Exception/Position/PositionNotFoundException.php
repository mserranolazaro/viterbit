<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Exception\Position;

use App\Recruitment\Domain\Write\Aggregate\Value\Position\PositionId;

final class PositionNotFoundException extends \DomainException
{
    public static function withId(PositionId $id): self
    {
        return new self(sprintf('Position "%s" not found.', $id->value()));
    }
}
