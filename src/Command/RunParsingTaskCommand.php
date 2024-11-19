<?php

namespace App\Command;

use App\Message\ParsingMessage;
use App\MessageHandler\ParsingHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:parse',
    description: 'Run parsing new data task',
)]
class RunParsingTaskCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ParsingHandler $parsingHandler,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'sync',
                null,
                InputOption::VALUE_NONE,
                'If set, the command will run synchronously'
            );
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Check if the `--sync` option is provided
        if ($input->getOption('sync')) {
            // Run synchronously
            $handler = $this->parsingHandler;
            $handler(new ParsingMessage());
        } else {
            // Run asynchronously
            $this->messageBus->dispatch(new ParsingMessage());
        }

        return Command::SUCCESS;
    }
}
