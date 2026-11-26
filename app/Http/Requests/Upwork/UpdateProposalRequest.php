<?php

namespace App\Http\Requests\Upwork;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-upwork') ?? false;
    }

    public function rules(): array
    {
        return [
            'bid_amount' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:1000000'],
            'cover_letter' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'screening_answers' => ['sometimes', 'array', 'max:20'],
            'screening_answers.*.question' => ['required', 'string', 'max:1000'],
            'screening_answers.*.answer' => ['nullable', 'string', 'max:3000'],
            'target_send_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
