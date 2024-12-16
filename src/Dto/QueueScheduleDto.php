<?php

declare(strict_types=1);

namespace App\Dto;

class QueueScheduleDto
{
    private const QUEUE_PREFIX = 'Черга';
    private const CHANGED_MARK = 'оновлено';

    public function __construct(
        public readonly string $queueNumber,
        public readonly string $value,
        public bool $changed = false,
    )
    {
    }

    public function getTitleMarkdown(): string
    {
        if (true === $this->changed) {
            return sprintf('*%s %s* _(%s)_', self::QUEUE_PREFIX, $this->queueNumber, self::CHANGED_MARK);
        } else {
            return sprintf('*%s %s*', self::QUEUE_PREFIX, $this->queueNumber);
        }
    }
}
