<?php
namespace App\Services;

/** Only in-memory Admin acceptance DTOs. Does not create customer or commerce records. */
class LogisticsAccountPreview
{
    public function account(array $catalog, array $data): array
    {
        $products=$catalog['demoProducts'];
        if ($products->contains(fn ($product) => $product->exists)) throw new \LogicException('Logistics account preview requires unsaved demo products.');
        $product=$products->first();
        $image=$product?->images->first()?->path ?: asset('images/themes/logistics/containers.png');
        $data['user']['name']='Alex Customer';
        $data['user']['firstName']='Alex';
        $data['user']['lastName']='Customer';
        $data['user']['email']='samplecustomer@example.test';
        $data['orders'][0]=array_replace($data['orders'][0],[
            'number'=>'DEMO-JTG-001','status'=>'processing','shippedAt'=>null,'trackingCarrier'=>null,'trackingNumber'=>null,
            'shippingLabel'=>'Delivery subject to confirmation','subtotal'=>20000,'total'=>20000,'shippingAmount'=>0,
            'payments'=>[['id'=>-1,'gateway'=>'manual','gatewayStatus'=>'paid','transactionId'=>'DEMO-PAYMENT','paymentMethod'=>'manual','lastFour'=>null]],
        ]);
        $data['orders'][0]['items'][0]=array_replace($data['orders'][0]['items'][0],[
            'title'=>'Container Accessories','variantTitle'=>'Sample accessory order','imageUrl'=>$image,
            'sku'=>'DEMO-ACCESSORY','unitPrice'=>20000,'lineTotal'=>20000,
        ]);
        $data['addresses'][0]=array_replace($data['addresses'][0],['label'=>'Delivery address','firstName'=>'Alex','line1'=>'12, Jalan Industri 3','line2'=>'Kawasan Perindustrian Klang','city'=>'Klang','state'=>'Selangor','postalCode'=>'42100']);
        $data['wishlist']=$products->take(3)->map(fn ($product) => ['product'=>[
            'id'=>$product->id,'handle'=>$product->handle,'title'=>$product->title,
            'purchaseMode'=>$product->purchase_mode,'priceLabel'=>'Price on request','basePrice'=>null,
            'images'=>[['path'=>$product->images->first()?->path ?: $image,'alt'=>$product->title]],
            'variants'=>$product->variants->map(fn ($variant) => ['id'=>$variant->id,'title'=>$variant->title,'price'=>null])->all(),
        ]])->all();
        $data['dashboard']=['orderCount'=>1,'activeLogisticsCount'=>1,'paidSpend'=>[['amount'=>20000,'currency'=>'MYR','currencyExchangeRate'=>1]]];
        $data['quotations']=[['id'=>'demo-quotation','number'=>'DEMO-QT-001','revision'=>1,'sentAt'=>'2026-10-01T10:00:00Z','expiresAt'=>'2099-01-01T00:00:00Z','expired'=>false,'url'=>'#quotations']];
        foreach($data['vouchers']['eligible'] as $index=>&$voucher){
            $voucher['code']=$index===0?'JTG10':'DELIVERY';
            $voucher['title']=$index===0?'Container accessories':'Delivery voucher';
            $voucher['description']='Sample voucher for theme preview only. Eligibility is confirmed at checkout.';
        }
        unset($voucher);
        return $data;
    }
}
