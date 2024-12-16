<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Panther\Client;
use Symfony\Component\Panther\DomCrawler\Crawler;

class RivneElectricityParsingService
{
    private const URL = 'https://www.roe.vsei.ua/disconnections';
    private const NO_VALUE = '';
    private const TITLE_SUB_SCHEDULE = 'Підчерга';

    public function __construct(
        ParameterBagInterface $params,
    )
    {
        $this->chrome = Client::createSeleniumClient(
            host: $params->get('app.chrome_driver'),
            options: [
                sprintf('--user-agent=%s', $params->get('app.user_agent')),
            ],
        );
    }

    public function parse(): array
    {
        $result  = [];
        $crawler = $this->chrome->request('GET', self::URL);

        $rows = $crawler->filter('#fetched-data-container tr');

        if ($rows->count() === 0) {
            throw new \Exception('Table with schedule wasn\'t found.');
        }

        $queueTitles = null;

        $rows->each(function (Crawler $node) use (&$result, &$queueTitles): void {
            $columns = $node->filter('td');

            if ($columns->count() === 0) {
                return;
            }

            $rowTitle = $columns->first()->text();

            if (!empty($queueTitles)) {
                $schedule = $this->parseQueueHours($queueTitles, $node);

                if (!empty($schedule)) {
                    $result[$rowTitle] = $schedule;
                }
            } elseif ($rowTitle === self::TITLE_SUB_SCHEDULE) {
                $queueTitles = $this->parseQueueTitle($node);
            }
        });

        return $result;
    }

    private function parseQueueTitle(Crawler $row): array
    {
        $result = [];
        $titles = $row->filter('td:not(:first-child)');

        $titles->each(function (Crawler $node) use (&$result): void {
            $result[] = trim($node->text());
        });

        return $result;
    }

    private function parseQueueHours(array $queueTitles, Crawler $row): array
    {
        $schedule = $row->filter('td:not(:first-child)');

        if ($schedule->count() === 0) {
            return [];
        }

        $i      = -1;
        $result = [];

        $schedule->each(function (Crawler $node) use (&$result, &$i, $queueTitles): void {
            $schedule = $queueTitles[++$i];
            $text = trim($node->text());

            if(preg_match('/\d{2}:\d{2}/', $text) > 0 || empty($text)) {
                $value = $text;
            } else {
                $value = self::NO_VALUE;
            }

            $result[$schedule] = $value;
        });

        return $result;
    }

    private function removeEmpty(array $schedules): array
    {
        $result = [];

        foreach ($schedules as $date => $schedule) {
            $cleared = array_filter($schedule, static fn($item): bool => $item !== self::NO_VALUE);

            if (empty($cleared) === false) {
                $result[$date] = $schedule;
            }
        }

        return $result;
    }
}
