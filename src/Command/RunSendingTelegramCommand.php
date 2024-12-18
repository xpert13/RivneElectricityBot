<?php

namespace App\Command;

use App\Collection\ScheduleQueueCollection;
use App\Message\GenerateAndSendTelegramMessage;
use App\MessageHandler\GenerateAndSendTelegramHandler;
use Predis\Client;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;

#[AsCommand(
    name: 'app:send-telegram',
    description: 'Run sending telegram command',
)]
class RunSendingTelegramCommand extends Command
{
    public function __construct(
        private readonly GenerateAndSendTelegramHandler $generateAndSendTelegramHandler,
        private readonly Client $redis,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'date',
                null,
                InputOption::VALUE_REQUIRED,
                'Date'
            );
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date      = $input->getOption('date');
        $schedules = ScheduleQueueCollection::create(
            json_decode($this->redis->get($date) ?? '[]', true)
        );

        $handler = $this->generateAndSendTelegramHandler;
        $handler(new GenerateAndSendTelegramMessage($date, $schedules));

        return Command::SUCCESS;
    }
}
