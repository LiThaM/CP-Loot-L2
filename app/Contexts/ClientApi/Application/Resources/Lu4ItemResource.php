<?php

namespace App\Contexts\ClientApi\Application\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Lu4ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'category' => $this->category,
            'grade' => $this->grade,
            'icon_name' => $this->icon_name,
            'icon_url' => $this->icon_name
                ? 'https://resources.elmorelab.com/images/'.$this->icon_name.'.jpg'
                : null,
            'source' => $this->source,
            'chronicle' => $this->chronicle,
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
