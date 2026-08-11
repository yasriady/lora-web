<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TelemetryReading extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'telemetry_id',
        'gateway_id',
        'node_id',
        'timestamp',
        'metric_key',
        'metric_value',
        'unit',
    ];

    protected function casts(): array
    {
        return [
            'timestamp' => 'datetime',
            'created_at' => 'datetime',
            'metric_value' => 'decimal:6',
        ];
    }

    public function telemetry(): BelongsTo
    {
        return $this->belongsTo(Telemetry::class);
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id', 'node_id');
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id', 'gateway_id');
    }
}
