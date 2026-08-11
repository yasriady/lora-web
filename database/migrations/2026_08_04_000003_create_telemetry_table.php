<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id', 64);
            $table->string('node_id', 64);
            $table->timestamp('timestamp')->index();
            $table->json('metrics')->nullable();
            $table->decimal('temperature', 6, 2)->nullable();
            $table->decimal('humidity', 5, 2)->nullable();
            $table->decimal('battery', 5, 3)->nullable();
            $table->smallInteger('rssi')->nullable();
            $table->decimal('snr', 5, 2)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['gateway_id', 'node_id', 'timestamp']);
            $table->index(['node_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry');
    }
};
