<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gateway_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('gateway_id', 64)->nullable()->index();
            $table->string('level', 20)->index();
            $table->string('event', 100)->index();
            $table->text('message');
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gateway_logs');
    }
};
