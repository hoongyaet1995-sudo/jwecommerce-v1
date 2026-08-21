<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\CmsContactSubmission;
use App\Models\Page;
use App\Models\PageDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class CmsContactSubmissionController extends Controller
{
    public function __invoke(Request $request, Page $page, string $stage, string $section): JsonResponse
    {
        try {
            $input = $this->validatedInput($request);
            $submission = DB::transaction(function () use ($request, $page, $stage, $section, $input): CmsContactSubmission {
                $page = Page::query()->lockForUpdate()->findOrFail($page->getKey());
                $authoritativeSection = $this->authoritativeSection($request, $page, $stage, $section);
                $this->assertCategoryIsOwnedBySection($input['category'], $authoritativeSection);

                return CmsContactSubmission::query()->create([
                    'page_id' => $page->getKey(),
                    'page_id_snapshot' => $page->getKey(),
                    'document_stage' => $stage,
                    'section_id' => $section,
                    'section_type' => 'contact_form',
                    'name' => $input['name'],
                    'email' => $input['email'],
                    'subject' => $input['subject'],
                    'category' => $input['category'],
                    'message' => $input['message'],
                ]);
            });
        } catch (ValidationException $exception) {
            return $this->invalid($exception);
        }

        return response()->json(['data' => ['submitted' => true, 'id' => (string) $submission->getKey()]], 201);
    }

    /** @return array{name: string, email: string, subject: string, category: string, message: string} */
    private function validatedInput(Request $request): array
    {
        $input = $request->json()->all();
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        $errors = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'subject' => ['required', 'string', 'max:200'],
            'category' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
        ])->errors()->toArray();
        foreach (array_diff(array_keys($input), ['name', 'email', 'subject', 'category', 'message']) as $field) {
            $errors[$field][] = 'This form field is not allowed.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /** @var array{name: string, email: string, subject: string, category: string, message: string} $input */
        return $input;
    }

    /** @return array<string, mixed> */
    private function authoritativeSection(Request $request, Page $page, string $stage, string $sectionId): array
    {
        abort_unless($this->mayUseStage($page, $stage), 403);

        $document = PageDocument::query()->where('page_id', $page->getKey())->where('stage', $stage)->lockForUpdate()->first();
        if ($document === null) {
            throw ValidationException::withMessages(['section' => ['The submitted page form is unavailable.']]);
        }
        if ($request->query('hash') !== $document->content_hash || (string) $request->query('version') !== (string) $document->lock_version || $request->query('type') !== 'contact_form') {
            throw ValidationException::withMessages(['contract' => ['The submitted form contract is stale.']]);
        }

        foreach ($document->document['sections'] ?? [] as $section) {
            if (is_array($section) && ($section['id'] ?? null) === $sectionId && ($section['type'] ?? null) === 'contact_form' && ($section['isVisible'] ?? false) === true) {
                return $section;
            }
        }
        throw ValidationException::withMessages(['section' => ['The submitted form does not match the current page section.']]);
    }

    /** @param array<string, mixed> $section */
    private function assertCategoryIsOwnedBySection(string $category, array $section): void
    {
        $categories = $section['inquiryCategories'] ?? [];
        if (! is_array($categories) || $categories === []) {
            $categories = [
                ['id' => 'cat-1', 'label' => 'Product Enquiry'], ['id' => 'cat-2', 'label' => 'Order Support'],
                ['id' => 'cat-3', 'label' => 'Wholesale & Partnership'], ['id' => 'cat-4', 'label' => 'Press & Collaboration'],
                ['id' => 'cat-5', 'label' => 'Other'],
            ];
        }
        $labels = array_filter(array_column($categories, 'label'), 'is_string');
        if (! in_array($category, $labels, true)) {
            throw ValidationException::withMessages(['category' => ['The selected inquiry category is unavailable.']]);
        }
    }

    private function mayUseStage(Page $page, string $stage): bool
    {
        if ($stage === PageDocument::STAGE_PUBLISHED) {
            return $page->status === 'published' && $page->is_published;
        }

        return $stage === PageDocument::STAGE_DRAFT && auth()->user()?->is_admin === true;
    }

    private function invalid(ValidationException $exception): JsonResponse
    {
        return response()->json(['message' => 'The contact form submission is invalid.', 'code' => 'cms_contact_submission_invalid', 'errors' => $exception->errors()], 422);
    }
}
