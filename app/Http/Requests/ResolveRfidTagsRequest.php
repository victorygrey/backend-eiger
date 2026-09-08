<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResolveRfidTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'uids' => ['required', 'array', 'min:1', 'max:500'],
            'uids.*' => ['required', 'string', 'max:100', 'regex:/\A[0-9A-Fa-f]+\z/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->input('uids'))) {
            $this->merge([
                'uids' => array_map(
                    fn ($uid) => strtoupper(str_replace(['-', ':', ' '], '', trim((string) $uid))),
                    $this->input('uids'),
                ),
            ]);
        }
    }

    /** @return list<string> */
    public function uids(): array
    {
        return array_values(array_unique($this->validated('uids')));
    }
}
