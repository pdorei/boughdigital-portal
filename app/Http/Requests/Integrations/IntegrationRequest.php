<?php

namespace App\Http\Requests\Integrations;

use App\Enums\IntegrationProvider;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Which credential a provider needs, and whether it may be saved at all, is
 * decided by ClientIntegrationService from the provider catalogue.
 */
class IntegrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'provider' => $this->isMethod('POST') ? ['required', Rule::enum(IntegrationProvider::class)] : ['prohibited'],
            'name' => ['nullable', 'string', 'max:120'],
            'credentials' => ['nullable', 'array:api_key,access_token,secret'],
            'credentials.*' => ['nullable', 'string', 'max:4096'],
        ];
    }
}
