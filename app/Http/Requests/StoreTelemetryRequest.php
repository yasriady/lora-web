<?php

namespace App\Http\Requests;

use App\Support\NodeMetricCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTelemetryRequest extends FormRequest
{
    /**
     * Packet envelope fields that must not be stored as sensor metrics.
     *
     * @var list<string>
     */
    private const ENVELOPE_METRIC_KEYS = [
        'seq',
        'rssi',
        'snr',
        'battery',
        'node_id',
        'gateway_id',
        'timestamp',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $metrics = $this->input('metrics');

        if (! is_array($metrics)) {
            $metrics = [];
        }

        foreach (['temperature', 'humidity', 'pressure', 'soil_moisture', 'co2', 'pm25', 'pm10', 'lux', 'latitude', 'longitude'] as $legacyKey) {
            if ($this->filled($legacyKey) && ! array_key_exists($legacyKey, $metrics)) {
                $metrics[$legacyKey] = $this->input($legacyKey);
            }
        }

        $seq = $this->normalizeSeq($this->input('seq') ?? $metrics['seq'] ?? null);

        foreach (self::ENVELOPE_METRIC_KEYS as $key) {
            unset($metrics[$key]);
        }

        $this->merge([
            'metrics' => NodeMetricCatalog::normalizeNumericMetrics($metrics),
            'seq' => $seq,
        ]);
    }

    private function normalizeSeq(mixed $value): ?int
    {
        if ($value === null || $value === '' || is_bool($value) || ! is_numeric($value)) {
            return null;
        }

        $seq = (int) $value;

        if ($seq < 1 || $seq > 4294967295) {
            return null;
        }

        return $seq;
    }

    public function rules(): array
    {
        return [
            'gateway_id' => ['required', 'string', 'max:64'],
            'node_id' => ['required', 'string', 'max:64'],
            'timestamp' => ['required', 'date'],
            'battery' => ['nullable', 'numeric', 'between:0,10'],
            'rssi' => ['nullable', 'integer', 'between:-200,0'],
            'snr' => ['nullable', 'numeric', 'between:-50,50'],
            'seq' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'metrics' => ['required', 'array', 'min:1'],
            'metrics.*' => ['numeric'],
            // Legacy fields remain accepted but optional.
            'temperature' => ['nullable', 'numeric', 'between:-100,200'],
            'humidity' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Invalid telemetry payload.',
            'errors' => $validator->errors(),
        ], 400));
    }
}
