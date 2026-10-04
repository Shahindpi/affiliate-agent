<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Content extends Model
{
    protected $table = 'agent_contents';
    protected $guarded = ['id'];
    protected $hidden = ['checkpoint'];
    protected function casts(): array { return ['checkpoint' => 'array', 'locks' => 'array']; }
    public function versions() { return $this->hasMany(ContentVersion::class, 'content_id')->orderByDesc('number'); }
    public function currentVersion() { return $this->belongsTo(ContentVersion::class, 'current_version_id'); }
    public function feedback() { return $this->hasMany(Feedback::class, 'content_id')->latest(); }
    public function approvals() { return $this->hasMany(Approval::class, 'content_id')->latest('approved_at'); }
    public function publications() { return $this->hasMany(Publication::class, 'content_id')->latest(); }
    public function brand() { return $this->belongsTo(\App\Models\Brand::class); }
    public function product() { return $this->belongsTo(\App\Models\AffiliateProduct::class, 'affiliate_product_id'); }
    public function campaign() { return $this->belongsTo(Campaign::class, 'campaign_id'); }
}
