<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_center_id'           => ['required', 'integer', 'exists:work_centers,id'],
            'shift_id'                 => ['required', 'integer', 'exists:shifts,id'],
            'log_date'                 => ['required', 'date'],
            'planned_minutes'          => ['required', 'numeric', 'min:0.01'],
            'downtime_minutes'         => ['nullable', 'numeric', 'min:0'],
            'actual_output'            => ['required', 'numeric', 'min:0.0001'],
            'good_output'              => ['required', 'numeric', 'min:0', 'lte:actual_output'],
            'ideal_cycle_time_minutes' => ['required', 'numeric', 'min:0.000001'],

            // Downtime events opsional, langsung di form yang sama (FR-04/US-07)
            'downtime_events'                       => ['nullable', 'array'],
            'downtime_events.*.reason_category'     => ['required_with:downtime_events', 'string', 'in:breakdown,setup,material,operator,other'],
            'downtime_events.*.reason_detail'       => ['nullable', 'string', 'max:255'],
            'downtime_events.*.duration_minutes'    => ['required_with:downtime_events', 'numeric', 'min:0.01'],
            'downtime_events.*.started_at'          => ['required_with:downtime_events', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'work_center_id.required'   => 'Mesin wajib dipilih.',
            'shift_id.required'         => 'Shift wajib dipilih.',
            'log_date.required'         => 'Tanggal log wajib diisi.',
            'planned_minutes.required'  => 'Planned minutes wajib diisi.',
            'planned_minutes.min'       => 'Planned minutes harus lebih besar dari 0.',
            'actual_output.required'    => 'Actual output wajib diisi.',
            'actual_output.min'         => 'Actual output harus lebih besar dari 0.',
            'good_output.lte'           => 'Good output tidak boleh melebihi actual output.',
            'ideal_cycle_time_minutes.required' => 'Ideal cycle time wajib diisi.',
            'downtime_events.*.reason_category.in' => 'Kategori downtime tidak valid.',
        ];
    }
}