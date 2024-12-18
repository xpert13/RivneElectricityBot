<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Collection\ScheduleQueueCollection;
use App\Message\GenerateAndSendTelegramMessage;
use DateTime;
use Facebook\WebDriver\WebDriverDimension;
use IntlDateFormatter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Notifier\Bridge\Telegram\TelegramOptions;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Panther\Client;
use Twig\Environment;

#[AsMessageHandler]
readonly class GenerateAndSendTelegramHandler
{
    private const HEADER_HEIGHT = 114;
    private const DATE_FORMAT   = 'd.m.Y';
    private const LOCALE        = 'uk_UA';
    private const TIMEZONE      = 'Europe/Kiev';
    private const NO_BLACKOUTS  = '_Відключення не заплановані_';
    private const MAX_CACHE_LIFETIME = 7 * 24 * 60 * 60; // 7 days

    private Client $chrome;
    private string $templatesDir;
    private string $screenshotDir;

    public function __construct(
        private Environment $twig,
        private ChatterInterface $chatter,
        ParameterBagInterface $params,
        private string $projectDir,
    ) {
        $this->chrome        = Client::createSeleniumClient(host: $params->get('app.chrome_driver'));
        $this->templatesDir  = sprintf('%s/var/html', $this->projectDir);
        $this->screenshotDir = sprintf('%s/var/screenshots', $this->projectDir);
    }

    /**
     * @throws \Throwable
     * @throws ExceptionInterface
     */
    public function __invoke(GenerateAndSendTelegramMessage $message): void
    {
        try {
            if ($message->schedules->isEmpty()) {
                $this->sendNoBlackoutsMessage($message->date);

                return;
            }

            $filepath   = $this->generateHtmlFile($message->date, $message->schedules);
            $screenshot = $this->makeScreenshot($message->date, $filepath);

            $this->sendMessage($message->date, $screenshot);

            $this->clearCache($this->templatesDir);
            $this->clearCache($this->screenshotDir);
        } catch (\Throwable $exception) {
            dump($exception->getMessage());

            throw $exception;
        }
    }

    private function generateHtmlFile(string $date, ScheduleQueueCollection $schedules): string
    {
        $filepath = sprintf('%s/%s.html', $this->templatesDir, $date);
        $html     = $this->twig->render('template.html.twig', [
            'dayOfWeek' => $this->getDayOfWeek($date),
            'date'      => $date,
            'schedules' => $schedules,
        ]);

        if (!file_exists($this->templatesDir)) {
            mkdir($this->templatesDir);
        }

        file_put_contents($filepath, $html);

        return $filepath;
    }

    private function makeScreenshot(string $date, string $htmlFilePath): string
    {
        $this->chrome->request('GET', 'file://' . $htmlFilePath);

        $pageHeight = $this->chrome->executeScript('return document.body.scrollHeight');

        $this->chrome->getWebDriver()->manage()->window()->setSize(new WebDriverDimension(500, $pageHeight + self::HEADER_HEIGHT));

        $filepath = sprintf('%s/%s.png', $this->screenshotDir, $date);

        if (!file_exists($this->screenshotDir)) {
            mkdir($this->screenshotDir);
        }

        $this->chrome->takeScreenshot($filepath);

        return $filepath;
    }

    private function sendNoBlackoutsMessage(string $date): void
    {
        $dayOfWeek = $this->getDayOfWeek($date);
        $text      = "$dayOfWeek *$date*\n\n" . self::NO_BLACKOUTS;

        $message = new ChatMessage($text);

        $telegramOptions = (new TelegramOptions())
            ->parseMode('Markdown')
            ->disableNotification(true);

        $message->options($telegramOptions);

        $this->chatter->send($message);
    }

    private function sendMessage(string $date, string $screenshot): void
    {
        $message = new ChatMessage($date);

        $telegramOptions = (new TelegramOptions())
            ->uploadPhoto($screenshot)
            ->parseMode('Markdown')
            ->disableNotification(true);

        $message->options($telegramOptions);

        $this->chatter->send($message);
    }

    private function getDayOfWeek(string $dateString): string
    {
        $date      = DateTime::createFromFormat(self::DATE_FORMAT, $dateString);
        $formatter = new IntlDateFormatter(self::LOCALE, IntlDateFormatter::FULL, IntlDateFormatter::NONE, self::TIMEZONE, null, 'EEEE');

        return mb_convert_case($formatter->format($date), MB_CASE_TITLE, "UTF-8");
    }

    private function clearCache(string $folderPath): void
    {
        if (!is_dir($folderPath)) {
            return;
        }

        $now   = time();
        $files = scandir($folderPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $folderPath . $file;

            if (is_file($filePath)) {
                $fileModifiedTime = filemtime($filePath);
                $fileAgeInSeconds = $now - $fileModifiedTime;

                if ($fileAgeInSeconds > self::MAX_CACHE_LIFETIME) {
                    unlink($filePath);
                }
            }
        }
    }
}
