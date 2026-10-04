<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'agent_settings';
    protected $guarded = ['id'];
    protected function casts(): array { return ['mix' => 'array', 'generation_days' => 'array', 'publishing_slots' => 'array', 'enabled_brand_ids' => 'array', 'enabled_campaign_ids' => 'array', 'generation_enabled' => 'boolean', 'publishing_enabled' => 'boolean']; }
    public static function current(): self { return static::firstOrCreate(['id' => 1], ['timezone' => 'Asia/Dhaka', 'videos_per_day' => 2, 'generation_time' => '06:00', 'horizon_days' => 7, 'duration_seconds' => 25, 'late_policy' => 'next_slot', 'generation_enabled' => false, 'publishing_enabled' => false, 'mix' => ['tutorial' => 30, 'educational' => 25, 'use_case' => 20, 'tips' => 15, 'promotion' => 10], 'generation_days' => [0,1,2,3,4,5,6], 'publishing_slots' => ['09:00', '14:00', '20:00']]); }
}
