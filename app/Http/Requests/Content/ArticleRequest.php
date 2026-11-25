<?php

namespace App\Http\Requests\Content;

use App\Enums\ArticleStatus;
use App\Enums\SearchIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules(creating: $this->isMethod('POST'));
    }

    /**
     * Pillar, writer and calendar entry IDs are checked against the client by ArticleService.
     */
    public static function fieldRules(bool $creating): array
    {
        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'content_pillar_id' => ['nullable', 'integer'],
            'assigned_writer_id' => ['nullable', 'integer'],
            'calendar_entry_id' => [$creating ? 'nullable' : 'prohibited', 'integer'],
            'status' => ['sometimes', Rule::enum(ArticleStatus::class)],
            'primary_keyword' => ['nullable', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array', 'list', 'max:20'],
            'secondary_keywords.*' => ['nullable', 'string', 'max:100'],
            'search_intent' => ['nullable', Rule::enum(SearchIntent::class)],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'canonical_url' => ['nullable', 'url:http,https', 'max:2048'],
            'content_body' => ['nullable', 'string', 'max:200000'],
            'excerpt' => ['nullable', 'string', 'max:2000'],
            'word_count' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'published_url' => ['nullable', 'url:http,https', 'max:2048'],
            'published_at' => ['nullable', 'date'],
            'tags' => ['nullable', 'array', 'list', 'max:30'],
            'tags.*' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function attributes(): array
    {
        return ['secondary_keywords.*' => 'keyword', 'tags.*' => 'tag', 'canonical_url' => 'canonical URL', 'published_url' => 'published URL'];
    }
}
