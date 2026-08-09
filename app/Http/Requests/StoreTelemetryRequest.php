<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTelemetryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gateway_id' => ['required', 'string', 'max:64'],
            'node_id' => ['required', 'string', 'max:64'],
            'timestamp' => ['required', 'date'],
            'temperature' => ['required', 'numeric', 'between:-100,200'],
            'humidity' => ['required', 'numeric', 'between:0,100'],
            'battery' => ['required', 'numeric', 'between:0,10'],
            'rssi' => ['required', 'integer', 'between:-200,0'],
            'snr' => ['required', 'numeric', 'between:-50,50'],
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
