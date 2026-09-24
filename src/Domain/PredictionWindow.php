<?php

declare(strict_types=1);

namespace CompetitionDemo\Domain;

use DateTimeImmutable;

final class PredictionWindow
{
    public static function isOpen(DateTimeImmutable $now, DateTimeImmutable $closesAt): bool
    {
        return $now < $closesAt;
    }
}
