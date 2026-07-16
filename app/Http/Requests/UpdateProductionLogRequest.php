<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Otorisasi detail (immutability + creator/admin) didelegasikan ke
        // ProductionLogPolicy, dipanggil eksplisit di controller.
        return true;
    }

    public function rules(): array
    {
        return [
            'shift_id'                 => ['sometimes', 'integer', 'exists:shifts,id'],
            'log_date'                 => ['sometimes', 'date'],
            'planned_minutes'          => ['sometimes', 'numeric', 'min:0.01'],
            'downtime_minutes'         => ['sometimes', 'numeric', 'min:0'],
            'actual_output'            => ['sometimes', 'numeric', 'min:0.0001'],
            'good_output'              => ['sometimes', 'numeric', 'min:0', 'lte:actual_output'],
            'ideal_cycle_time_minutes' => ['sometimes', 'numeric', 'min:0.000001'],
        ];
    }

    public function messages(): array
    {
        return [
            'good_output.lte' => 'Good output tidak boleh melebihi actual output.',
        ];
    }
}