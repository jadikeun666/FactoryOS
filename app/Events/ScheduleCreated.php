<?php

namespace App\Events;

use App\Models\Schedule;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * ScheduleCreated — di-dispatch setelah JobShopSchedulerService::run() sukses.
 * @see docs/architecture.md § Events & Listeners
 *
 * CATATAN: event ini sebelumnya didokumentasikan di docs/architecture.md
 * tapi tidak pernah benar-benar dibuat (diverifikasi via `find app/Events`
 * sesi 2026-07-19 — kosong). Dibangun sesi ini untuk memicu MRP run
 * otomatis (docs/engineering-rules.md § 9: "run Schedule dulu → baru run
 * MRP"). Listener LogScheduleActivity (disebut di docs/architecture.md)
 * SENGAJA tidak dibuat sesi ini — di luar scope Bagian B, hanya
 * TriggerMrpRunListener yang didaftarkan.
 */
class ScheduleCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Schedule $schedule,
    ) {
    }
}