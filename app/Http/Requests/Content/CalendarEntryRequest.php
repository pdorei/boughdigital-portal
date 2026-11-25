<?php

namespace App\Http\Requests\Content;

use App\Enums\ContentCalendarStatus;
use App\Enums\ContentType;
use App\Enums\SearchIntent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalendarEntryRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules(creating: $this->isMethod('POST'));
    }

    /**
     * Pillar, article and writer IDs are checked against the client by ContentCalendarService.
     */
    public static function fieldRules(bool $creating): array
    {
        return [
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'target_keyword' => ['nullable', 'string', 'max:255'],
            'secondary_keywords' => ['nullable', 'array', 'list', 'max:20'],
            'secondary_keywords.*' => ['nullable', 'string', 'max:100'],
            'content_pillar_id' => ['nullable', 'integer'],
            'article_id' => ['nullable', 'integer'],
            'assigned_writer_id' => ['nullable', 'integer'],
            'status' => ['sometimes', Rule::enum(ContentCalendarStatus::class)],
            'content_type' => ['sometimes', Rule::enum(ContentType::class)],
            'search_intent' => ['nullable', Rule::enum(SearchIntent::class)],
            'due_date' => ['nullable', 'date_format:Y-m-d'],
            'publish_date' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return ['secondary_keywords.*' => 'keyword'];
    }
}
