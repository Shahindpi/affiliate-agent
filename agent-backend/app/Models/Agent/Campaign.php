<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'agent_campaigns';
    protected $guarded = ['id'];
    protected function casts(): array { return ['enabled' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date']; }
    public function brand() { return $this->belongsTo(\App\Models\Brand::class); }
    public function product() { return $this->belongsTo(\App\Models\AffiliateProduct::class, 'affiliate_product_id'); }
}
