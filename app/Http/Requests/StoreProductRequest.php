<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * No auth required for development phase.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku'             => ['required', 'string', 'max:100', 'unique:products,sku'],
            'name'            => ['required', 'string', 'max:255'],
            'price'           => ['nullable', 'numeric', 'min:0'],
            'stock'           => ['nullable', 'integer', 'min:0'],
            'zone_id'         => ['nullable', 'integer', 'exists:zones,id'],
            'image'           => ['nullable', 'string', 'max:8192'],
            'material'        => ['nullable', 'string', 'max:100'],
            'description'     => ['nullable', 'string'],
            'is_featured'     => ['nullable', 'boolean'],
            'is_discontinued' => ['nullable', 'boolean'],
            'variants'        => ['nullable', 'array'],
            'variants.*.sku'  => ['required_with:variants', 'string', 'max:100'],
            'variants.*.name' => ['nullable', 'string', 'max:255'],
            'variants.*.color' => ['nullable', 'string', 'max:100'],
            'variants.*.size' => ['nullable', 'string', 'max:100'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
