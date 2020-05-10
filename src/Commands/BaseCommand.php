<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot\Commands;

use jacklul\e621bot\TelegramBot;
use Longman\TelegramBot\Commands\Command;
use ReflectionClass;
use ReflectionException;

abstract class BaseCommand extends Command
{
    /**
     * TelegramBot object
     *
     * @var TelegramBot
     */
    protected $telegram;

    /**
     * @return TelegramBot
     *
     * @noinspection SenselessMethodDuplicationInspection
     */
    public function getTelegram()
    {
        return $this->telegram;
    }

    /**
     * @return string
     */
    public function getName()
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        try {
            $name = (new ReflectionClass($this))->getShortName();
        } catch (ReflectionException $e) {
            return null;
        }

        return strtolower(str_replace('Command', '', $name));
    }
}
