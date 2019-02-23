<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\UserCommands;

use jacklul\e621bot\E621API;
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

class RandomCommand extends UserCommand
{
    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $callback_query = $this->getCallbackQuery();

        if ($callback_query) {
            $chat_id = $callback_query->getMessage()->getChat()->getId();
            $message_id = $callback_query->getMessage()->getMessageId();
            $text = $this->getCallbackQuery()->getData();
        } else {
            $chat_id = $this->getMessage()->getChat()->getId();
            $message_id = $this->getMessage()->getMessageId();
            $text = $this->getMessage()->getText(true);
        }

        Request::sendChatAction(['chat_id' => $chat_id, 'action' => 'typing']);

        if ($callback_query) {
            $callback_query_data = [
                'callback_query_id' => $callback_query->getId(),
            ];
        }

        $api = E621API::getApi();
        $request = $api->postIndex(['tags' => 'order:random ' . $text, 'limit' => 1]);

        if ($request->isSuccessful()) {
            $results = $request->getResult();

            if (count($results) === 0) {
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

            /** @var \jacklul\E621API\Entity\Post $image */
            $image = $results[0];

            $image_url = '[Image](' . $image->getFileUrl() . '), ';
            if ($image->getFileSize() > 5242880) {
                $image_url = '[Sample](' . $image->getSampleUrl() . '), ' . $image_url;
            }

            $data = [
                'chat_id'             => $chat_id,
                'reply_to_message_id' => $message_id,
                'text'                => $image_url . '[Post](https://e621.net/post/show/' . $image->getId() . ')' .
                                        ', Score: *' . $image->getScore() . '*, Favorites: *' . $image->getFavCount() .
                                        '*, Rating: *' . ucfirst($this->parseRating($image->getRating())) . '*',
                'parse_mode'          => 'markdown',
            ];

            if (strlen($text) <= 64) {
                $data['reply_markup'] = new InlineKeyboard(
                    [
                        new InlineKeyboardButton(
                            [
                                'text'          => 'Another',
                                'callback_data' => empty($text) ? ' ' : $text
                            ]
                        )
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
            $error = $request->getReason();
            if (strpos($error, 'up to 6 tags') === false) {
                TelegramLog::error($error);

                $internal_error = $request->getError();
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
