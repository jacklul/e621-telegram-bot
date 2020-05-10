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
use Longman\TelegramBot\Entities\ChatMember;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;
use Longman\TelegramBot\Request;

class SettingsCommand extends UserCommand
{
    /**
     * @return ServerResponse
     * @throws TelegramException
     */
    public function execute()
    {
        $message = $this->getMessage();
        $chat_id = $message->getChat()->getId();
        $user_id = $message->getFrom()->getId();

        if ($message->getChat()->isPrivateChat()) {
            return Request::sendMessage(
                [
                    'chat_id'    => $chat_id,
                    'text'       => '*Settings are currently only available in groups!*',
                    'parse_mode' => 'markdown',
                ]
            );
        }

        if ($this->isGroupAdmin($user_id, $chat_id)) {
            $this->getTelegram()->getMemcache()->delete('settings:' . $chat_id);
        }

        $group_settings = $this->getTelegram()->getGroupSettings($chat_id);
        $settings = array_merge(
            [
                'tags'     => '',
                'force'    => 0,
                'antispam' => 0,
                'sfw'      => 0,
            ],
            $group_settings
        );

        if ($group_settings === false) {
            $text[] = '*Failed to fetch group description!*';
        } else if ($group_settings === null) {
            $text[] = '*Settings string is invalid!*';
        } else if (is_array($group_settings)) {
            $text[] = '*Default tags*: ' . (!empty($settings['tags']) ? $settings['tags'] : '(not set)');
            $text[] = '*Forced tags*: ' . ((int)$settings['force'] === 1 ? 'enabled' : 'disabled');
            $text[] = '*Anti-spam*: ' . ((int)$settings['antispam'] > 0 ? $settings['antispam'] . ' seconds' : 'disabled');
            $text[] = '*SFW mode*: ' . ((int)$settings['sfw'] === 1 ? 'enabled' : 'disabled');
        }

        $text[] = PHP_EOL . '[How to set group settings?](https://github.com/jacklul/e621-telegram-bot#group-settings) _Due to caching on Telegram\'s side it can take some time for the changes to be available to the bot._';

        return Request::sendMessage(
            [
                'chat_id'                  => $chat_id,
                'text'                     => implode(PHP_EOL, $text),
                'parse_mode'               => 'markdown',
                'disable_web_page_preview' => true,
            ]
        );
    }

    /**
     * @param $user_id
     * @param $chat_id
     *
     * @return bool
     */
    private function isGroupAdmin($user_id, $chat_id)
    {
        $result = Request::getChatAdministrators(['chat_id' => $chat_id]);

        if ($result->isOk() && is_array($result = $result->getResult())) {
            /** @var ChatMember $admin */
            foreach ($result as $admin) {
                if ((integer)$user_id === $admin->getUser()->getId()) {
                    return true;
                }
            }
        }

        return false;
    }
}
