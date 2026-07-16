<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDowntimeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi sesungguhnya (log belum validated + creator/admin)
        // didelegasikan ke ProductionLogPolicy::update() milik parent log,
        // dipanggil eksplisit di DowntimeController.
        return true;
    }

    public function rules(): array
    {
        return [
            'reason_category'  => ['required', 'string', 'in:breakdown,setup,material,operator,other'],
            'reason_detail'    => ['nullable', 'string', 'max:255'],
            'duration_minutes' => ['required', 'numeric', 'min:0.01'],
            'started_at'       => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason_category.required' => 'Kategori downtime wajib dipilih.',
            'reason_category.in'       => 'Kategori downtime tidak valid.',
            'duration_minutes.required' => 'Durasi downtime wajib diisi.',
            'duration_minutes.min'      => 'Durasi downtime harus lebih besar dari 0.',
        ];
    }
}