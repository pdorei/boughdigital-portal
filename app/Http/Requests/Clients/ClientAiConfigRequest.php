<?php

namespace App\Http\Requests\Clients;

use App\Models\ClientAiConfig;
use Illuminate\Foundation\Http\FormRequest;

class ClientAiConfigRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules();
    }

    /**
     * Every field is optional; shared with a future set_client_ai_config MCP tool.
     */
    public static function fieldRules(): array
    {
        return [
            'brand_tone' => ['nullable', 'string', 'max:5000'],
            'writing_preferences' => ['nullable', 'string', 'max:5000'],
            'industry_context' => ['nullable', 'string', 'max:5000'],
            'custom_instructions' => ['nullable', 'string', 'max:10000'],
            'key_urls' => ['nullable', 'array', 'list', 'max:'.ClientAiConfig::MAX_KEY_URLS],
            'key_urls.*' => ['array:label,url'],
            'key_urls.*.label' => ['required', 'string', 'max:80'],
            'key_urls.*.url' => ['required', 'url:http,https', 'max:2048'],
        ];
    }

    public function attributes(): array
    {
        return [
            'key_urls.*.label' => 'label',
            'key_urls.*.url' => 'URL',
        ];
    }
}
