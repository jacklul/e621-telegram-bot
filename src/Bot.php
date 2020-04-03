<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot;

use Dotenv\Dotenv;
use Exception;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use RuntimeException;
use Throwable;

class Bot
{
    /**
     * @var TelegramBot
     */
    private $telegram;

    /**
     * @throws RuntimeException
     */
    public function __construct()
    {
        if (!defined('ROOT_PATH')) {
            throw new RuntimeException('ROOT_PATH is not defined');
        }

        ini_set('display_errors', false);

        if (class_exists(Dotenv::class) && file_exists(ROOT_PATH . '/.env')) {
            $env = Dotenv::create(ROOT_PATH);
            $env->load();
        }
    }

    /**
     * @throws Exception
     * @throws Throwable
     */
    public function run()
    {
        $this->initialize();

        if (!$this->validateRequest()) {
            exit;
        }

        $arg = '';
        if (isset($_GET['a'])) {
            $arg = strtolower(trim($_GET['a']));
        } elseif (isset($_SERVER['argv'][1])) {
            $arg = strtolower(trim($_SERVER['argv'][1]));
        }

        switch (strtolower($arg)) {
            case 'run':
                if (PHP_SAPI !== 'cli') {
                    $this->telegram->handle();
                } else {
                    $this->handleLoop();
                }
                break;
            case 'set':
                $this->setWebhook();
                break;
            case 'unset':
                $this->deleteWebhook();
                break;
            case 'info':
                $this->webhookInfo();
                break;
            default:
                print 'Invalid command' . PHP_EOL;
                print ' Commands: run, set, unset, info' . PHP_EOL;
        }
    }

    /**
     * @throws TelegramException
     */
    private function initialize()
    {
        $bot_username = getenv('BOT_USERNAME');

        $this->telegram = new TelegramBot(getenv('BOT_API_KEY'), $bot_username);

        $logger = new Logger($bot_username);
        $level = Logger::ERROR;

        if ((bool)getenv('DEBUG') === true) {
            $level = Logger::DEBUG;
        }

        $logger->pushHandler(new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, $level));   // Log to PHP error_log
        TelegramLog::initialize($logger);

        $this->telegram->addCommandsPath(ROOT_PATH . '/src/Command/');

        if (!empty(getenv('BOT_ADMIN'))) {
            $this->telegram->enableAdmin((int)getenv('BOT_ADMIN'));
        }
    }

    /**
     * @return bool
     */
    private function validateRequest()
    {
        if (PHP_SAPI !== 'cli') {
            $secret = getenv('BOT_SECRET');
            $secret_get = $_GET['s'] ?: '';

            if (!isset($secret, $secret_get) || $secret !== $secret_get) {
                return false;
            }
        }

        return true;
    }

    /**
     * @throws TelegramException
     */
    private function handleLoop()
    {
        if (PHP_SAPI !== 'cli') {
            print 'Cannot run this from the web space!' . PHP_EOL;

            return;
        }

        $this->telegram->useGetUpdatesWithoutDatabase();

        print 'Running with getUpdates method...' . PHP_EOL;
        while (true) {
            @set_time_limit(0);

            $server_response = $this->telegram->handleGetUpdates();
            if ($server_response->isOk()) {
                $update_count = count($server_response->getResult());

                if ($update_count > 0) {
                    print PHP_EOL . 'Processed ' . $update_count . ' updates' . PHP_EOL;
                }
            } else {
                print PHP_EOL . 'Failed to process updates, error: ' . $server_response->getDescription() . PHP_EOL;
            }

            sleep(1);
        }
    }

    /**
     * @throws TelegramException
     */
    private function setWebhook()
    {
        if (empty($webhook_url = getenv('BOT_WEBHOOK'))) {
            throw new RuntimeException('BOT_WEBHOOK is not set');
        }

        $result = $this->telegram->setWebhook(
            $webhook_url,
            [
                'max_connections' => 5,
                'allowed_updates' => [
                    'message',
                    'inline_query',
                    'callback_query',
                ],
            ]
        );

        print $result->getDescription() . PHP_EOL;
    }

    /**
     * @throws TelegramException
     */
    private function deleteWebhook()
    {
        $result = $this->telegram->deleteWebhook();
        print $result->getDescription() . PHP_EOL;
    }

    /**
     * Get webhook info
     */
    private function webhookInfo()
    {
        $result = Request::getWebhookInfo();

        if ($result->isOk()) {
            if (PHP_SAPI !== 'cli') {
                print '<pre>' . print_r($result->getResult(), true) . '</pre>' . PHP_EOL;
            } else {
                print print_r($result->getResult(), true) . PHP_EOL;
            }
        } else {
            print $result->getDescription() . PHP_EOL;
        }
    }
}
