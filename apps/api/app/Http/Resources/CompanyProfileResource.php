<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class CompanyProfileResource extends JsonResource
{
    public function toArray(
        Request $request
    ): array {
        return [
            'id' => $this->id,

            'name' => $this->name,

            'address' => $this->address,

            'phone' => $this->phone,

            'logo_path' => $this->logo_path,

            'logo_url' => $this->logo_path === null
                ? null
                : Storage::disk('public')
                    ->url(
                        $this->logo_path
                    ),

            'updated_at' => $this->updated_at,
        ];
    }
}
