<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id', 64)->unique();
            $table->char('api_token', 64)->unique();
            $table->string('name', 120);
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('enabled')->default(true)->index();
            $table->timestamp('last_seen')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateways');
    }
};
