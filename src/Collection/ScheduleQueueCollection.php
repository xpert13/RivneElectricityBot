<?php

declare(strict_types=1);

namespace App\Collection;

use App\Dto\QueueScheduleDto;
use Iterator;

class ScheduleQueueCollection implements Iterator
{
    /** @var QueueScheduleDto[] */
    private array $items = [];
    private int $position = 0;

    public function push(QueueScheduleDto $queueScheduleDto): void
    {
        $this->items[] = $queueScheduleDto;
    }

    public function current(): QueueScheduleDto
    {
        return $this->items[$this->position];
    }

    public function key(): int
    {
        return $this->position;
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function rewind(): void
    {
        $this->position = 0;
    }

    public function valid(): bool
    {
        return isset($this->items[$this->position]);
    }

    public function markChangedQueues(ScheduleQueueCollection $queueCollection): void
    {
        foreach ($this as $internalQueueScheduleDto) {
            foreach ($queueCollection as $externalQueueScheduleDto) {
                if ($internalQueueScheduleDto->queueNumber === $externalQueueScheduleDto->queueNumber) {
                    $internalQueueScheduleDto->changed = $internalQueueScheduleDto->value !== $externalQueueScheduleDto->value;

                    break;
                }
            }
        }
    }

    public function hasChangedValues(): bool
    {
        foreach ($this as $queueScheduleDto) {
            if (true === $queueScheduleDto->changed) {
                return true;
            }
        }

        return false;
    }

    public function getValues(): array
    {
        $result = [];

        foreach ($this as $queueScheduleDto) {
            $result[$queueScheduleDto->queueNumber] = $queueScheduleDto->value;
        }

        return $result;
    }

    public static function create(?array $data): self
    {
        $collection = new self();

        if (false === empty($data)) {
            foreach ($data as $queueNumber => $value) {
                $collection->push(new QueueScheduleDto(
                    queueNumber: $queueNumber,
                    value: $value,
                ));
            }
        }

        return $collection;
    }

    public function isNew(): bool
    {
        return empty($this->items);
    }

    public function isEmpty(): bool
    {
        foreach ($this->items as $item) {
            if (!empty($item->value)) {
                return false;
            }
        }

        return true;
    }
}
