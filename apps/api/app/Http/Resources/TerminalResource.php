<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TerminalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'terminal_uuid' => $this->terminal_uuid,
            'name' => $this->name,
            'device_name' => $this->device_name,
            'is_active' => $this->is_active,
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
