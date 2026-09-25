<?php

namespace App\Http\Requests\Users;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $member = $this->member();

        if (! $this->user()->can('update', $member)) {
            return false;
        }

        $role = UserRole::tryFrom((string) $this->input('role', $member->role->value));

        return $this->user()->isSuperAdmin()
            || ($role !== null && $this->user()->can('assignRole', [User::class, $role, $this->targetWorkspace()]));
    }

    public function rules(): array
    {
        $member = $this->member();
        $lockedCredentials = ! $this->user()->can('changeCredentials', $member);

        return [
            'workspace_id' => $this->user()->isSuperAdmin() ? ['sometimes', 'nullable', Rule::exists('workspaces', 'id')] : ['prohibited'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => [Rule::prohibitedIf($lockedCredentials && $this->input('email', $member->email) !== $member->email), 'sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($member)],
            'password' => [Rule::prohibitedIf($lockedCredentials), 'nullable', 'string', 'min:10'],
            'role' => ['sometimes', 'required', Rule::enum(UserRole::class)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'client_ids' => ['array'],
            'client_ids.*' => ['integer', Rule::exists('clients', 'id')->where('workspace_id', $this->targetWorkspace()?->id)],
        ];
    }

    public function member(): User
    {
        return $this->route('user');
    }

    public function targetWorkspace(): ?Workspace
    {
        if (! $this->user()->isSuperAdmin()) {
            return $this->attributes->get('workspace');
        }

        $workspaceId = $this->has('workspace_id') ? $this->integer('workspace_id') : $this->member()->workspace_id;

        return Workspace::query()->find($workspaceId);
    }
}
