<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot\Commands\UserCommands;

use jacklul\e621bot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

class RandomCommand extends UserCommand
{
    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        $callback_query = $this->getCallbackQuery();
        $message = $this->getMessage();

        if ($callback_query) {
            $message = $callback_query->getMessage();

            $text = $this->getCallbackQuery()->getData();
        } else {
            $text = $message->getText(true);
        }

        $chat_id = $message->getChat()->getId();
        $message_id = $message->getMessageId();

        if (!$message->getChat()->isPrivateChat()) {
            $settings = $this->getTelegram()->getGroupSettings($chat_id);

            if ($settings === false) {
                if ($callback_query) {
                    return Request::answerCallbackQuery(
                        [
                            'callback_query_id' => $callback_query->getId(),
                        ]
                    );
                }

                return Request::emptyResponse();
            }

            $antispam = 0;
            if (isset($settings['antispam']) && (int)$settings['antispam'] > 0) {
                $antispam = (int)$settings['antispam'];
            }

            if ($antispam > 0) {
                $memcache = $this->getTelegram()->getMemcache();

                if (($antispam_remaining = $memcache->get('antispam:' . $this->getName() . ':' . $chat_id)) !== false) {
                    $antispam_text = 'Please wait ' . ($antispam - (time() - $antispam_remaining)) . ' seconds before next search.';

                    if ($antispam_remaining > 0) {
                        if ($callback_query) {
                            return Request::answerCallbackQuery(
                                [
                                    'callback_query_id' => $callback_query->getId(),
                                    'text'              => $antispam_text,
                                    'show_alert'        => true,
                                ]
                            );
                        }

                        $data = [
                            'chat_id'             => $chat_id,
                            'reply_to_message_id' => $message_id,
                            'text'                => '*' . $antispam_text . '*',
                            'parse_mode'          => 'markdown',
                        ];

                        return Request::sendMessage($data);
                    }
                }

                $memcache->set('antispam:' . $this->getName() . ':' . $chat_id, time(), null, $antispam);
            }

            if (
                (
                    empty($text) ||
                    (isset($settings['force']) && (int)$settings['force'] === 1)
                ) &&
                isset($settings['tags'])
            ) {
                $text = $settings['tags'];
            }

            if (isset($settings['sfw']) && (int)$settings['sfw'] === 1) {
                if (strpos($text, 'rating:') !== false) {
                    $text = preg_replace('/rating:\w+/', '', $text);
                }

                $text .= ' rating:safe';
            }
        }

        echo PHP_EOL . $text . PHP_EOL;

        Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);

        if ($callback_query) {
            $callback_query_data = [
                'callback_query_id' => $callback_query->getId(),
            ];
        }

        $request = $this->getTelegram()->getE621()->posts(['tags' => 'order:random ' . $text, 'limit' => 1]);

        if (isset($request['result'])) {
            if (count($request['result']['posts']) === 0) {
                if (isset($callback_query_data)) {
                    $callback_query_data['text'] = 'No posts matched your search.';
                    $callback_query_data['show_alert'] = true;

                    return Request::answerCallbackQuery($callback_query_data);
                }

                return Request::sendMessage(
                    [
                        'chat_id'             => $chat_id,
                        'reply_to_message_id' => $message_id,
                        'text'                => '*No posts matched your search.*',
                        'parse_mode'          => 'markdown',
                    ]
                );
            }

            $image = $request['result']['posts'][0];

            $image_url = '';
            if (isset($image['file']['url'])) {
                $image_url = '[Image](' . $image['file']['url'] . '), ';

                if ($image['file']['size'] > 5242880) {
                    $image_url = '[Sample](' . $image['sample']['url'] . '), ' . $image_url;
                }
            }

            $data = [
                'chat_id'             => $chat_id,
                'reply_to_message_id' => $message_id,
                'text'                => $image_url . '[Post](https://e621.net/posts/' . $image['id'] . ')' .
                    ', Score: *' . $image['score']['total'] . '*, Favorites: *' . $image['fav_count'] .
                    '*, Rating: *' . ucfirst($this->parseRating($image['rating'])) . '*',
                'parse_mode'          => 'markdown',
            ];

            if (strlen($text) <= 64) {
                $data['reply_markup'] = new InlineKeyboard(
                    [
                        new InlineKeyboardButton(
                            [
                                'text'          => 'Another',
                                'callback_data' => empty($text) ? ' ' : $text,
                            ]
                        ),
                    ]
                );
            }

            $result = Request::sendMessage($data);

            if ($result->isOk()) {
                if (isset($callback_query_data)) {
                    return Request::answerCallbackQuery($callback_query_data);
                }

                return $result;
            }

            TelegramLog::error($result->getDescription());
            $error = 'Telegram API error';
        } else {
            $error = $request['reason'];
            if (strpos($error, 'up to 6 tags') === false) {
                TelegramLog::error($error);

                $internal_error = $request['error'];
                if ($internal_error !== null) {
                    TelegramLog::error($internal_error);
                }
            } else {
                $error = str_replace('up to 6 tags', 'up to 5 tags', $error);   // Forced 'order:random' give user only 5 tags to use so correct the message
            }
        }

        if (isset($callback_query_data)) {
            $callback_query_data['text'] = 'Error: ' . $error;
            $callback_query_data['show_alert'] = true;

            return Request::answerCallbackQuery($callback_query_data);
        }

        return Request::sendMessage(
            [
                'chat_id'             => $chat_id,
                'reply_to_message_id' => $message_id,
                'text'                => '*Error:* ' . $error,
                'parse_mode'          => 'markdown',
            ]
        );
    }

    /**
     * @param $rating
     *
     * @return string
     */
    private function parseRating($rating)
    {
        switch ($rating) {
            case 's':
                return 'safe';
            case 'e':
                return 'explicit';
            case 'q':
                return 'questionable';
            default:
                return 'unknown';
        }
    }
}
