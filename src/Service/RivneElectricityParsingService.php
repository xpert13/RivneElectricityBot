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

        $rows->each(function (Crawler $node) use (&$result): void {
            $columns = $node->filter('td');

            if ($columns->count() !== 7) {
                return;
            }

            $date = $columns->first()->text();

            if (empty($date)) {
                return;
            }

            $schedule = $node->filter('td:not(:first-child)');

            if ($schedule->count() === 0) {
                return;
            }

            $schedule->each(function (Crawler $node) use (&$result, $date): void {
                $text = trim($node->text());

                if(preg_match('/\d{2}:\d{2}/', $text) > 0 || empty($text)) {
                    $value = $text;
                } else {
                    $value = self::NO_VALUE;
                }

                $result[$date][] = $value;
            });
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
