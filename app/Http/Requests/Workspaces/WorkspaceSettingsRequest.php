<?php

namespace App\Http\Requests\Workspaces;

use App\Services\DateTime\DateTimePreferenceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkspaceSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('updateSettings', $this->attributes->get('workspace'));
    }

    public function rules(): array
    {
        return self::dateTimeRules();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function dateTimeRules(string $prefix = ''): array
    {
        return [
            "{$prefix}timezone" => ['nullable', 'string', 'timezone:all'],
            "{$prefix}date_format" => ['nullable', 'string', Rule::in(array_keys(DateTimePreferenceService::DATE_FORMATS))],
            "{$prefix}datetime_format" => ['nullable', 'string', Rule::in(array_keys(DateTimePreferenceService::DATETIME_FORMATS))],
        ];
    }
}
