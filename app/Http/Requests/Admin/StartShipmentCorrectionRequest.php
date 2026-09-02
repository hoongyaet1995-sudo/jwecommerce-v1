<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartShipmentCorrectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reasonCode' => ['required', Rule::in([
                'wrong_fulfilment_method', 'incorrect_delivery_details', 'duplicate_booking',
                'customer_request', 'sender_information_error', 'other',
            ])],
            'internalNote' => ['required', 'string', 'min:3', 'max:2000'],
            'externalActionState' => ['required', Rule::in([
                'provider_confirmed', 'staff_confirmed_external', 'not_required',
            ])],
            'externalActionNote' => ['nullable', 'required_if:externalActionState,staff_confirmed_external', 'string', 'min:3', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $shipment = $this->route('shipment');
            if ($shipment?->source === 'easyparcel'
                && ! in_array($this->input('externalActionState'), ['provider_confirmed', 'staff_confirmed_external'], true)) {
                $validator->errors()->add('externalActionState', 'EasyParcel attempts require provider confirmation or a staff-confirmed external acknowledgement.');
            }
            if ($shipment?->source !== 'easyparcel' && $this->input('externalActionState') !== 'not_required') {
                $validator->errors()->add('externalActionState', 'External provider confirmation does not apply to this shipment.');
            }
        });
    }
}
