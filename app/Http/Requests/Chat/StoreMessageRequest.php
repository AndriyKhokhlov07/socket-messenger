<?php

namespace App\Http\Requests\Chat;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receiver_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::notIn([$this->user()?->id]),
            ],
            'body' => ['nullable', 'string', 'max:4000', 'required_without:attachment'],
            'attachment' => [
                'nullable',
                'file',
                'max:25600',
                'mimes:jpg,jpeg,png,gif,webp,svg,mp4,webm,mov,m4v,pdf,txt,doc,docx,xls,xlsx,zip,rar',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'body' => trim((string) $this->input('body', '')),
        ]);
    }
}
