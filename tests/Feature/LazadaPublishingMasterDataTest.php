<?php

namespace Tests\Feature;

use App\Services\Marketplace\LazadaPublishingMasterData;
use PHPUnit\Framework\TestCase;

class LazadaPublishingMasterDataTest extends TestCase
{
    private function source(): array
    {
        return ['title'=>'Saved master','description'=>'Saved description','variants'=>[
            ['sku'=>'BLUE','price_minor'=>1599],['sku'=>'RED','price_minor'=>2001],
        ]];
    }

    private function payload(): array
    {
        return ['Request'=>['Product'=>['PrimaryCategory'=>'123','Attributes'=>['name'=>'Client name','brand'=>'Selected brand'],
            'Skus'=>['Sku'=>[['SellerSku'=>'RED','price'=>'1','quantity'=>'50','color_family'=>'Red'],
                ['SellerSku'=>'BLUE','price'=>'2','quantity'=>'99','color_family'=>'Blue']]]]]];
    }

    public function test_master_values_replace_client_values_without_losing_category_attributes(): void
    {
        $result=LazadaPublishingMasterData::bind($this->source(),$this->payload());
        $product=$result['Request']['Product'];
        $this->assertSame('Saved master',$product['Attributes']['name']);
        $this->assertSame('Saved description',$product['Attributes']['description']);
        $this->assertSame('Selected brand',$product['Attributes']['brand']);
        $this->assertSame([
            ['SellerSku'=>'BLUE','price'=>'15.99','quantity'=>'0','color_family'=>'Blue'],
            ['SellerSku'=>'RED','price'=>'20.01','quantity'=>'0','color_family'=>'Red'],
        ],$product['Skus']['Sku']);
    }

    public function test_foreign_or_missing_variant_is_rejected(): void
    {
        $payload=$this->payload();
        $payload['Request']['Product']['Skus']['Sku'][0]['SellerSku']='FOREIGN';
        $this->expectException(\DomainException::class);
        LazadaPublishingMasterData::bind($this->source(),$payload);
    }

    public function test_duplicate_variant_is_rejected(): void
    {
        $payload=$this->payload();
        $payload['Request']['Product']['Skus']['Sku'][0]['SellerSku']='BLUE';
        $this->expectException(\DomainException::class);
        LazadaPublishingMasterData::bind($this->source(),$payload);
    }

    public function test_alternate_price_and_existing_product_association_are_rejected(): void
    {
        $payload=$this->payload();
        $payload['Request']['Product']['Skus']['Sku'][0]['special_price']='0.01';
        $this->expectException(\DomainException::class);
        LazadaPublishingMasterData::bind($this->source(),$payload);
    }
}
