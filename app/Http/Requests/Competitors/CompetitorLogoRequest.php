<?php

namespace App\Http\Requests\Competitors;

use Illuminate\Foundation\Http\FormRequest;

class CompetitorLogoRequest extends FormRequest
{
    public function rules(): array
    {
        // Same constraints as workspace logos; SVG is excluded because it can carry script.
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }
}
