<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTabletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tabletId = $this->route('tablet')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('tablets', 'slug')->ignore($tabletId)],
            'location' => ['nullable', 'string', 'max:255'],
            'featured_product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_discontinued', false)],
            'recommendation_ids' => ['required', 'array', 'min:1', 'max:30'],
            'recommendation_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('products', 'id')->where('is_discontinued', false),
                Rule::notIn([$this->integer('featured_product_id')]),
            ],
            'activation_code' => ['nullable', 'string', 'min:6', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
