<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Lead;

class LeadAssigned {
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly ?int $fromUserId,
        public readonly int $toUserId,
    ) {}
}
