<?php

use App\Models\WorkCenter;
use App\Models\Shift;
use App\Models\ProductionLog;
use App\Models\DowntimeEvent;

$wc = WorkCenter::factory()->create();
$shift = Shift::factory()->create();

$log1 = ProductionLog::factory()->create(['work_center_id' => $wc->id, 'shift_id' => $shift->id, 'log_date' => '2026-07-10']);
$log2 = ProductionLog::factory()->create(['work_center_id' => $wc->id, 'shift_id' => $shift->id, 'log_date' => '2026-07-11']);

echo "log1 id={$log1->id} work_center_id={$log1->work_center_id} log_date={$log1->log_date}\n";
echo "log2 id={$log2->id} work_center_id={$log2->work_center_id} log_date={$log2->log_date}\n";

DowntimeEvent::create(['production_log_id' => $log1->id, 'reason_category' => 'breakdown', 'reason_detail' => null, 'duration_minutes' => 480, 'started_at' => '2026-07-10 08:00:00']);
DowntimeEvent::create(['production_log_id' => $log1->id, 'reason_category' => 'setup', 'reason_detail' => null, 'duration_minutes' => 320, 'started_at' => '2026-07-10 10:00:00']);
DowntimeEvent::create(['production_log_id' => $log2->id, 'reason_category' => 'material', 'reason_detail' => null, 'duration_minutes' => 150, 'started_at' => '2026-07-11 08:00:00']);
DowntimeEvent::create(['production_log_id' => $log2->id, 'reason_category' => 'operator', 'reason_detail' => null, 'duration_minutes' => 80, 'started_at' => '2026-07-11 09:00:00']);
DowntimeEvent::create(['production_log_id' => $log2->id, 'reason_category' => 'other', 'reason_detail' => null, 'duration_minutes' => 33, 'started_at' => '2026-07-11 10:00:00']);

echo "total downtime events: " . DowntimeEvent::count() . "\n";
echo DowntimeEvent::pluck('reason_category', 'production_log_id')->toJson() . "\n";

$service = new \App\Services\OEE\DowntimeAnalysisService();
$result = $service->paretoDowntime(\Illuminate\Support\Carbon::parse('2026-07-10'), \Illuminate\Support\Carbon::parse('2026-07-11'));

echo "paretoDowntime result:\n";
print_r($result);