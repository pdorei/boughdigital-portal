<?php

namespace App\Http\Requests\Content;

use App\Enums\ArticleScoreCategory;
use App\Enums\ArticleScoreType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ArticleScoreRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules();
    }

    public static function fieldRules(): array
    {
        $categories = implode(',', array_column(ArticleScoreCategory::cases(), 'value'));

        return [
            'score_type' => ['required', Rule::enum(ArticleScoreType::class)],
            'overall_score' => ['required', 'integer', 'min:0', 'max:100'],
            'category_scores' => ['nullable', "array:{$categories}"],
            'category_scores.*' => ['nullable', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string', 'max:10000'],
        ];
    }

    public function attributes(): array
    {
        return ['category_scores.*' => 'category score'];
    }
}
