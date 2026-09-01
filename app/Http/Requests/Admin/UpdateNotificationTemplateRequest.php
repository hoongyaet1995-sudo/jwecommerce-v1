<?php

namespace App\Http\Requests\Admin;

use App\Domain\Notifications\NotificationPermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && NotificationPermission::allows($user, NotificationPermission::TEMPLATES_MANAGE);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'eventKey' => ['sometimes', 'string', Rule::in([(string) $this->route('eventKey')])],
            'version' => ['sometimes', 'integer', 'min:1'],
            'emailEnabled' => ['sometimes', 'boolean'],
            'emailSubject' => ['sometimes', 'string', 'max:255'],
            'emailPreheader' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emailHeading' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emailBody' => ['sometimes', 'string', 'max:50000'],
            'emailCtaText' => ['sometimes', 'nullable', 'string', 'max:120'],
            'emailCtaUrl' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'whatsappEnabled' => ['sometimes', 'boolean'],
            'whatsappMessage' => ['sometimes', 'nullable', 'string', 'max:50000'],
            'whatsappCtaButtons' => ['sometimes', 'array', 'max:10'],
            'whatsappCtaButtons.*.id' => ['required_with:whatsappCtaButtons', 'string', 'max:64'],
            'whatsappCtaButtons.*.text' => ['required_with:whatsappCtaButtons', 'string', 'max:120'],
            'whatsappCtaButtons.*.type' => ['required_with:whatsappCtaButtons', Rule::in(['url'])],
            'whatsappCtaButtons.*.value' => ['required_with:whatsappCtaButtons', 'string', 'max:2048'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ((string) $this->route('eventKey') === 'on_hold'
                && $this->has('emailEnabled')
                && $this->boolean('emailEnabled')) {
                $validator->errors()->add('emailEnabled', 'On Hold email delivery is dormant and cannot be enabled.');
            }
        });
    }
}
