<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Collection\ScheduleQueueCollection;
use App\Message\ParsingMessage;
use App\Service\RivneElectricityParsingService;
use DateTime;
use IntlDateFormatter;
use Predis\Client;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;

#[AsMessageHandler]
readonly class ParsingHandler
{
    private const DATE_FORMAT = 'd.m.Y';
    private const LOCALE      = 'uk_UA';
    private const TIMEZONE    = 'Europe/Kiev';

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
            json_decode($this->redis->get($date) ?? '[]')
        );

        if ($storedSchedule->isEmpty() && !$newSchedule->hasChangedValues()) {
            // Skip empty schedule if it was empty
            return;
        }

        // Mark changed queues
        $newSchedule->markChangedQueues($storedSchedule);

        if ($storedSchedule->isEmpty() || $newSchedule->hasChangedValues()) {
            // Save new value
            $this->redis->set($date, json_encode($newSchedule->getValues()));

            // Send message
            $this->sendMessage($date, $newSchedule);
        }
    }

    private function sendMessage(string $date, ScheduleQueueCollection $schedule): void
    {
        $dayOfWeek = $this->getDayOfWeek($date);
        $text      = "$dayOfWeek *$date*\n\n";

        foreach ($schedule as $queue) {
            $text .= sprintf("%s:%s\n\n", $queue->getTitleMarkdown(), rtrim("\n$queue->value"));
        }

        $message = new ChatMessage(trim($text));

        // Optional: Customize message with Telegram options
        $telegramOptions = (new TelegramOptions())
            ->parseMode('Markdown')
            ->disableNotification(true);

        $message->options($telegramOptions);

        // Send the message
        $this->chatter->send($message);
    }

    private function getDayOfWeek(string $dateString): string
    {
        $date      = DateTime::createFromFormat(self::DATE_FORMAT, $dateString);
        $formatter = new IntlDateFormatter(self::LOCALE, IntlDateFormatter::FULL, IntlDateFormatter::NONE, self::TIMEZONE, null, 'EEEE');

        return mb_convert_case($formatter->format($date), MB_CASE_TITLE, "UTF-8");
    }
}
