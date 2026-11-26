<?php

namespace App\Jobs\Upwork;

use App\Services\Upwork\UpworkJobService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExpireUpworkItems implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct()
    {
        $this->onQueue(config('upwork.queue'));
    }

    public function handle(UpworkJobService $jobs): void
    {
        $jobs->expireStale();
    }
}
