<?php

namespace App\Models\Agent;

use Illuminate\Database\Eloquent\Model;

class SocialAccount extends Model
{
    protected $table = 'agent_social_accounts';
    protected $guarded = ['id'];
    protected $hidden = ['access_token', 'refresh_token'];
    protected function casts(): array { return ['access_token' => 'encrypted', 'refresh_token' => 'encrypted', 'expires_at' => 'datetime', 'last_verified_at' => 'datetime', 'scopes' => 'array', 'metadata' => 'array', 'publishing_enabled' => 'boolean']; }
}
