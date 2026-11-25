<?php

namespace App\Http\Requests\Content;

use Illuminate\Foundation\Http\FormRequest;

class ContentPillarRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules(creating: $this->isMethod('POST'));
    }

    /**
     * Name uniqueness within the client is checked by ContentPillarService.
     */
    public static function fieldRules(bool $creating): array
    {
        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
