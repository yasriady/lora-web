<?php

namespace App\Support;

class NodeMetricCatalog
{
    /**
     * @return array<string, array{label: string, schema: array<string, array{label: string, unit: ?string, type: string}>}>
     */
    public static function presets(): array
    {
        return [
            'env_basic' => [
                'label' => 'Environment (Temp/Humidity)',
                'schema' => [
                    'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'type' => 'number'],
                    'humidity' => ['label' => 'Humidity', 'unit' => '%', 'type' => 'number'],
                ],
            ],
            'env_bme280' => [
                'label' => 'Environment BME280',
                'schema' => [
                    'temperature' => ['label' => 'Temperature', 'unit' => '°C', 'type' => 'number'],
                    'humidity' => ['label' => 'Humidity', 'unit' => '%', 'type' => 'number'],
                    'pressure' => ['label' => 'Pressure', 'unit' => 'hPa', 'type' => 'number'],
                ],
            ],
            'soil_v1' => [
                'label' => 'Soil Moisture',
                'schema' => [
                    'soil_moisture' => ['label' => 'Soil Moisture', 'unit' => '%', 'type' => 'number'],
                    'temperature' => ['label' => 'Soil Temp', 'unit' => '°C', 'type' => 'number'],
                ],
            ],
            'air_quality' => [
                'label' => 'Air Quality',
                'schema' => [
                    'co2' => ['label' => 'CO₂', 'unit' => 'ppm', 'type' => 'number'],
                    'pm25' => ['label' => 'PM2.5', 'unit' => 'µg/m³', 'type' => 'number'],
                    'pm10' => ['label' => 'PM10', 'unit' => 'µg/m³', 'type' => 'number'],
                    'tvoc' => ['label' => 'TVOC', 'unit' => 'ppb', 'type' => 'number'],
                ],
            ],
            'gps_tracker' => [
                'label' => 'GPS Tracker',
                'schema' => [
                    'latitude' => ['label' => 'Latitude', 'unit' => '°', 'type' => 'number'],
                    'longitude' => ['label' => 'Longitude', 'unit' => '°', 'type' => 'number'],
                    'altitude' => ['label' => 'Altitude', 'unit' => 'm', 'type' => 'number'],
                    'speed' => ['label' => 'Speed', 'unit' => 'km/h', 'type' => 'number'],
                ],
            ],
            'custom' => [
                'label' => 'Custom / Any metrics',
                'schema' => [],
            ],
        ];
    }

    /**
     * @param  array<string, array{label?: string, unit?: ?string, type?: string}>|null  $schema
     */
    public static function unitFor(string $metricKey, ?array $schema = null): ?string
    {
        if (is_array($schema) && isset($schema[$metricKey]['unit'])) {
            return $schema[$metricKey]['unit'];
        }

        return match ($metricKey) {
            'temperature' => '°C',
            'humidity', 'soil_moisture', 'battery_percent' => '%',
            'pressure' => 'hPa',
            'co2', 'tvoc' => 'ppm',
            'pm25', 'pm10' => 'µg/m³',
            'latitude', 'longitude' => '°',
            'altitude' => 'm',
            'speed' => 'km/h',
            'lux', 'light' => 'lux',
            'rssi' => 'dBm',
            'snr' => 'dB',
            'battery' => 'V',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, float>
     */
    public static function normalizeNumericMetrics(array $metrics): array
    {
        $normalized = [];

        foreach ($metrics as $key => $value) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $key)) {
                continue;
            }

            if (is_bool($value) || is_numeric($value)) {
                $normalized[$key] = round((float) $value, 6);
            }
        }

        return $normalized;
    }
}
