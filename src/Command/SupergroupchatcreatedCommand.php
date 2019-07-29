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
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Exception\TelegramException;

/** @noinspection PhpUndefinedClassInspection */
class SupergroupchatcreatedCommand extends SystemCommand
{
    /**
     * @return ServerResponse|mixed
     * @throws TelegramException
     */
    public function execute()
    {
        return $this->getTelegram()->executeCommand('start');
    }
}
