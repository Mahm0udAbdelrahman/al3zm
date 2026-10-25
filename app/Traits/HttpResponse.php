<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait HttpResponse
{
    public function sendResponse(
        int $httpStatus = 200,
        ?string $message = null,
        mixed $data = null,
        ?array $pagination = null,
        int|string|null $code = null,
    ): JsonResponse {
        $response = [
            'status' => $httpStatus >= 200 && $httpStatus < 300,
            'code' => $code ?? $httpStatus,
            'message' => $message,
            'data' => $data,
        ];

        if ($pagination !== null) {
            $response['pagination'] = $pagination;
        }

        return response()->json($response, $httpStatus);
    }

    
}
