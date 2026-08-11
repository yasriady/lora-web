<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table): void {
            if (! Schema::hasColumn('nodes', 'node_type')) {
                $table->string('node_type', 64)->default('env_basic')->after('name')->index();
            }
            if (! Schema::hasColumn('nodes', 'metrics_schema')) {
                $table->json('metrics_schema')->nullable()->after('node_type');
            }
        });

        Schema::table('telemetry', function (Blueprint $table): void {
            if (! Schema::hasColumn('telemetry', 'metrics')) {
                $table->json('metrics')->nullable()->after('timestamp');
            }
        });

        // Make legacy sensor columns nullable for hybrid payloads.
        Schema::table('telemetry', function (Blueprint $table): void {
            $table->decimal('temperature', 6, 2)->nullable()->change();
            $table->decimal('humidity', 5, 2)->nullable()->change();
            $table->decimal('battery', 5, 3)->nullable()->change();
            $table->smallInteger('rssi')->nullable()->change();
            $table->decimal('snr', 5, 2)->nullable()->change();
        });

        Schema::create('telemetry_readings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('telemetry_id')->constrained('telemetry')->cascadeOnDelete();
            $table->string('gateway_id', 64)->index();
            $table->string('node_id', 64)->index();
            $table->timestamp('timestamp')->index();
            $table->string('metric_key', 64)->index();
            $table->decimal('metric_value', 16, 6);
            $table->string('unit', 32)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['node_id', 'metric_key', 'timestamp'], 'telemetry_readings_node_metric_time_idx');
            $table->index(['gateway_id', 'metric_key', 'timestamp'], 'telemetry_readings_gateway_metric_time_idx');
            $table->index(['timestamp', 'metric_key'], 'telemetry_readings_time_metric_idx');
        });

        $this->backfillExistingTelemetry();
        $this->createGrafanaView();
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS grafana_telemetry_readings');
        Schema::dropIfExists('telemetry_readings');

        Schema::table('nodes', function (Blueprint $table): void {
            if (Schema::hasColumn('nodes', 'metrics_schema')) {
                $table->dropColumn('metrics_schema');
            }
            if (Schema::hasColumn('nodes', 'node_type')) {
                $table->dropColumn('node_type');
            }
        });

        Schema::table('telemetry', function (Blueprint $table): void {
            if (Schema::hasColumn('telemetry', 'metrics')) {
                $table->dropColumn('metrics');
            }
        });
    }

    private function backfillExistingTelemetry(): void
    {
        DB::table('telemetry')->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                $metrics = [];
                if ($row->temperature !== null) {
                    $metrics['temperature'] = (float) $row->temperature;
                }
                if ($row->humidity !== null) {
                    $metrics['humidity'] = (float) $row->humidity;
                }

                if ($metrics === []) {
                    continue;
                }

                DB::table('telemetry')->where('id', $row->id)->update([
                    'metrics' => json_encode($metrics, JSON_THROW_ON_ERROR),
                ]);

                foreach ($metrics as $key => $value) {
                    DB::table('telemetry_readings')->insert([
                        'telemetry_id' => $row->id,
                        'gateway_id' => $row->gateway_id,
                        'node_id' => $row->node_id,
                        'timestamp' => $row->timestamp,
                        'metric_key' => $key,
                        'metric_value' => $value,
                        'unit' => match ($key) {
                            'temperature' => '°C',
                            'humidity' => '%',
                            default => null,
                        },
                        'created_at' => $row->created_at ?? now(),
                    ]);
                }
            }
        });

        DB::table('nodes')->whereNull('node_type')->orWhere('node_type', '')->update([
            'node_type' => 'env_basic',
        ]);

        $defaultSchema = json_encode([
            'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'type' => 'number'],
            'humidity' => ['label' => 'Humidity', 'unit' => '%', 'type' => 'number'],
        ], JSON_THROW_ON_ERROR);

        DB::table('nodes')->whereNull('metrics_schema')->update([
            'metrics_schema' => $defaultSchema,
        ]);
    }

    private function createGrafanaView(): void
    {
        DB::statement('DROP VIEW IF EXISTS grafana_telemetry_readings');
        DB::statement(<<<'SQL'
            CREATE VIEW grafana_telemetry_readings AS
            SELECT
                timestamp AS time,
                gateway_id,
                node_id,
                metric_key,
                metric_value AS value,
                unit,
                telemetry_id
            FROM telemetry_readings
        SQL);
    }
};
