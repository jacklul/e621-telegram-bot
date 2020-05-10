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
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class HelpCommand extends UserCommand
{
    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        $text[] = '*Help*';
        $text[] = PHP_EOL . '*Inline search*:' . PHP_EOL . ' Type in any chat:  `@' . $this->getTelegram()->getBotUsername() . ' <tags>`  and wait for the results to appear';

        if ($this->getMessage()->getChat()->isPrivateChat()) {
            $text[] = PHP_EOL . '*Random image*:' . PHP_EOL . ' Send tags as a text message or use  `/random <tags>`  command';
            $text[] = PHP_EOL . '*Image to post conversion*:' . PHP_EOL . ' Send direct e621 image link';
            $text[] = PHP_EOL . '*Reverse image search*:' . PHP_EOL . ' Send any direct image link or photo message';
        } else {
            $text[] = PHP_EOL . '*Random image*:' . PHP_EOL . ' Use  `/random <tags>`  command';
            $text[] = PHP_EOL . '*Show settings*:' . PHP_EOL . ' Use  `/settings`  command';
            $text[] = PHP_EOL . '*Private chat exclusive features were hidden, execute this command in private chat to see them.*';
        }

        $data = [
            'chat_id'                  => $this->getMessage()->getChat()->getId(),
            'text'                     => implode(PHP_EOL, $text),
            'parse_mode'               => 'markdown',
            'disable_web_page_preview' => true,
        ];

        if (!$this->getMessage()->getChat()->isPrivateChat()) {
            $data['reply_to_message_id'] = $this->getMessage()->getMessageId();
        }

        return Request::sendMessage($data);
    }
}
