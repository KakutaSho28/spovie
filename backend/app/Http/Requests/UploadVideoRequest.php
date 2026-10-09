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
            'file' => ['required', 'file', 'mimetypes:video/mp4', 'max:' . (config('media.max_upload_mb') * 1024)],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimetypes' => 'mp4形式の動画ファイルをアップロードしてください',
            'file.max' => 'ファイルサイズは' . config('media.max_upload_mb') . 'MB以下にしてください',
        ];
    }
}
