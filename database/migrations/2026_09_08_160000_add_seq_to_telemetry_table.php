<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telemetry', function (Blueprint $table): void {
            $table->unsignedInteger('seq')->nullable()->after('snr');
            $table->index(['node_id', 'seq']);
        });
    }

    public function down(): void
    {
        Schema::table('telemetry', function (Blueprint $table): void {
            $table->dropIndex(['node_id', 'seq']);
            $table->dropColumn('seq');
        });
    }
};
