<?php

namespace App\Http\Controllers\Auth;

use App\Enums\ActivityEvent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Support\Routing\HomePath;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthenticatedSessionController extends Controller
{
    private const NO_WORKSPACE_MESSAGE = 'Your account is not assigned to an active workspace. Please contact your administrator.';

    public function home(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        return $this->redirectHome($request, $user);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return $this->redirectHome($request, $request->user());
        }

        return view('welcome', ['page' => 'login']);
    }

    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember_me' => ['nullable'],
        ]);

        $key = strtolower($credentials['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', [
                    'seconds' => RateLimiter::availableIn($key),
                    'minutes' => ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! $user->is_active || ! Hash::check($credentials['password'], $user->password)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        RateLimiter::clear($key);

        if (HomePath::for($user) === null) {
            throw ValidationException::withMessages(['email' => self::NO_WORKSPACE_MESSAGE]);
        }

        Auth::login($user, $request->boolean('remember_me'));
        $request->session()->regenerate();
        $user->forceFill(['last_login' => now()])->save();

        $activity->log(ActivityEvent::UserLogin, $user->workspace, $user, $user, ['name' => $user->name]);

        return redirect()->to($this->intendedPath($request, $user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function redirectHome(Request $request, User $user): RedirectResponse
    {
        $home = HomePath::for($user);

        if ($home) {
            return redirect($home);
        }

        $this->destroy($request);

        return redirect()->route('login')->withErrors(['email' => self::NO_WORKSPACE_MESSAGE]);
    }

    /**
     * Only honour an intended URL inside an area the user can actually open.
     */
    private function intendedPath(Request $request, User $user): string
    {
        $home = HomePath::for($user);
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '' || $user->isSuperAdmin()) {
            return is_string($intended) && $intended !== '' ? $intended : $home;
        }

        $path = parse_url($intended, PHP_URL_PATH) ?: '/';

        return str_starts_with($path, '/'.$user->workspace->slug.'/') ? $intended : $home;
    }
}
