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

use jacklul\E621API\Entity\Post;
use jacklul\e621bot\E621API;
use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultGif;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultPhoto;
use Longman\TelegramBot\Entities\InlineQuery\InlineQueryResultVideo;
use Longman\TelegramBot\Entities\InputMessageContent\InputTextMessageContent;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\TelegramLog;

/** @noinspection PhpUndefinedClassInspection */
class InlinequeryCommand extends SystemCommand
{
    const MAX_PHOTO_FILE_SIZE = 5242880;   //  5 MB

    /**
     * @return ServerResponse
     */
    public function execute()
    {
        $offset = $this->getInlineQuery()->getOffset();
        $query = trim($this->getInlineQuery()->getQuery());

        $use_pages = false;
        if (preg_match('/.*(e621|e926)\.net.*\/show\/(\d+).*/', $query, $matches)) {
            $query = 'id:' . $matches[2];
        } elseif (preg_match("/(e621|e926)\.net.*([a-f0-9]{32}).*$/", $query, $matches)) {
            $query = 'md5:' . $matches[2];
        } else {
            if (stripos($query, 'order:random')) {
                $query = str_replace('order:random', '', strtolower($query));
            }

            if (strpos($query, 'order') !== false) {    // We cannot use 'before_id' when user uses 'order' tag, fallback to pages
                $offset = is_numeric($offset) ? $offset : 1;
                $use_pages = true;
            }
        }

        $data = [];
        $data['inline_query_id'] = $this->getInlineQuery()->getId();

        $request_options = ['tags' => $query, 'limit' => 25];
        if ($use_pages) {
            $request_options['page'] = $offset;
        } elseif (!empty($offset)) {
            $request_options['before_id'] = $offset;
        }

        $e621 = E621API::getApi();
        $request = $e621->postIndex($request_options);
        $results = $request->getResult();

        if ($request->isSuccessful()) {
            $contents = [];

            if (count($results) > 0) {
                /** @var Post $image */
                foreach ($results as $image) {
                    $element = null;

                    if (in_array($image->getFileExt(), ['jpg', 'jpeg', 'png'])) {
                        $image_url = $image->getFileUrl();

                        if ($image->getFileSize() > self::MAX_PHOTO_FILE_SIZE) {    // Telegram won't let you upload photo bigger than this so use sample image instead
                            $image_url = $image->getSampleUrl();
                        }

                        $contents[] = new InlineQueryResultPhoto(
                            [
                                'type'         => 'photo',
                                'id'           => $image->getId(),
                                'photo_url'    => $image_url,
                                'thumb_url'    => $image->getPreviewUrl(),
                                'photo_width'  => $image->getWidth(),
                                'photo_height' => $image->getHeight(),
                                'caption'      => 'https://e621.net/post/show/' . $image->getId(),
                                'title'        => 'Post #' . $image->getId(),
                                'description'  => '(' . $image->getFileExt() . ')',
                            ]
                        );
                    } elseif ($image->getFileExt() === 'gif' && $image->getFileSize() <= self::MAX_PHOTO_FILE_SIZE) {   // Telegram refuses to download GIFs bigger than this, even though it shows the thumbnails in the results window
                        $contents[] = new InlineQueryResultGif(
                            [
                                'type'        => 'gif',
                                'id'          => $image->getId(),
                                'gif_url'     => $image->getFileUrl(),
                                'thumb_url'   => $image->getPreviewUrl(),
                                'gif_width'   => $image->getWidth(),
                                'gif_height'  => $image->getHeight(),
                                'caption'     => 'https://e621.net/post/show/' . $image->getId(),
                                'title'       => 'Post #' . $image->getId(),
                                'description' => '(' . $image->getFileExt() . ')',
                            ]
                        );
                    } elseif ($image->getFileExt() === 'webm') {    // No native support for WEBM in Telegram yet, this is a bit of cheaty way that sends it as a message with web preview
                        $contents[] = new InlineQueryResultVideo(
                            [
                                'type'                  => 'video',
                                'id'                    => $image->getId(),
                                'video_url'             => $image->getFileUrl(),
                                'mime_type'             => 'video/mp4',
                                'thumb_url'             => $image->getPreviewUrl(),
                                'video_width'           => $image->getWidth(),
                                'video_height'          => $image->getHeight(),
                                'caption'               => 'https://e621.net/post/show/' . $image->getId(),
                                'title'                 => 'Post #' . $image->getId(),
                                'description'           => '(' . $image->getFileExt() . ')',
                                'input_message_content' => new InputTextMessageContent(
                                    [
                                        'message_text' => 'https://e621.net/post/show/' . $image->getId(),
                                    ]
                                ),
                            ]
                        );
                    }
                }

                $data['results'] = '[' . implode(',', $contents) . ']';

                if ($use_pages) {
                    $data['next_offset'] = $offset + 1;
                } else {
                    $data['next_offset'] = count($contents) > 0 ? end($results)->getId() : '';
                }
            } else {
                $data['results'] = '[]';
                $data['next_offset'] = '';
            }

            $data['cache_time'] = 300;  // Cache for 5 minutes on Telegram servers to prevent useless bot load
        } else {
            $error = $request->getReason();
            TelegramLog::error($error);

            $internal_error = $request->getError();
            if ($internal_error !== null) {
                TelegramLog::error($internal_error);
            }

            $data['results'] = '[]';
            $data['cache_time'] = 5;    // Do not cache for too long
            $data['switch_pm_text'] = $error;
            $data['switch_pm_parameter'] = 'error';
        }

        return Request::answerInlineQuery($data);
    }
}
