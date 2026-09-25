<?php

namespace App\Http\Requests\Documents;

use App\Enums\DocumentFolder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'folder' => ['sometimes', Rule::enum(DocumentFolder::class)],
            'document_type' => ['sometimes', 'nullable', 'string', 'max:80'],
            'external_url' => ['sometimes', 'url:http,https', 'max:2048'],
        ];
    }
}
