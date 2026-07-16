<?php

namespace App\Events;

use App\Models\OeeSnapshot;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * @see docs/oee-formulas.md § Real-time Update Flow (Soketi)
 * @see docs/architecture.md § WebSocket Flow
 *
 * Broadcast ke channel `work-center.{work_center_id}`, event name `oee.updated`.
 * Vue listener: Echo.private('work-center.X').listen('OeeUpdated', callback)
 */
class OeeUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly OeeSnapshot $snapshot)
    {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('work-center.' . $this->snapshot->work_center_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'oee.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'snapshot' => [
                'work_center_id' => $this->snapshot->work_center_id,
                'log_date'       => $this->snapshot->log_date->toDateString(),
                'shift_id'       => $this->snapshot->shift_id,
                'availability'   => (string) $this->snapshot->availability,
                'performance'    => (string) $this->snapshot->performance,
                'quality'        => (string) $this->snapshot->quality,
                'oee'            => (string) $this->snapshot->oee,
                'computed_at'    => $this->snapshot->computed_at->toIso8601String(),
            ],
        ];
    }
}