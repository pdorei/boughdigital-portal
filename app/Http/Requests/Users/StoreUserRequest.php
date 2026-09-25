<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->user()->isSuperAdmin()) {
            return true;
        }

        $role = UserRole::tryFrom((string) $this->input('role'));

        return $role !== null && $this->user()->can('assignRole', [User::class, $role, $this->targetWorkspace()]);
    }

    public function rules(): array
    {
        $superAdmin = $this->user()->isSuperAdmin();

        return [
            'workspace_id' => $superAdmin
                ? ['nullable', 'required_unless:role,super_admin', 'prohibited_if:role,super_admin', Rule::exists('workspaces', 'id')]
                : ['prohibited'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['nullable', 'string', 'min:10'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'client_ids' => ['array'],
            'client_ids.*' => ['integer', Rule::exists('clients', 'id')->where('workspace_id', $this->targetWorkspace()?->id)],
        ];
    }

    public function targetWorkspace(): ?Workspace
    {
        if ($this->user()->isSuperAdmin()) {
            return Workspace::query()->find($this->integer('workspace_id'));
        }

        return $this->attributes->get('workspace');
    }
}
