<?php

namespace App\Models;

use App\Support\NodeMetricCatalog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Node extends Model
{
    protected $fillable = [
        'gateway_id',
        'node_id',
        'name',
        'node_type',
        'metrics_schema',
        'location',
        'description',
        'enabled',
        'last_seen',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_seen' => 'datetime',
            'metrics_schema' => 'array',
        ];
    }

    public function gateway(): BelongsTo
    {
        return $this->belongsTo(Gateway::class, 'gateway_id', 'gateway_id');
    }

    public function telemetry(): HasMany
    {
        return $this->hasMany(Telemetry::class, 'node_id', 'node_id');
    }

    public function readings(): HasMany
    {
        return $this->hasMany(TelemetryReading::class, 'node_id', 'node_id');
    }

    /**
     * @return array<string, array{label: string, unit: ?string, type: string}>
     */
    public function resolvedMetricsSchema(): array
    {
        if (is_array($this->metrics_schema) && $this->metrics_schema !== []) {
            return $this->metrics_schema;
        }

        $presets = NodeMetricCatalog::presets();

        return $presets[$this->node_type]['schema'] ?? [];
    }
}
