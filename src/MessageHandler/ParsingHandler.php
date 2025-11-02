<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Collection\ScheduleQueueCollection;
use App\Message\GenerateAndSendTelegramMessage;
use App\Message\ParsingMessage;
use App\Service\RivneElectricityParsingService;
use Predis\Client;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly class ParsingHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private RivneElectricityParsingService $parsingService,
        private LoggerInterface $logger,
        private Client $redis,
    ) {
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function __invoke(ParsingMessage $message): void
    {
        $this->logger->info('Start parsing schedule.');

        try {
            $schedules = $this->parsingService->parse();

            foreach ($schedules as $date => $schedule) {
                $this->processSchedule($date, ScheduleQueueCollection::create($schedule));
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Exception: ' . $exception->getMessage(), $exception->getTrace());

            if ($message->isLastAttempt()) {
                throw $exception;
            }

            $this->logger->info('Try to do one more attempt.');
            $this->messageBus->dispatch($message->getNextAttempt());
        }

        $this->logger->info('Finish parsing schedule.');
    }

    private function processSchedule(string $date, ScheduleQueueCollection $newSchedule): void
    {
        $this->logger->info('Processing schedule for ' . $date);

        // Load previous schedule
        $storedSchedule = ScheduleQueueCollection::create(
            json_decode($this->redis->get($date) ?? '[]', true)
        );

        if ($storedSchedule->isEmpty() && $newSchedule->isEmpty()) {
            $this->logger->info('The schedule is empty. Skipping...');
            return;
        }

        // Mark changed queues
        $newSchedule->markChangedQueues($storedSchedule);

        if ($storedSchedule->isNew() || $newSchedule->hasChangedValues()) {
            // Save new value
            $this->redis->set($date, json_encode($newSchedule->getValues()));

            // Dispatch generation and sending picture
            $this->logger->info('Dispatch sending telegram message.');
            $this->messageBus->dispatch(new GenerateAndSendTelegramMessage($date, $newSchedule));
        } else {
            $this->logger->info('The schedule is not new. Skipping.');
        }
    }
}
