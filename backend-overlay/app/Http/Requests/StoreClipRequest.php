<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'video_id' => ['required', 'integer', 'exists:videos,id'],
            'annotation_id' => ['nullable', 'integer', 'exists:annotations,id'],
            'title' => ['required', 'string', 'max:255'],
            'start_seconds' => ['required', 'integer', 'min:0'],
            'end_seconds' => ['required', 'integer', 'gt:start_seconds'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_seconds.gt' => 'end_secondsはstart_secondsより大きい値を入力してください',
        ];
    }
}
