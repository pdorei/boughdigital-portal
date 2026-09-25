<?php

namespace App\Http\Controllers;

use App\Services\DateTime\DateTimePreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserDateTimePreferenceController extends Controller
{
    public function update(Request $request, DateTimePreferenceService $preferences): RedirectResponse
    {
        $data = $request->validate([
            'timezone' => ['nullable', 'string', Rule::in(collect($preferences->options()['timezone_options'])->pluck('value')->all())],
            'date_format' => ['nullable', 'string', Rule::in(array_keys(DateTimePreferenceService::DATE_FORMATS))],
            'datetime_format' => ['nullable', 'string', Rule::in(array_keys(DateTimePreferenceService::DATETIME_FORMATS))],
        ]);

        $request->user()->forceFill([
            'timezone' => ($data['timezone'] ?? null) ?: null,
            'date_format' => ($data['date_format'] ?? null) ?: null,
            'datetime_format' => ($data['datetime_format'] ?? null) ?: null,
        ])->save();

        return back()->with('status', 'Date and time preferences updated.');
    }
}
