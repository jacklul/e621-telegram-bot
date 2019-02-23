<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace jacklul\e621bot;

use jacklul\E621API\E621;
use Longman\TelegramBot\TelegramLog;

class E621API
{
    /**
     * @var E621|null
     */
    private static $api;

    /**
     * @var array
     */
    private static $custom_options;

    /**
     * @param array $custom_options
     *
     * @throws \InvalidArgumentException
     */
    public static function construct(array $custom_options = [])
    {
        self::$custom_options = $custom_options;
    }

    /**
     * @throws \InvalidArgumentException
     */
    private static function init()
    {
        if (self::$api === null) {
            self::$api = new E621(self::$custom_options);
            self::$api->throwExceptions(false);

            if (TelegramLog::isDebugLogActive()) {
                self::$api->setDebugLogHandler('\Longman\TelegramBot\TelegramLog::debug');
            }
        }
    }

    /**
     * @return E621
     */
    public static function getApi()
    {
        self::init();

        if (self::$api === null) {
            throw new \RuntimeException('E621 instance not initialized - this should\'nt happen');
        }

        return self::$api;
    }
}
