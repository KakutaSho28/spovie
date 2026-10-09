<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListVideosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'scope' => ['nullable', 'in:personal,all'],
            'team_id' => ['nullable', 'integer'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
