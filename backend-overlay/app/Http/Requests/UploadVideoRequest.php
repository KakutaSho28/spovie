<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadVideoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            // 500MB = 512000KB
            'file' => ['required', 'file', 'mimetypes:video/mp4', 'max:512000'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'mp4形式の動画ファイルをアップロードしてください',
            'file.max' => 'ファイルサイズは500MB以下にしてください',
        ];
    }
}
