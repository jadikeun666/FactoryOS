<?php

use App\Jobs\CheckReorderAlertsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sesuai docs/architecture.md § Laravel Scheduler dan
// docs/inventory.md § Reorder Alert Logic: cek reorder alerts setiap
// hari pukul 06:00. Dijalankan via CheckReorderAlertsJob (queued,
// sudah ada dari sesi 2026-07-20/22) -- sengaja BARU diaktifkan sesi
// ini (item 6, claude.md § Utang Teknis) setelah persetujuan eksplisit
// untuk pendekatan Supervisor + `schedule:work`.
Schedule::job(new CheckReorderAlertsJob())->dailyAt('06:00');