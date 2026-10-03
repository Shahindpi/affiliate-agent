<?php

namespace App\Models;

use App\Support\ImageUrl;
use Illuminate\Database\Eloquent\Model;

class HeroBanner extends Model
{
    protected $fillable = ['heading', 'description', 'background_image', 'cta_text', 'cta_url', 'enabled', 'sort_order'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'sort_order' => 'integer'];
    }

    public function publicData(): array
    {
        return [
            'id' => $this->id,
            'heading' => $this->heading,
            'description' => $this->description,
            'background_image' => ImageUrl::make($this->background_image),
            'cta_text' => $this->cta_text,
            'cta_url' => $this->cta_url,
        ];
    }
}
