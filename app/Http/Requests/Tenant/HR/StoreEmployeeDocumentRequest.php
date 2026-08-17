<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant\HR;

use App\Http\Requests\BaseRequest;

class StoreEmployeeDocumentRequest extends BaseRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('media.upload_limits.document', 20480),
                'mimetypes:'.implode(',', [
                    ...config('media.mimes.image', []),
                    ...config('media.mimes.document', []),
                ]),
            ],
            'name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
