<?php

namespace App\Services\Travel\Exceptions;

use App\Traits\HttpResponse;
use Exception;

/** A travel error the app can show; its code is the HTTP status. */
class TravelException extends Exception
{
    use HttpResponse;

    public function __construct(string $message, int $status = 400, public mixed $data = null)
    {
        parent::__construct($message, $status);
    }

    public function render($request)
    {
        return $this->sendResponse($this->getCode() ?: 400, $this->getMessage(), $this->data);
    }
}
