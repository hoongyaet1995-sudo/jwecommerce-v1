<?php
namespace App\Http\Requests\Admin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class UpdateOrderDeliveryDetailsRequest extends FormRequest {
    private const MALAYSIA_STATES = ['Johor','Kedah','Kelantan','Melaka','Negeri Sembilan','Pahang','Penang','Perak','Perlis','Sabah','Sarawak','Selangor','Terengganu','Kuala Lumpur','Labuan','Putrajaya'];
    public function authorize(): bool { return true; }
    public function rules(): array { return [
        'firstName'=>['required','string','max:120'],'lastName'=>['required','string','max:120'],'phone'=>['required','string','max:32'],
        'address1'=>['required','string','max:255'],'address2'=>['nullable','string','max:255'],'postalCode'=>['required','string','max:20'],
        'city'=>['required','string','max:120'],'state'=>['required','string','max:120'],'country'=>['required','string','max:120'],
        'reasonCode'=>['required',Rule::in(['customer_request','invalid_receiver_details','address_typo','other'])],
        'internalNote'=>['required','string','min:3','max:2000'],'syncAddressBook'=>['required','boolean'],
    ]; }
    public function withValidator($validator): void { $validator->after(function ($validator): void {
        if (strcasecmp((string)$this->input('country'),'Malaysia') !== 0) return;
        if (!preg_match('/^0\d{8,10}$/',(string)$this->input('phone'))) $validator->errors()->add('phone','Use a Malaysian local mobile number beginning with 0.');
        if (!preg_match('/^\d{5}$/',(string)$this->input('postalCode'))) $validator->errors()->add('postalCode','Malaysian postcodes must contain five digits.');
        if (!in_array($this->input('state'),self::MALAYSIA_STATES,true)) $validator->errors()->add('state','Select a valid Malaysian state or federal territory.');
    }); }
}
