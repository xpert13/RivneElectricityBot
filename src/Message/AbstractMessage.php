<?php

declare(strict_types=1);

namespace App\Message;

abstract class AbstractMessage
{
    protected const MAX_ATTEMPTS = 1;

    public function __construct(
        public int $attempt = 1,
    ) {
    }

    public function getNextAttempt(): static
    {
        ++$this->attempt;

        return $this;
    }

    public function isLastAttempt(): bool
    {
        return $this->attempt >= static::MAX_ATTEMPTS;
    }
}
