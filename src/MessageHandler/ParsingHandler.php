<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Collection\ScheduleQueueCollection;
use App\Message\GenerateAndSendTelegramMessage;
use App\Message\ParsingMessage;
use App\Service\RivneElectricityParsingService;
use Predis\Client;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\ChatterInterface;

#[AsMessageHandler]
readonly class ParsingHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RivneElectricityParsingService $parsingService,
        private Client $redis,
        private ChatterInterface $chatter,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function __invoke(ParsingMessage $message): void
    {
        try {
            $schedules = $this->parsingService->parse();

            foreach ($schedules as $date => $schedule) {
                $this->processSchedule($date, ScheduleQueueCollection::create($schedule));
            }
        } catch (\Throwable $exception) {
            dump($exception->getMessage());

            if ($message->isLastAttempt()) {
                throw $exception;
            }

            $this->messageBus->dispatch($message->getNextAttempt());
        }
    }

    private function processSchedule(string $date, ScheduleQueueCollection $newSchedule): void
    {
        // Load previous schedule
        $storedSchedule = ScheduleQueueCollection::create(
            json_decode($this->redis->get($date) ?? '[]', true)
        );

        if ($storedSchedule->isEmpty() && $newSchedule->isEmpty()) {
            // Skip empty schedule if it was empty
            return;
        }

        // Mark changed queues
        $newSchedule->markChangedQueues($storedSchedule);

        if ($storedSchedule->isNew() || $newSchedule->hasChangedValues()) {
            // Save new value
            $this->redis->set($date, json_encode($newSchedule->getValues()));

            // Dispatch generation and sending picture
            $this->messageBus->dispatch(new GenerateAndSendTelegramMessage($date, $newSchedule));
        }
    }
}
