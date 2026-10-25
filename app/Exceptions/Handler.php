<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    protected $dontReport = [

    ];

    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    public function register(): void
    {
        $this->renderable(function (Throwable $e, $request) {

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'code' => $e->getCode() ?: 500,
                ], $e->getCode() ?: 500);
            }

            
            return response()->view('errors.custom', [
                'message' => $e->getMessage(),
                'code' => $e->getCode() ?: 500,
            ], $e->getCode() ?: 500);
        });
    }

    public function render($request, Throwable $exception)
{
    if ($request->expectsJson()) {
        return response()->json([
            'success' => false,
            'message' => $exception->getMessage(),
            'code' => $exception->getCode() ?: 500,
        ], $exception instanceof \Illuminate\Http\Exceptions\HttpResponseException ? $exception->getStatusCode() : 500);
    }

    return parent::render($request, $exception);
}

}
