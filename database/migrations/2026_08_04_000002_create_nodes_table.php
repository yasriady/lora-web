<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id', 64)->index();
            $table->string('node_id', 64)->unique();
            $table->string('name', 120);
            $table->string('node_type', 64)->default('env_basic')->index();
            $table->json('metrics_schema')->nullable();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_seen')->nullable()->index();
            $table->timestamps();
            $table->index(['gateway_id', 'node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nodes');
    }
};
