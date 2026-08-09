<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    protected $fillable = ['gateway_id', 'node_id', 'name', 'location', 'description', 'enabled', 'last_seen'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'last_seen' => 'datetime'];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id', 'gateway_id');
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class, 'node_id', 'node_id');
    }
}
