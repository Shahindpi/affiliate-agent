<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class PinterestDestination extends Model
{
    protected $table = 'agent_pinterest_destinations';
    protected $guarded = ['id'];
    public function account() { return $this->belongsTo(SocialAccount::class, 'social_account_id'); }
}
