<?php

declare(strict_types=1);

namespace App\FunctionalStability\Domain\DTO;

final readonly class FunctionalStabilityInputDTO
{
    public function __construct(
        public float $targetProbability = 0,
        public ?array $nodes = null,
        public ?array $edges = null,
    ) {
    }
}
