<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesWorkspaceClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Content\ArticleRequest;
use App\Http\Resources\ArticleResource;
use App\Models\Article;
use App\Services\Content\ArticleService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ArticleController extends Controller
{
    use ResolvesWorkspaceClient;

    public function __construct(private ArticleService $articles) {}

    public function index(Request $request, string $workspace, int $client): AnonymousResourceCollection
    {
        $client = $this->workspaceClient($client);
        $this->authorize('viewAny', [Article::class, $client]);

        $filters = $request->only(['search', 'status', 'writer', 'pillar', 'page', 'per_page']);

        return ArticleResource::collection($this->articles->list($client, $request->user(), $filters))->additional(['meta' => [
            'can_create' => $request->user()->can('create', [Article::class, $client]),
        ]]);
    }

    public function store(ArticleRequest $request, string $workspace, int $client): ArticleResource
    {
        $client = $this->workspaceClient($client);
        $this->authorize('create', [Article::class, $client]);

        return new ArticleResource($this->articles->create($client, $request->user(), $request->validated()));
    }

    public function show(string $workspace, int $client, int $article): ArticleResource
    {
        return new ArticleResource($this->article('view', $client, $article));
    }

    public function update(ArticleRequest $request, string $workspace, int $client, int $article): ArticleResource
    {
        $article = $this->article('update', $client, $article);

        return new ArticleResource($this->articles->update($article, $request->user(), $request->validated()));
    }

    public function destroy(Request $request, string $workspace, int $client, int $article): Response
    {
        $this->articles->delete($this->article('delete', $client, $article), $request->user());

        return response()->noContent();
    }

    private function article(string $ability, int $clientId, int $id): Article
    {
        $article = $this->articles->findForClient($this->workspaceClient($clientId), $id);
        $this->authorize($ability, $article);

        return $article;
    }
}
