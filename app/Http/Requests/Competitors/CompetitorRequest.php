<?php

namespace App\Http\Requests\Competitors;

use App\Enums\CompetitorTier;
use App\Enums\CompetitorType;
use App\Models\ClientCompetitor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompetitorRequest extends FormRequest
{
    public function rules(): array
    {
        return self::fieldRules(creating: $this->isMethod('POST'));
    }

    /**
     * Domain format, per-client uniqueness and the competitor limit are
     * enforced by CompetitorService so every interface gets the same rules.
     */
    public static function fieldRules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'competitor_name' => [$required, 'string', 'max:255'],
            'competitor_domain' => [$required, 'string', 'max:255'],
            'tier' => [$required, Rule::enum(CompetitorTier::class)],
            'competitor_type' => ['nullable', Rule::enum(CompetitorType::class)],
            'description' => ['nullable', 'string', 'max:5000'],
            'industry' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'contact' => ['nullable', 'array:'.implode(',', ClientCompetitor::CONTACT_FIELDS)],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.phone' => ['nullable', 'string', 'max:50'],
            'social_media' => ['nullable', 'array:'.implode(',', ClientCompetitor::SOCIAL_NETWORKS)],
            'social_media.*' => ['nullable', 'url:http,https', 'max:255'],
            'keywords' => ['nullable', 'array', 'list', 'max:'.ClientCompetitor::MAX_KEYWORDS],
            'keywords.*' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'competitor_name' => 'competitor name',
            'competitor_domain' => 'website / domain',
            'competitor_type' => 'competitor type',
            'contact.email' => 'contact email',
            'social_media.*' => 'profile URL',
            'keywords.*' => 'keyword',
        ];
    }
}
