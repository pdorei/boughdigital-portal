<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesWorkspaceClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ArticleScoreRequest;
use App\Http\Resources\ArticleScoreResource;
use App\Models\Article;
use App\Services\Content\ArticleService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ArticleScoreController extends Controller
{
    use ResolvesWorkspaceClient;

    public function __construct(private ArticleService $articles) {}

    public function index(string $workspace, int $client, int $article): AnonymousResourceCollection
    {
        return ArticleScoreResource::collection($this->articles->scores($this->article('view', $client, $article)));
    }

    public function store(ArticleScoreRequest $request, string $workspace, int $client, int $article): ArticleScoreResource
    {
        $article = $this->article('score', $client, $article);

        return new ArticleScoreResource($this->articles->addScore($article, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $article, int $score): ArticleScoreResource
    {
        return new ArticleScoreResource($this->articles->findScore($this->article('view', $client, $article), $score));
    }

    private function article(string $ability, int $clientId, int $id): Article
    {
        $article = $this->articles->findForClient($this->workspaceClient($clientId), $id);
        $this->authorize($ability, $article);

        return $article;
    }
}
