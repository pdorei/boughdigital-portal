<?php

namespace App\Http\Controllers;

use App\Enums\ActivityEvent;
use App\Services\Audit\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private ActivityLogger $activity) {}

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'avatar_url' => ['nullable', 'url', 'max:2048'],
        ]);

        $user->update($data);

        if ($user->wasChanged()) {
            $this->activity->log(ActivityEvent::UserProfileUpdated, $user->workspace, $user, $user, [
                'name' => $user->name,
                'fields' => array_values(array_diff(array_keys($user->getChanges()), ['updated_at'])),
            ]);
        }

        return back()->with('status', 'Profile updated.');
    }

    public function password(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->update(['password' => $data['password']]);

        // Invalidates every other session and refreshes this device's remember-me cookie.
        Auth::logoutOtherDevices($data['password']);
        $user->tokens()->delete();

        $this->activity->log(ActivityEvent::UserPasswordChanged, $user->workspace, $user, $user, ['name' => $user->name]);

        return back()->with('status', 'Password changed. Other sessions have been signed out.');
    }
}
