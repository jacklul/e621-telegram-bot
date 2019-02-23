<?php
/**
 * This file is part of the e621 Search Lite project.
 *
 * (c) Jack'lul <jacklulcat@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use jacklul\e621bot\Bot;

define('ROOT_PATH', dirname(__DIR__));

require_once __DIR__ . '/../vendor/autoload.php';

$bot = new Bot();
$bot->run();
