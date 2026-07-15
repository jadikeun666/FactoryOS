<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi detail (hanya creator/admin) di-enforce via WorkOrderPolicy,
        // dipanggil eksplisit di WorkOrderController@update.
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
}