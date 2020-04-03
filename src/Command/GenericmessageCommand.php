<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Longman\TelegramBot\Commands\SystemCommands;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\StreamHandler;
use jacklul\e621bot\E621API;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Document;
use Longman\TelegramBot\Entities\PhotoSize;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\User;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;
use RuntimeException;

/** @noinspection PhpUndefinedClassInspection */

class GenericmessageCommand extends SystemCommand
{
    const MAX_REVERSE_SEARCH_RESULTS = 5;
    const MAX_DOCUMENT_SIZE = 5242880;

    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $bot_id = $this->getTelegram()->getBotId();
        $bot_username = $this->getTelegram()->getBotUsername();

        if ($message->getGroupChatCreated()) {
            return $this->getTelegram()->executeCommand('help');
        }

        if ($new_chat_members = $message->getNewChatMembers()) {
            /** @var User $user */
            foreach ($new_chat_members as $user) {
                if ($user->getId() === (int)$bot_id) {
                    return $this->getTelegram()->executeCommand('help');
                }
            }
        }

        $messageContainsBotUsername = strpos($message->getText(true), $bot_username) !== false || strpos($message->getCaption(), $bot_username) !== false;

        if ($messageContainsBotUsername || $message->getReplyToMessage() || $message->getChat()->isPrivateChat()) {
            $message->getReplyToMessage() && $message = $message->getReplyToMessage();
            $text = $message->getText(true);
            $text = trim(str_replace('@' . $bot_username, '', $text));

            if ($this->isUrl($text)) {
                if (preg_match("/e621\.net/", $text) || preg_match('/e926\\.net/', $text)) {
                    return $this->md5Search(preg_replace('/^.*([a-f0-9]{32}).*$/', '$1', $text));   // Image is from e621/e926 domain - MD5 lookup
                }

                return $this->reverseSearch($text);     // non-e621 url found, reverse search using image url
            }

            if ((($object = $message->getPhoto()) || ($object = $message->getDocument())) && !preg_match('/e621\\.net.*\\/(show|posts)\\/(\\d+)/', trim($message->getCaption()))) {
                return $this->reverseSearch($object);     // message contains photo/document and has no e621 url in caption (results posted from inline search)
            }

            if (!$this->isUrl($text) && !$this->isUrl($message->getCaption())) {
                return $this->getTelegram()->executeCommand('random');  // message is just text, try to make /random search
            }
        }

