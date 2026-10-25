<?php

namespace App\Http\Requests\Api\Travel;

use App\Traits\HttpResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

abstract class TravelRequest extends FormRequest
{
    use HttpResponse;

    public function authorize(): bool
    {
        return true;
    }

    /** Sorting, filtering and paging shared by every result list. */
    protected function listRules(): array
    {
        return [
            'sort' => 'nullable|string|max:20',
            'direction' => 'nullable|in:asc,desc',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
            'providers' => 'nullable|array',
            'providers.*' => 'string',
            'min_price' => 'nullable|numeric|min:0',
            'max_price' => 'nullable|numeric|min:0',
            'max_stops' => 'nullable|integer|min:0|max:3',
            'airlines' => 'nullable|array',
            'airlines.*' => 'string|size:2',
            'refundable' => 'nullable|boolean',
            'min_stars' => 'nullable|integer|min:0|max:5',
            'min_score' => 'nullable|numeric|min:0|max:10',
            'min_rating' => 'nullable|numeric|min:0|max:5',
            'free_cancellation' => 'nullable|boolean',
            'types' => 'nullable|array',
            'categories' => 'nullable|array',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            $this->sendResponse(Response::HTTP_UNPROCESSABLE_ENTITY, $validator->errors()->first(), $validator->errors())
        );
    }
}
