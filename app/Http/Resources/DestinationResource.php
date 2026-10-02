<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DestinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'region' => $this->region,
            'famous_for' => $this->famous_for,
            'best_time' => $this->best_time,
            'short_description' => $this->short_description,
            'places_to_visit' => $this->places_to_visit,
            'things_to_do' => $this->things_to_do,
            'cover_image_url' => $this->cover_image ? asset(img($this->cover_image)) : null,
        ];
    }
}
