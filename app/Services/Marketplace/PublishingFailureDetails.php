<?php

namespace App\Services\Marketplace;

final class PublishingFailureDetails
{
    public static function fromThrowable(\Throwable $error, string $stage): array
    {
        $message=strtolower($error->getMessage());
        [$code,$safe,$next]=match (true) {
            $error instanceof PublishingProviderRejected && ($error->diagnostics()['provider_code']??null)==='product.error_desc_length_min_limit' =>
                ['product_description_required','Shopee rejected the product description. Add a complete description to the JW product and publish again.','review_product'],
            $error instanceof PublishingResultUncertain =>
                ['provider_result_unknown','The marketplace creation result was not confirmed. Check the saved task details or deliberately start a new attempt.','check_marketplace_result'],
            str_contains($message,'token') || str_contains($message,'credential') || str_contains($message,'connect') =>
                ['credentials_unavailable','The marketplace connection expired or could not be verified.','reauthorize_store'],
            str_contains($message,'changed after review') =>
                ['review_outdated','The JW product changed after review. Review the current product details before trying again.','review_product'],
            str_contains($message,'image') =>
                ['image_validation_failed','One or more product images no longer meet the reviewed marketplace requirements.','review_images'],
            str_contains($message,'attribute') || str_contains($message,'category') =>
                ['marketplace_validation_failed','The marketplace rejected or changed a category or attribute requirement.','review_attributes'],
            default => ['provider_rejected','The marketplace did not accept this publishing attempt. Open the task details and review the current product and store connection.','review_task'],
        };
        $diagnostics=$error instanceof PublishingResultUncertain || $error instanceof PublishingProviderRejected
            ? $error->diagnostics() : [];
        return ['code'=>$code,'message'=>$safe,'stage'=>$stage,'nextAction'=>$next,'diagnostics'=>$diagnostics ?: (object)[]];
    }
}
