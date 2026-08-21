<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['required', 'string', 'max:255'],
            'event_type' => ['required', 'string', 'max:255'],
            'data' => ['required', 'array'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'event_id' => $this->input('event_id') ?: 'evt_'.Str::ulid(),
        ]);
    }

    public function eventId(): string
    {
        return $this->string('event_id')->value();
    }

    public function eventType(): string
    {
        return $this->string('event_type')->value();
    }

    /**
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        return $this->array('data');
    }
}
