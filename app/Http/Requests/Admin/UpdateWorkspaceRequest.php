<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Workspaces\WorkspaceSettingsRequest;
use App\Support\Routing\ReservedWorkspaceSlugs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace && ($this->user()?->can('update', $workspace) ?? false);
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug((string) $this->input('slug')),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $workspace = $this->route('workspace');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(ReservedWorkspaceSlugs::all()), Rule::unique('workspaces', 'slug')->ignore($workspace)],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'remove_logo' => ['sometimes', 'boolean'],
            'primary_colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_colour' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings' => ['nullable', 'array'],
            ...WorkspaceSettingsRequest::dateTimeRules('settings.'),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.regex' => 'The slug may only contain lowercase letters, numbers, and hyphens.',
            'slug.unique' => 'That workspace slug is already in use.',
        ];
    }
}
