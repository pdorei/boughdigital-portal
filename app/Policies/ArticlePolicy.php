<?php

namespace App\Policies;

use App\Models\Article;
use App\Models\Client;
use App\Models\User;
use App\Policies\Concerns\AuthorizesClientContent;

class ArticlePolicy
{
    use AuthorizesClientContent;

    public function viewAny(User $user, Client $client): bool
    {
        return $this->canViewClient($user, $client);
    }

    public function view(User $user, Article $article): bool
    {
        return $this->canViewClient($user, $article->client);
    }

    public function create(User $user, Client $client): bool
    {
        return $this->canManageClient($user, $client);
    }

    /**
     * Writers may edit the articles assigned to them.
     */
    public function update(User $user, Article $article): bool
    {
        return $this->canManageClient($user, $article->client)
            || ((int) $article->assigned_writer_id === (int) $user->id && $this->view($user, $article));
    }

    public function delete(User $user, Article $article): bool
    {
        return $this->canDeleteFromClient($user, $article->client);
    }

    public function score(User $user, Article $article): bool
    {
        return $this->canManageClient($user, $article->client);
    }
}
