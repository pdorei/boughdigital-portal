<?php

/*
| Upwork proposal automation. Only Upwork's official GraphQL API with OAuth2 is
| used: no scraping, no browser automation, and proposals are never submitted
| automatically. App credentials come from the environment; the connected
| account's tokens are stored encrypted in platform_integrations.
*/
return [

    // "graphql" calls the real API; "fake" reads fixtures so the pipeline can
    // run before the API key is approved.
    'driver' => env('UPWORK_DRIVER', 'fake'),

    'client_id' => env('UPWORK_CLIENT_ID'),
    'client_secret' => env('UPWORK_CLIENT_SECRET'),
    'redirect_uri' => env('UPWORK_REDIRECT_URI'),

    'authorize_url' => 'https://www.upwork.com/ab/account-security/oauth2/authorize',
    'token_url' => 'https://www.upwork.com/api/v3/oauth2/token',
    'graphql_url' => 'https://api.upwork.com/graphql',
    'job_url' => 'https://www.upwork.com/jobs/',

    'fixtures_path' => database_path('fixtures/upwork/jobs.json'),

    'timeout' => 30,

    // Each search call counts towards Upwork's limits; a run stops at this cap.
    'max_api_calls_per_run' => (int) env('UPWORK_MAX_API_CALLS_PER_RUN', 60),

    // Business timezone for "today" (Connects cap, overview counts).
    'timezone' => env('UPWORK_TIMEZONE', 'Australia/Sydney'),

    'queue' => env('UPWORK_QUEUE', 'upwork'),
];
