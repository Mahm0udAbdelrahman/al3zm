<?php

namespace App\Exceptions;

use Exception;
use App\Traits\HttpResponse;
use Symfony\Component\HttpFoundation\Response;

class NotFoundException extends Exception
{
    use HttpResponse;

    public function render($request)
    {
        if ($request->expectsJson()) {
            return $this->sendResponse(Response::HTTP_NOT_FOUND, $this->getMessage() ?: 'Not found',null,null,400);
        }


    }
}
