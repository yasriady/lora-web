<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Telemetry extends Model
{
    public const UPDATED_AT = null;
    protected $table = 'telemetry';
    protected $fillable = ['gateway_id', 'node_id', 'timestamp', 'temperature', 'humidity', 'battery', 'rssi', 'snr'];

    protected function casts(): array
    {
        return ['timestamp' => 'datetime', 'created_at' => 'datetime', 'temperature' => 'decimal:2', 'humidity' => 'decimal:2', 'battery' => 'decimal:3', 'snr' => 'decimal:2', 'rssi' => 'integer'];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id', 'gateway_id');
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id', 'node_id');
    }
}
