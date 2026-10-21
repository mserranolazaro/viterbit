<?php

declare(strict_types=1);

namespace App\Recruitment\Domain\Write\Aggregate\Value\Position;

final readonly class Requirement
{
    public string $skill;

    public function __construct(string $skill, public RequirementLevel $level)
    {
        $skill = trim($skill);
        if ($skill === '') {
            throw new \InvalidArgumentException('A requirement needs a skill.');
        }

        $this->skill = $skill;
    }

    public function isMandatory(): bool
    {
        return $this->level === RequirementLevel::Must;
    }
}
