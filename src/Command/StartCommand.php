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

use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

/** @noinspection PhpUndefinedClassInspection */
class StartCommand extends UserCommand
{
    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        if ($this->getMessage()->getText(true) === '') {
            Request::sendSticker(
                [
                    'chat_id'             => $this->getMessage()->getChat()->getId(),
                    'reply_to_message_id' => $this->getMessage()->getMessageId(),
                    'sticker'             => 'CAADBAADEwAD8mXUBiJaO5i0S6dLAg',
                ]
            );

            return $this->getTelegram()->executeCommand('help');
        }

        return Request::emptyResponse();
    }
}
