<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot\Commands\SystemCommands;

use jacklul\e621bot\Commands\SystemCommand;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class CallbackqueryCommand extends SystemCommand
{
    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        if (
            $this->getCallbackQuery()->getData() !== '' &&
            (
                $this->getCallbackQuery()->getMessage() !== null &&
                strpos($this->getCallbackQuery()->getMessage()->getText(), 'Post') !== false
            )
        ) {
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
