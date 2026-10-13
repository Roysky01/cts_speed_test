<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSpeedTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ping_ms' => ['required', 'numeric', 'min:0', 'max:10000'],
            'jitter_ms' => ['required', 'numeric', 'min:0', 'max:10000'],
            'loaded_ping_ms' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'download_bytes' => ['required', 'integer', 'min:0', 'max:50000000000'],
            'upload_bytes' => ['required', 'integer', 'min:0', 'max:50000000000'],
            'download_ms' => ['required', 'integer', 'min:200', 'max:120000'],
            'upload_ms' => ['required', 'integer', 'min:200', 'max:120000'],
        ];
    }
}
