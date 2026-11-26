<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Upwork\Client\UpworkAuthenticationException;
use App\Services\Upwork\UpworkOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Upwork OAuth2 consent. The state value is kept in the session and checked
 * on the callback so a forged callback cannot attach another account.
 */
class UpworkOAuthController extends Controller
{
    private const RETURN_TO = '/admin/connections';

    public function __construct(private UpworkOAuthService $oauth) {}

    public function connect(Request $request): RedirectResponse
    {
        $this->authorize('manage-platform-settings');

        if ($this->oauth->usesFixtures()) {
            $this->oauth->connectFixtures($request->user());

            return redirect(self::RETURN_TO)->with('status', 'Upwork connected in fixture mode (no API calls).');
        }

        if (! $this->oauth->appConfigured()) {
            return redirect(self::RETURN_TO)->with('status', 'Set UPWORK_CLIENT_ID and UPWORK_CLIENT_SECRET before connecting Upwork.');
        }

        $state = Str::random(40);
        $request->session()->put('upwork_oauth_state', $state);

        return redirect()->away($this->oauth->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorize('manage-platform-settings');

        $expected = $request->session()->pull('upwork_oauth_state');

        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect(self::RETURN_TO)->with('status', 'Upwork connection failed: the request could not be verified. Try again.');
        }

        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect(self::RETURN_TO)->with('status', 'Upwork connection was cancelled.');
        }

        try {
            $this->oauth->completeAuthorization((string) $request->query('code'), $request->user());
        } catch (UpworkAuthenticationException $exception) {
            report($exception);

            return redirect(self::RETURN_TO)->with('status', 'Upwork connection failed: '.$exception->getMessage());
        }

        return redirect(self::RETURN_TO)->with('status', 'Upwork connected successfully.');
    }
}
