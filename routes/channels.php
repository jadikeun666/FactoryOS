<?php

use App\Models\WorkCenter;
use Illuminate\Support\Facades\Broadcast;

/**
 * @see docs/architecture.md § Channel Authorization
 */
Broadcast::channel('work-center.{workCenterId}', function ($user, $workCenterId) {
    $workCenter = WorkCenter::find($workCenterId);

    if (! $workCenter) {
        return false;
    }

    return $user->can('view', $workCenter);
});