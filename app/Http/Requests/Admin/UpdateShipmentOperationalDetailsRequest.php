<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateShipmentOperationalDetailsRequest extends FormRequest {
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'courierCode'=>['nullable','string','max:80'], 'courierName'=>['nullable','string','max:120'],
        'serviceName'=>['nullable','string','max:160'], 'trackingNumber'=>['nullable','string','max:160'],
        'trackingUrl'=>['nullable','url:http,https','max:2048'], 'driverName'=>['nullable','string','max:120'],
        'handoffReference'=>['nullable','string','max:160'],
        'reasonCode'=>['required',Rule::in(['tracking_typo','courier_correction','driver_correction','handoff_correction','other'])],
        'internalNote'=>['required','string','min:3','max:2000'],
    ]; }
}
