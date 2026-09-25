<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Client;
use App\Models\Task;
use App\Models\Workspace;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $this->call(DevelopmentUserSeeder::class);

        $workspace = Workspace::query()->where('slug', 'bough')->sole();

        $clients = collect([
            ['name' => 'Acorn Legal', 'slug' => 'acorn-legal', 'website_url' => 'https://example.com'],
            ['name' => 'Canopy Health', 'slug' => 'canopy-health', 'website_url' => 'https://example.org'],
            ['name' => 'Rootline SaaS', 'slug' => 'rootline-saas', 'website_url' => 'https://example.net'],
        ])->map(fn ($data) => Client::query()->firstOrCreate(
            ['workspace_id' => $workspace->id, 'slug' => $data['slug']],
            $data + ['workspace_id' => $workspace->id],
        ));

        foreach ($clients as $client) {
            Task::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'client_id' => $client->id, 'title' => "Review {$client->name} technical SEO"],
                ['status' => TaskStatus::Backlog, 'source' => 'manual', 'priority' => 'normal'],
            );
        }
    }
}
