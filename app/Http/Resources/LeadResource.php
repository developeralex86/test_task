<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'company'    => $this->company,
            'status'     => $this->status,
            'created_at' => $this->created_at->toIso8601String(),

            // Nested object — only when eager-loaded
            'assigned_to' => $this->whenLoaded('assignedUser', fn() => [
                'id'    => $this->assignedUser->id,
                'name'  => $this->assignedUser->name,
                'email' => $this->assignedUser->email,
            ]),

            // Audit trail — only when explicitly loaded
            'activity' => $this->whenLoaded('activities', fn() =>
                $this->activities->map(fn($a) => [
                    'action' => $a->action,
                    'from'   => $a->fromUser?->name ?? 'Unassigned',
                    'to'     => $a->toUser?->name,
                    'at'     => $a->occurred_at->toIso8601String(),
                ])
            ),
        ];
    }
}
