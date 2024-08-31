<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ParsingMessage;
use App\Service\RivneElectricityParsingService;
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
                if($this->saveSchedule($date, $schedule)) {
                    $this->sendMessage($date, $schedule);
                }
            }
        } catch (\Throwable $exception) {
            if ($message->isLastAttempt()) {
                throw $exception;
            }

            $this->messageBus->dispatch($message->getNextAttempt());
        }
    }

    private function saveSchedule(string $date, array $schedule): bool
    {
        $encodedSchedule = json_encode($schedule);
        $storedSchedule  = null;

        if ($this->redis->exists($date)) {
            $storedSchedule = $this->redis->get($date);
        }

        if($encodedSchedule !== $storedSchedule) {
            $this->redis->set($date, $encodedSchedule);

            return true;
        }

        return false;
    }

    private function sendMessage(string $date, array $schedule): void
    {
        $text = "*$date*\n\n";

        foreach ($schedule as $index => $item) {
            $group = $index + 1;
            $text .= "*Группа $group*:" . rtrim("\n$item") . "\n\n";
        }

        $message = new ChatMessage($text);

        // Optional: Customize message with Telegram options
        $telegramOptions = (new TelegramOptions())
            ->parseMode('Markdown')
            ->disableNotification(true);

        $message->options($telegramOptions);

        // Send the message
        $this->chatter->send($message);
    }
}
