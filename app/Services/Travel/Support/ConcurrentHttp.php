<?php

namespace App\Services\Travel\Support;

use GuzzleHttp\Utils;

/**
 * One curl handler shared by every async provider request in this process,
 * so requests to different providers run at the same time instead of one
 * after another.
 */
class ConcurrentHttp
{
    private $handler;

    public function handler(): callable
    {
        return $this->handler ??= Utils::chooseHandler();
    }
}
