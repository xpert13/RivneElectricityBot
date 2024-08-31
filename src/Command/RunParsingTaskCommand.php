<?php

namespace App\Command;

use App\Message\ParsingMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:parse',
    description: 'Run parsing new data task',
)]
class RunParsingTaskCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->messageBus->dispatch(new ParsingMessage());

        return Command::SUCCESS;
    }
}
