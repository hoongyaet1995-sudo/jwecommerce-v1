<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cms\NewsletterSubscriptionService;
use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class NewsletterSubscriptionController extends Controller
{
    public function __invoke(Request $request, Page $page, string $stage, string $section, NewsletterSubscriptionService $subscriptions): JsonResponse
    {
        try {
            $result = $subscriptions->subscribe($request, $page, $stage, $section, $this->validatedEmail($request));
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => 'The newsletter subscription is invalid.',
                'code' => 'cms_newsletter_subscription_invalid',
                'errors' => $exception->errors(),
            ], 422);
        }

        return response()->json(['data' => $result], ($result['alreadySubscribed'] ?? false) ? 200 : 201);
    }

    private function validatedEmail(Request $request): string
    {
        $input = $request->json()->all();
        if (is_string($input['email'] ?? null)) {
            $input['email'] = mb_strtolower(trim($input['email']));
        }
        $errors = Validator::make($input, ['email' => ['required', 'email:rfc', 'max:254']])->errors()->toArray();
        foreach (array_diff(array_keys($input), ['email']) as $field) {
            $errors[$field][] = 'This form field is not allowed.';
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $input['email'];
    }
}
