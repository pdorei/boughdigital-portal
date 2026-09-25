<?php

namespace App\Http\Requests\Clients;

use App\Enums\ClientStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClientRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules(creating: $this->isMethod('POST'));
    }

    /**
     * Shared with the MCP client tools so every interface validates the same way.
     * Slug uniqueness is enforced by ClientService.
     */
    public static function fieldRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'status' => [$creating ? 'nullable' : 'sometimes', Rule::enum(ClientStatus::class)],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
