<?php

declare(strict_types=1);

namespace App\Message;

use App\Collection\ScheduleQueueCollection;

class GenerateAndSendTelegramMessage extends AbstractMessage
{
    protected const MAX_ATTEMPTS = 1;

    public function __construct(
        public readonly string $date,
        public readonly ScheduleQueueCollection $schedules,
        int $attempt = 1,
    )
    {
        parent::__construct($attempt);
    }

    public function toArray(): array
    {
        return [
            'date'      => $this->date,
            'schedules' => $this->schedules->getValues(),
        ];
    }
}
