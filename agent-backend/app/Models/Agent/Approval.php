<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class Approval extends Model
{
    protected $table = 'agent_approvals';
    protected $guarded = ['id'];
    protected function casts(): array { return ['approved_at' => 'datetime', 'invalidated_at' => 'datetime']; }
    public $timestamps = false;
}
