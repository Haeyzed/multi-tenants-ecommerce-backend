<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\Media;

use App\Enums\Media\MediaCollection;
use App\Http\Requests\BaseRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates media library uploads (single or batch).
 */
class StoreMediaRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxImage = (int) config('media.upload_limits.image', 10240);
        $maxDocument = (int) config('media.upload_limits.document', 20480);
        $maxVideo = (int) config('media.upload_limits.video', 102400);
        $maxAudio = (int) config('media.upload_limits.audio', 51200);
        $max = max($maxImage, $maxDocument, $maxVideo, $maxAudio);

        $extensions = implode(',', array_unique([
            ...config('media.extensions.image', []),
            ...config('media.extensions.document', []),
            ...config('media.extensions.video', []),
            ...config('media.extensions.audio', []),
        ]));

        $mimetypes = implode(',', array_unique([
            ...config('media.mimes.image', []),
            ...config('media.mimes.document', []),
            ...config('media.mimes.video', []),
            ...config('media.mimes.audio', []),
        ]));

        return [
            'file' => ['required_without:files', 'nullable', 'file', 'mimes:'.$extensions, 'mimetypes:'.$mimetypes, 'max:'.$max],
            'files' => ['required_without:file', 'nullable', 'array', 'min:1', 'max:20'],
            'files.*' => ['required', 'file', 'mimes:'.$extensions, 'mimetypes:'.$mimetypes, 'max:'.$max],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'collection' => ['sometimes', 'nullable', 'string', Rule::in([MediaCollection::Library->value])],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasFile('file') && $this->hasFile('files')) {
                $validator->errors()->add('file', 'Provide either file or files, not both.');
            }
        });
    }
}
