<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $documents = [
            'shipping_rates' => [[
                'id'=>'standard-global','name'=>'Standard Delivery','zoneRegion'=>'all','logisticType'=>'all','calculationMode'=>'per_order','rateType'=>'flat','tiers'=>[],
                'flatRateCost'=>0,'freeShippingThreshold'=>150,'estimatedDays'=>'3-7 business days','isActive'=>true,'notes'=>'Operational default; editable in Admin.',
                'createdAt'=>$now->toIso8601String(),'updatedAt'=>$now->toIso8601String(),
            ]],
            'payment_gateways' => [
                ['id'=>'stripe','name'=>'Credit Card','category'=>'card','description'=>'Simulated card payment record','logo'=>'','isEnabled'=>true,'isSandbox'=>true,'sortOrder'=>1,'credentials'=>[],'supportedCurrencies'=>['USD'],'transactionFee'=>['type'=>'fixed','feePayer'=>'merchant'],'limits'=>[],'features'=>['supportsApplePay'=>true],'webhookUrl'=>''],
                ['id'=>'paypal','name'=>'PayPal','category'=>'card','description'=>'Simulated PayPal payment record','logo'=>'','isEnabled'=>true,'isSandbox'=>true,'sortOrder'=>2,'credentials'=>[],'supportedCurrencies'=>['USD'],'transactionFee'=>['type'=>'fixed','feePayer'=>'merchant'],'limits'=>[],'features'=>[],'webhookUrl'=>''],
                ['id'=>'atome','name'=>'Atome','category'=>'bnpl','description'=>'Simulated BNPL payment record','logo'=>'','isEnabled'=>true,'isSandbox'=>true,'sortOrder'=>3,'credentials'=>[],'supportedCurrencies'=>['USD'],'transactionFee'=>['type'=>'fixed','feePayer'=>'merchant'],'limits'=>[],'features'=>[],'webhookUrl'=>''],
            ],
            'global_payment' => ['defaultGateway'=>'stripe','allowMultiplePaymentMethods'=>true,'enableSandboxBanner'=>true,'unpaidOrderAutoCancelMinutes'=>120,'sendPaymentReminderSms'=>false,'sendPaymentReminderEmail'=>true,'displayTaxInvoiceAtCheckout'=>true,'enableLoyaltyPointsSplitPayment'=>false,'maxLoyaltyPointsDiscountPercent'=>0],
            'bank_transfer' => ['isEnabled'=>false,'bankAccounts'=>[],'requireProofUpload'=>true,'verificationInstructions'=>'','paymentWindowMinutes'=>1440,'notificationEmail'=>'','allowInstantDuitNow'=>false],
            'cod_config' => ['isEnabled'=>false,'surchargeType'=>'fixed','surchargeAmount'=>0,'minOrderAmount'=>0,'maxOrderAmount'=>0,'allowedRegions'=>'all','customPostcodes'=>[],'requirePhoneOtp'=>false,'instructions'=>''],
        ];
        foreach ($documents as $key => $data) {
            DB::table('store_settings')->insertOrIgnore(['key'=>$key,'value'=>json_encode(['data'=>$data], JSON_THROW_ON_ERROR),'created_at'=>$now,'updated_at'=>$now]);
        }
    }

    public function down(): void
    {
        // Preserve operator-managed settings if this data migration is rolled back.
    }
};
