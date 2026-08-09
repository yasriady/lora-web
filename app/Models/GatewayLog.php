<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewayLog extends Model
{
    public const UPDATED_AT = null;
    protected $fillable = ['gateway_id', 'level', 'event', 'message'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
