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

use Longman\TelegramBot\Commands\SystemCommand;
use Longman\TelegramBot\Request;

/** @noinspection PhpUndefinedClassInspection */
class CallbackqueryCommand extends SystemCommand
{
    /**
     * @return \Longman\TelegramBot\Entities\ServerResponse
     * @throws \Longman\TelegramBot\Exception\TelegramException
     */
    public function execute()
    {
        if ($this->getCallbackQuery()->getData() !== '' && ($this->getCallbackQuery()->getMessage() !== null && strpos($this->getCallbackQuery()->getMessage()->getText(), ', Post') !== false)) {
            return $this->getTelegram()->executeCommand('random');
        }

        return Request::answerCallbackQuery(
            [
                'callback_query_id' => $this->getUpdate()->getCallbackQuery()->getId(),
                'text'              => 'Bad request',
                'show_alert'        => true,
            ]
        );
    }
}
