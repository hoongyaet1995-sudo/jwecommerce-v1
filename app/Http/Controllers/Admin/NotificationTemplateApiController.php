<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Notifications\NotificationEventCatalog;
use App\Domain\Notifications\NotificationPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateNotificationTemplateRequest;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Services\Notifications\NotificationDispatchService;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NotificationTemplateApiController extends Controller
{
    private const INPUT_TO_ATTRIBUTE = [
        'emailEnabled' => 'email_enabled',
        'emailSubject' => 'email_subject',
        'emailPreheader' => 'email_preheader',
        'emailHeading' => 'email_heading',
        'emailBody' => 'email_body',
        'emailCtaText' => 'email_cta_text',
        'emailCtaUrl' => 'email_cta_url',
        'whatsappEnabled' => 'whatsapp_enabled',
        'whatsappMessage' => 'whatsapp_message',
        'whatsappCtaButtons' => 'whatsapp_cta_buttons',
    ];

    public function __construct(
        private readonly NotificationTemplateDefaults $defaults,
        private readonly NotificationDispatchService $dispatches,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::TEMPLATES_VIEW);
        $this->defaults->synchronize();

        return response()->json([
            'data' => NotificationTemplate::query()->orderBy('id')->get()->map($this->templateData(...))->values(),
        ]);
    }

    public function update(UpdateNotificationTemplateRequest $request, string $eventKey): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::TEMPLATES_MANAGE);
        $this->assertKnownEvent($eventKey);
        $this->defaults->synchronize();
        $validated = $request->validated();

        $template = DB::transaction(function () use ($request, $eventKey, $validated): NotificationTemplate {
            $template = NotificationTemplate::query()
                ->where('event_key', $eventKey)
                ->lockForUpdate()
                ->firstOrFail();

            if (array_key_exists('version', $validated) && (int) $validated['version'] !== $template->version) {
                abort(409, 'This notification template was updated by another user. Refresh and try again.');
            }

            $attributes = [];
            foreach (self::INPUT_TO_ATTRIBUTE as $input => $attribute) {
                if (array_key_exists($input, $validated)) {
                    $attributes[$attribute] = $validated[$input];
                }
            }

            $candidate = $template->replicate();
            $candidate->forceFill($attributes);

            try {
                $this->dispatches->validateTemplate($candidate);
            } catch (ValidationException $error) {
                throw ValidationException::withMessages($this->camelCaseErrors($error->errors()));
            }

            $template->forceFill([
                ...$attributes,
                'version' => $template->version + 1,
                'updated_by' => $request->user()->id,
            ])->save();

            return $template->refresh();
        });

        return response()->json(['data' => $this->templateData($template)]);
    }

    public function reset(Request $request): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::TEMPLATES_MANAGE);
        $this->defaults->synchronize();
        $this->defaults->reset($request->user());

        return response()->json([
            'data' => NotificationTemplate::query()->orderBy('id')->get()->map($this->templateData(...))->values(),
        ]);
    }

    public function test(Request $request, string $eventKey): JsonResponse
    {
        $this->authorizePermission($request, NotificationPermission::TEST_SEND);
        $this->assertKnownEvent($eventKey);
        $validated = $request->validate([
            'recipientEmail' => ['required', 'email:rfc', 'max:320'],
        ]);
        $this->defaults->synchronize();
        $template = NotificationTemplate::query()->where('event_key', $eventKey)->firstOrFail();
        $dispatch = $this->dispatches->test($template, $validated['recipientEmail'], $request->user());

        return response()->json(['data' => $this->dispatchData($dispatch)], 201);
    }

    private function authorizePermission(Request $request, string $permission): void
    {
        abort_unless(NotificationPermission::allows($request->user(), $permission), 403);
    }

    private function assertKnownEvent(string $eventKey): void
    {
        abort_unless(array_key_exists($eventKey, NotificationEventCatalog::all()), 404);
    }

    /** @return array<string, mixed> */
    private function templateData(NotificationTemplate $template): array
    {
        $event = NotificationEventCatalog::get($template->event_key);

        return [
            'eventKey' => $template->event_key,
            'statusName' => $template->status_name,
            'color' => $template->color,
            'automaticEmail' => $event['automatic_email'],
            'variables' => $event['variables'],
            'emailEnabled' => $template->email_enabled,
            'emailSubject' => $template->email_subject,
            'emailPreheader' => $template->email_preheader,
            'emailHeading' => $template->email_heading,
            'emailBody' => $template->email_body,
            'emailCtaText' => $template->email_cta_text,
            'emailCtaUrl' => $template->email_cta_url,
            'whatsappEnabled' => $template->whatsapp_enabled,
            'whatsappMessage' => $template->whatsapp_message,
            'whatsappCtaButtons' => $template->whatsapp_cta_buttons ?? [],
            'version' => $template->version,
            'updatedById' => $template->updated_by,
            'updatedAt' => $template->updated_at?->toISOString(),
        ];
    }

    /** @return array<string, mixed> */
    private function dispatchData(NotificationDispatch $dispatch): array
    {
        return [
            'id' => $dispatch->public_id,
            'eventKey' => $dispatch->event_key,
            'channel' => $dispatch->channel,
            'state' => $dispatch->state,
            'source' => $dispatch->source,
            'subject' => $dispatch->rendered_subject,
            'templateVersion' => $dispatch->template_version,
            'attemptCount' => $dispatch->attempt_count,
            'queuedAt' => $dispatch->queued_at?->toISOString(),
            'sentAt' => $dispatch->sent_at?->toISOString(),
            'failedAt' => $dispatch->failed_at?->toISOString(),
            'errorCode' => $dispatch->last_error_code,
            'resendOfId' => null,
            'createdAt' => $dispatch->created_at?->toISOString(),
        ];
    }

    /** @param array<string, list<string>> $errors
     * @return array<string, list<string>>
     */
    private function camelCaseErrors(array $errors): array
    {
        $mapping = array_flip(self::INPUT_TO_ATTRIBUTE);
        $mapped = [];

        foreach ($errors as $field => $messages) {
            $mapped[$mapping[$field] ?? $field] = $messages;
        }

        return $mapped;
    }
}
