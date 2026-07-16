<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Semua user terautentikasi boleh apply — tidak ada Policy khusus
        // yang didefinisikan untuk aksi ini di docs/architecture.md.
        // Sesuaikan jika ternyata hanya role tertentu (mis. PPIC) yang boleh.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'schedule_id' => ['required', 'integer', 'exists:schedules,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'schedule_id.required' => 'Schedule yang akan diterapkan wajib dipilih.',
            'schedule_id.exists' => 'Schedule tidak ditemukan.',
        ];
    }
}