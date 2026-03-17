<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

final class NewsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cursor' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'category' => ['nullable', 'string', 'max:64'],
            'sentiment_min' => ['nullable', 'integer', 'between:-10,10'],
            'sentiment_max' => ['nullable', 'integer', 'between:-10,10'],
            'important' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:255'],
            'source_id' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $sentimentMin = $this->input('sentiment_min');
            $sentimentMax = $this->input('sentiment_max');
            if ($sentimentMin !== null && $sentimentMax !== null && (int) $sentimentMin > (int) $sentimentMax) {
                $validator->errors()->add('sentiment_range', 'sentiment_min must be less than or equal to sentiment_max.');
            }

            $dateFrom = $this->input('date_from');
            $dateTo = $this->input('date_to');
            if ($dateFrom !== null && $dateTo !== null && strtotime((string) $dateFrom) > strtotime((string) $dateTo)) {
                $validator->errors()->add('date_range', 'date_from must be less than or equal to date_to.');
            }
        });
    }
}
