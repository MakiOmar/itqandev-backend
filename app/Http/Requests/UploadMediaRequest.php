<?php

namespace App\Http\Requests;

use App\Models\AppMedia as Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

class UploadMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('upload', Media::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKb = (int) (config('media.max_file_size', 104857600) / 1024);

        return [
            'file' => [
                'required',
                'file',
                "max:{$maxKb}",
                'mimes:jpeg,jpg,png,webp,avif,gif,svg,mp4,webm,mov,pdf,doc,docx,xls,xlsx,ppt,pptx,txt,mp3,wav,ogg,m4a,aac',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    unset($attribute);
                    if (! $value instanceof UploadedFile) {
                        $fail('Invalid file upload.');

                        return;
                    }

                    $allowedMimes = [
                        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml', 'image/avif',
                        'video/mp4', 'video/webm', 'video/quicktime', 'video/x-msvideo',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/m4a', 'audio/aac',
                        'text/plain', 'text/csv',
                    ];

                    $mimeType = $value->getMimeType();
                    if (! in_array($mimeType, $allowedMimes, true)) {
                        $fail('File type '.$mimeType.' is not allowed.');
                    }
                },
            ],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }
}
