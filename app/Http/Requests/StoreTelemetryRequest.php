<?php

namespace App\Http\Requests;

use App\Support\NodeMetricCatalog;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTelemetryRequest extends FormRequest
{
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

        $this->merge([
            'metrics' => NodeMetricCatalog::normalizeNumericMetrics($metrics),
        ]);
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
