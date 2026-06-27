<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'youtube_url' => [
                'required',
                'url',
                'regex:/(?:v=|youtu\.be\/|embed\/)([a-zA-Z0-9_-]{11})/',
            ],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'youtube_url.regex' => 'youtube_urlはYouTubeのURLを入力してください',
        ];
    }
}
