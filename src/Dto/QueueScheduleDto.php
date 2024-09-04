<?php

declare(strict_types=1);

namespace App\Dto;

class QueueScheduleDto
{
    private const QUEUE_PREFIX = 'Черга';
    private const CHANGED_MARK = 'оновлено';

    public function __construct(
        public readonly int $number,
        public readonly string $value,
        public bool $changed = false,
    )
    {
    }

    public function getTitleMarkdown(): string
    {
        if (true === $this->changed) {
            return sprintf('*%s %d* _(%s)_', self::QUEUE_PREFIX, $this->number, self::CHANGED_MARK);
        } else {
            return sprintf('*%s %d*', self::QUEUE_PREFIX, $this->number);
        }
    }
}
