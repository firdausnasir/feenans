<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttachmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attachments' => ['required', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,gif,webp'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attachments.required' => 'Please choose at least one attachment.',
            'attachments.array' => 'Attachments must be uploaded as a list of files.',
            'attachments.max' => 'You may upload up to 10 attachments at a time.',
            'attachments.*.file' => 'Each attachment must be a valid file.',
            'attachments.*.max' => 'Each attachment must not be larger than 5 MB.',
            'attachments.*.mimes' => 'Attachments must be a PDF or image file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->hasFile('file') && ! $this->hasFile('attachments')) {
            $this->merge(['attachments' => [$this->file('file')]]);
        }
    }
}
