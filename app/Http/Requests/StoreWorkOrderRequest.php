<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user yang sudah login boleh membuat WO. Otorisasi lebih detail
        // (mis. berdasarkan role) ditangani di WorkOrderPolicy jika diperlukan.
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'   => ['required', 'integer', 'exists:products,id'],
            'qty'          => ['required', 'numeric', 'min:0.0001'],
            'due_date'     => ['required', 'date', 'after_or_equal:release_date'],
            'priority'     => ['nullable', 'integer', 'min:1', 'max:10'],
            'release_date' => ['nullable', 'date'],
            'notes'        => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required'   => 'Produk wajib dipilih.',
            'product_id.exists'     => 'Produk yang dipilih tidak valid.',
            'qty.required'          => 'Qty wajib diisi.',
            'qty.min'               => 'Qty harus lebih besar dari 0.',
            'due_date.required'     => 'Due date wajib diisi.',
            'due_date.after_or_equal' => 'Due date tidak boleh sebelum release date.',
            'priority.min'          => 'Priority minimal 1 (tertinggi).',
            'priority.max'          => 'Priority maksimal 10 (terendah).',
        ];
    }
}