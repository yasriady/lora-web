<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Gateway extends Model
{
    protected $fillable = ['gateway_id', 'api_token', 'name', 'location', 'description', 'enabled', 'last_seen'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_seen' => 'datetime'];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class, 'gateway_id', 'gateway_id');
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class, 'gateway_id', 'gateway_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(GatewayLog::class, 'gateway_id', 'gateway_id');
    }
}
