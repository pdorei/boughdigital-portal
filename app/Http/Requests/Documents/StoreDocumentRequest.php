<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreDocumentRequest extends FormRequest
{
    public const MAX_UPLOAD_KILOBYTES = 20 * 1024;

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'txt', 'png', 'jpg', 'jpeg', 'webp'];

    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer'],
            'kind' => ['required', Rule::in(['upload', 'link'])],
            // types() checks the detected MIME type as well as the extension.
            'file' => ['required_if:kind,upload', 'exclude_unless:kind,upload', File::types(self::ALLOWED_EXTENSIONS)->max(self::MAX_UPLOAD_KILOBYTES)],
            'external_url' => ['required_if:kind,link', 'exclude_unless:kind,link', 'url:http,https', 'max:2048'],
            'title' => [$this->input('kind') === 'link' ? 'required' : 'nullable', 'string', 'max:255'],
            'folder' => ['required', Rule::enum(DocumentFolder::class)],
            'document_type' => ['nullable', 'string', 'max:80'],
        ];
    }
}