        return Request::emptyResponse();
    }

    /**
     * @param $url
     *
     * @return bool
     */
    private function isUrl($url)
    {
        if (preg_match('/\s/', trim($url)) || !preg_match('/^.*\..*.\//', trim($url))) {
            return false;
        }

        if (($parts = parse_url($url)) && !isset($parts['scheme'])) {
            $url = 'http://' . $url;
        }

        $url = parse_url($url);

        if (isset($url['host'], $url['path'])) {
            return true;
        }

        return false;
    }

    /**
     * @param $md5
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    private function md5Search($md5)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $md5)) { // make sure the url contains image with MD5 file name
            return Request::emptyResponse();
        }

        Request::sendChatAction(['chat_id' => $this->getMessage()->getChat()->getId(), 'action' => 'typing']);

        /** @var E621API $api */
        $api = $this->getTelegram()->getE621();
        $request = $api->posts(['tags' => 'md5:' . $md5]);

        if (isset($request['result'])) {
            $result = $request['result']['posts'];

            if (empty($result)) {
                return Request::sendMessage(
                    [
                        'chat_id'             => $this->getMessage()->getChat()->getId(),
                        'reply_to_message_id' => $this->getMessage()->getMessageId(),
                        'text'                => '*Post not found*',
                        'parse_mode'          => 'markdown',
                    ]
                );
            }

            $post_id = $result[0]['id'];
        } else {
            $error = $request['reason'];
            TelegramLog::error($error);

            $internal_error = $request['error'];
            if ($internal_error !== null) {
                TelegramLog::error($internal_error);
            }

            return Request::sendMessage(
                [
                    'chat_id'             => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text'                => '*Error:* ' . $error,
                    'parse_mode'          => 'markdown',
                ]
            );
        }

        return Request::sendMessage(
            [
                'chat_id'                  => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id'      => $this->getMessage()->getMessageId(),
                'text'                     => '*Post:* https://e621.net/posts/' . $post_id,
                'parse_mode'               => 'markdown',
                'disable_web_page_preview' => true,
            ]
        );
    }

    /**
     * @param string|array|Document $data
     *
     * @return ServerResponse
     * @throws TelegramException
     */
    private function reverseSearch($data = null)
    {
        Request::sendChatAction(['chat_id' => $this->getMessage()->getChat()->getId(), 'action' => 'typing']);

        try {
            $options = [
                'base_uri' => 'https://e621.net/iqdb_queries.json',
                'headers'  => [
                    'User-Agent' => $this->getTelegram()->getUserAgent(),
                ],
                'timeout'  => 30,
                'handler'  => new StreamHandler(),
                'verify'   => false,
            ];

            $auth = [getenv('E621_LOGIN'), getenv('E621_API_KEY')];

            if (!empty($auth[0]) && !empty($auth[1])) {
                $options['auth'] = $auth;
            }

            $client = new Client($options);

            if (is_string($data)) {
                if (($parts = parse_url($data)) && !isset($parts['scheme'])) {
                    $data = 'http://' . $data;
                }

                $response = $client->request('GET', '?url=' . $data);
            } else {
                if (is_array($data) && $data[0] instanceof PhotoSize) {
                    $file_id = $data[count($data) - 1]->getFileId();
                } elseif ($data instanceof Document) {
                    if (strpos($data->getMimeType(), 'image/') === false) {
                        return Request::sendMessage(
                            [
                                'chat_id'             => $this->getMessage()->getChat()->getId(),
                                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                                'text'                => '*Error:* File is not an image',
                                'parse_mode'          => 'markdown',
                            ]
                        );
                    }

                    if ($data->getFileSize() > self::MAX_DOCUMENT_SIZE) {
                        return Request::sendMessage(
                            [
                                'chat_id'             => $this->getMessage()->getChat()->getId(),
                                'reply_to_message_id' => $this->getMessage()->getMessageId(),
                                'text'                => '*Error:* File exceeds 5MB file size limit',
                                'parse_mode'          => 'markdown',
                            ]
                        );
                    }

                    $file_id = $data->getFileId();
                } else {
                    throw new RuntimeException('No file provided - this shouldn\'t happen!');
                }

                $result = Request::getFile(['file_id' => $file_id]);

                $request_data = null;
                if ($result->isOk()) {
                    $request_data = file_get_contents('https://api.telegram.org/file/bot' . $this->getTelegram()->getApiKey() . '/' . $result->getResult()->getFilePath());
                }

                if ($request_data === null) {
                    return Request::sendMessage(
                        [
                            'chat_id'             => $this->getMessage()->getChat()->getId(),
                            'reply_to_message_id' => $this->getMessage()->getMessageId(),
                            'text'                => '*Error:* Image couldn\'t be downloaded',
                            'parse_mode'          => 'markdown',
                        ]
                    );
                }

                $response = $client->request(
                    'POST', '',
                    [
                        'multipart' => [
                            [
                                'name'     => 'file',
                                'contents' => $request_data,
                                'filename' => basename($result->getResult()->getFilePath()),
                            ],
                        ],
                    ]
                );
            }

            $raw_result = (string)$response->getBody();
            $json_result = json_decode($raw_result, true);

            if (is_array($json_result)) {
                if (isset($json_result['posts'])) {
                    $json_result = $json_result['posts'];
                }

                $results = [];
                if (count($json_result) > 0 && isset($json_result[0]['post_id'])) {
                    foreach ($json_result as $result) {
                        $results[] = 'https://e621.net/posts/' . $result['post_id'];
                    }

                    $results = count($results) > self::MAX_REVERSE_SEARCH_RESULTS ? array_slice($results, 0, self::MAX_REVERSE_SEARCH_RESULTS) : $results;
                }
            }
        } catch (Exception $e) {
            TelegramLog::error($e);
        }

        if (!isset($results) || isset($json_result['message'])) {
            return Request::sendMessage(
                [
                    'chat_id'             => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text'                => '*Error:* ' . (isset($json_result, $json_result['message']) ? $json_result['message'] . ' (e621.net)' : 'Unhandled error occurred - service might be unreachable or returned an error'),
                    'parse_mode'          => 'markdown',
                ]
            );
        }

        $results = array_unique($results);
        $results = array_values($results);

        if (count($results) > 0) {
            if (count($results) === 1) {
                $text = '*Post:*';
            } else {
                $text = '*Posts:*' . PHP_EOL;
            }

            $matches = [];
            foreach ($results as $iValue) {
                $matches[] = '[' . $iValue . '](' . $iValue . ')';
            }

            $text .= ' ' . implode(PHP_EOL . ' ', $matches);
        } else {
            $text = '*No matching images found*';
        }

        return Request::sendMessage(
            [
                'chat_id'                  => $this->getMessage()->getChat()->getId(),
                'reply_to_message_id'      => $this->getMessage()->getMessageId(),
                'text'                     => $text,
                'parse_mode'               => 'markdown',
                'disable_web_page_preview' => true,
            ]
        );
    }
}
