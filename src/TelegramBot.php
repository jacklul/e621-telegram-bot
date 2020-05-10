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

use Exception;
use GuzzleHttp\Exception\InvalidArgumentException;
use Longman\TelegramBot\Commands\Command;
use Longman\TelegramBot\Entities\Chat;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Telegram;
use Memcache;

class TelegramBot extends Telegram
{
    /**
     * @var string
     */
    private $user_agent;

    /**
     * @var E621API
     */
    private $e621;

    /**
     * @var Memcache
     */
    private $memcache;

    /**
     * @param string $api_key
     * @param string $bot_username
     *
     * @throws TelegramException
     */
    public function __construct($api_key, $bot_username = '')
    {
        parent::__construct($api_key, $bot_username);

        $this->user_agent = 'Telegram Bot: @' . $bot_username;
        $options = [
            'timeout' => 15,
            'headers' => [
                'User-Agent' => $this->user_agent,
            ],
        ];

        $this->e621 = new E621API($options);

        $this->memcache = new Memcache();
        PHP_SAPI === 'cli' && $this->memcache->addServer('127.0.0.1', 11211, 100);
    }

    /**
     * @return bool
     * @throws Exception
     */
    public function handle()
    {
        try {
            return parent::handle();
        } catch (Exception $e) {
            $this->notifyUser($e);

            if (strpos($e->getMessage(), 'Telegram returned an invalid response!') === false) {
                throw $e;
            }
        }

        return false;
    }

    /**
     * Informs user about unhandled bot error
     *
     * @param Exception $exception
     */
    public function notifyUser($exception)
    {
        if ($update = $this->update) {
            $text = 'Unhandled error occurred.';

            if ($exception instanceof Exception && $this->isAdmin()) {
                $text = str_replace('Please review your bot name and API key.', '', $exception->getMessage());
            }

            $message = $update->getMessage();
            $callback_query = $update->getCallbackQuery();
            $inline_query = $update->getInlineQuery();

            try {
                if ($message) {
                    Request::sendMessage(
                        [
                            'chat_id'    => $message->getChat()->getId(),
                            'text'       => '*' . $text . '*',
                            'parse_mode' => 'markdown',
                        ]
                    );
                } elseif ($callback_query) {
                    Request::answerCallbackQuery(
                        [
                            'callback_query_id' => $callback_query->getId(),
                            'text'              => $text,
                            'show_alert'        => true,
                        ]
                    );
                } elseif ($inline_query) {
                    Request::answerInlineQuery(
                        [
                            'inline_query_id'     => $inline_query->getId(),
                            'switch_pm_text'      => $text,
                            'switch_pm_parameter' => 'error',
                            'results'             => '[]',
                            'cache_time'          => 0,
                            'is_personal'         => true,
                        ]
                    );
                }
            } catch (Exception $e) {
                // Do nothing...
            }
        }
    }

    /**
     * @return string
     */
    public function getUserAgent()
    {
        return $this->user_agent;
    }

    /**
     * @return E621API
     */
    public function getE621()
    {
        return $this->e621;
    }

    /**
     * @return Memcache
     */
    public function getMemcache()
    {
        return $this->memcache;
    }

    /**
     * @param string $command
     *
     * @return Command|null
     *
     * @noinspection SenselessMethodDuplicationInspection
     */
    public function getCommandObject($command)
    {
        $which = ['System'];
        $this->isAdmin() && $which[] = 'Admin';
        $which[] = 'User';

        foreach ($which as $auth) {
            $command_namespace = __NAMESPACE__ . '\\Commands\\' . $auth . 'Commands\\' . $this->ucfirstUnicode($command) . 'Command';
            if (class_exists($command_namespace)) {
                return new $command_namespace($this, $this->update);
            }
        }

        return null;
    }

    /**
     * @param $chat_id
     *
     * @return array|bool|null
     */
    public function getGroupSettings($chat_id)
    {
        if (($cached_settings = $this->memcache->get('settings:' . $chat_id)) !== false && is_array($cached_settings)) {
            return $cached_settings;
        }

        $chat_data = Request::getChat(['chat_id' => $chat_id]);

        if ($chat_data->isOk()) {
            /** @var Chat $result */
            $result = $chat_data->getResult();
            $group_description = $result->getDescription();

            preg_match('/.*@' . $this->getBotUsername() .'\[(.*)\].*/', $group_description, $matches);

            if (isset($matches[1])) {
                try {
                    $settings = \GuzzleHttp\json_decode($matches[1], true);
                } catch (InvalidArgumentException $e) {
                    return null;
                }

                $this->memcache->set('settings:' . $chat_id, $settings, null, 3600);

                return $settings;
            }

            return [];
        }

        return false;
    }
}
