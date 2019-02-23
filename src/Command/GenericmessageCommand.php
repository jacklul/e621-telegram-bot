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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\StreamHandler;
use jacklul\e621bot\E621API;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\Document;
use Longman\TelegramBot\Entities\PhotoSize;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

/** @noinspection PhpUndefinedClassInspection */
class GenericmessageCommand extends SystemCommand
{
    const MAX_RESULTS = 5;

    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        $text = $this->getMessage()->getText(true);

        if ($this->isUrl($text)) {
            if (preg_match("/e621\.net/", $text) || preg_match('/e926\\.net/', $text)) {
                return $this->md5Search(preg_replace('/^.*([a-f0-9]{32}).*$/', '$1', $text));   // Image is from e621/e926 domain - MD5 lookup
            }

            return $this->reverseSearch($text);     // non-e621 url found, reverse search using image url
        }

        if ((($object = $this->getMessage()->getPhoto()) || ($object = $this->getMessage()->getDocument())) && !preg_match('/e621\\.net.*\\/show\\/(\\d+)/', trim($this->getMessage()->getCaption()))) {
            return $this->reverseSearch($object);     // message contains photo/document and has no e621 url in caption (results posted from inline search)
        }

        if (!$this->isUrl($text) && !$this->isUrl($this->getMessage()->getCaption())) {
            return $this->getTelegram()->executeCommand('random');  // message is just text, try to make /random search
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
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function md5Search($md5)
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $md5)) { // make sure the url contains image with MD5 file name
            return Request::emptyResponse();
        }

        Request::sendChatAction(['chat_id' => $this->getMessage()->getChat()->getId(), 'action' => 'typing']);

        $api = E621API::getApi();
        $request = $api->postCheckMd5(['md5' => $md5]);
        $result = $request->getResult();

        if ($request->isSuccessful()) {
            if (!$result->getExists()) {
                return Request::sendMessage(
                    [
                        'chat_id'             => $this->getMessage()->getChat()->getId(),
                        'reply_to_message_id' => $this->getMessage()->getMessageId(),
                        'text'                => '*Post not found*',
                        'parse_mode'          => 'markdown',
                    ]
                );
            }

            $post_id = $result->getPostId();
        } else {
            $error = $request->getReason();
            TelegramLog::error($error);

            $internal_error = $request->getError();
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
                'text'                     => '*Post:* https://e621.net/post/show/' . $post_id,
                'parse_mode'               => 'markdown',
                'disable_web_page_preview' => true,
            ]
        );
    }

    /**
     * @param string|array|Document $data
     *
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    private function reverseSearch($data = null)
    {
        Request::sendChatAction(['chat_id' => $this->getMessage()->getChat()->getId(), 'action' => 'typing']);

        try {
            /** @noinspection PhpUndefinedMethodInspection */
            $client = new Client(
                [
                    'base_uri' => 'http://iqdb.harry.lu',
                    'headers'  => [
                        'User-Agent' => $this->getTelegram()->getUserAgent(),
                    ],
                    'timeout'  => 15,
                    'handler'  => new StreamHandler(),
                    'verify'   => false,
                ]
            );

            if (is_string($data)) {
                if (($parts = parse_url($data)) && !isset($parts['scheme'])) {
                    $data = 'http://' . $data;
                }

                $response = $client->request('GET', '/?url=' . $data);
            } else {
                if (is_array($data) && $data[0] instanceof PhotoSize) {
                    $file_id = $data[count($data) - 1]->getFileId();
                } elseif ($data instanceof Document) {
                    $file_id = $data->getFileId();
                } else {
                    throw new \RuntimeException('No file provided');
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

                $response = $client->request('POST', '/', [
                    'multipart' => [
                        [
                            'name'     => 'file',
                            'contents' => $request_data,
                            'filename' => basename($result->getResult()->getFilePath()),
                        ],
                        [
                            'name'     => 'service[]',
                            'contents' => '0',
                        ],
                    ],
                ]);
            }

            $raw_result = (string)$response->getBody();
        } catch (GuzzleException $e) {
            TelegramLog::error($e);
            $raw_result = $e->getMessage();
        }

        if (preg_match_all("/Probable match.*?href='(.*?e621\.net.*?\/show\/\d+)/", $raw_result, $matches)) {
            $results = count($matches[1]) > self::MAX_RESULTS ? array_slice($matches[1], 0, self::MAX_RESULTS) : $matches[1];
        } elseif (strpos($raw_result, 'We didn\'t find any results that were highly-relevant') !== false || strpos($raw_result, 'No matches returned') !== false) {
            $results = [];
        } elseif (strpos($raw_result, 'Not an image') !== false || strpos($raw_result, 'unsupported filetype') !== false || strpos($raw_result, 'file too large') !== false) {
            $results = ['error' => 'This is not a valid image'];
        } elseif (empty($raw_result)) {
            $results = ['error' => 'Empty server reply or request timed out'];
        } else {
            TelegramLog::error('Unhandled result:' . PHP_EOL . '\'' . $raw_result . '\'');
        }

        if (!isset($results) || !is_array($results) || isset($results['error'])) {
            return Request::sendMessage(
                [
                    'chat_id'             => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'text'                => '*Error:* ' . (isset($results) ? $results['error'] : 'Unhandled error occurred - iqdb.harry.lu might be unreachable or returned an error'),
                    'parse_mode'          => 'markdown',
                ]
            );
        }

        $results = array_unique($results);
        $results = array_values($results);

        if (count($results) > 0) {
            if (count($results) === 1) {
                $text = '*Probable match:* ';
            } else {
                $text = '*Probable matches:*' . PHP_EOL;
            }

            $matches = [];
            for ($i = 0, $iMax = count($results); $i < $iMax; $i++) {
                $matches[] = '[' . $results[$i] . '](' . $results[$i] . ')';
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
