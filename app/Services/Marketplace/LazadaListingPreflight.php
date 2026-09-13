<?php
namespace App\Services\Marketplace;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Current category, required-field and supported attribute-value checks. */
final class LazadaListingPreflight
{
    public function __construct(private LazadaClient $client) {}

    public function validate(#[\SensitiveParameter] string $token, array $payload): void
    {
        $product=$payload['Request']['Product'] ?? [];
        $id=$product['PrimaryCategory'] ?? null;
        if ((!is_int($id)&&!is_string($id)) || !preg_match('/^[1-9][0-9]*$/D',(string)$id)) {
            throw ValidationException::withMessages(['category'=>'Select a Lazada leaf category.']);
        }
        $categories=LazadaListingCategories::flatten($this->client->read('/category/tree/get',$token));
        if (!collect($categories)->contains(fn($row)=>(string)$row['category_id']===(string)$id && !$row['has_children'])) {
            throw ValidationException::withMessages(['category'=>'This category is no longer an available leaf category.']);
        }
        $schema=$this->client->read('/category/attributes/get',$token,['primary_category_id'=>(string)$id]);
        $skus=$product['Skus']['Sku'] ?? null;
        if (!array_is_list($schema) || !is_array($skus) || !array_is_list($skus) || !$skus) throw new RuntimeException('Invalid Lazada listing schema or SKU list.');
        $seen=[];
        foreach ($schema as $attribute) {
            $name=$attribute['name'] ?? null;$scope=$attribute['attribute_type'] ?? null;
            if (!is_string($name)||trim($name)===''||!in_array($scope,['normal','sku'],true)
                ||!in_array($attribute['is_mandatory']??null,[0,1,false,true],true)||isset($seen[$scope.':'.$name])) {
                throw new RuntimeException('Lazada attribute schema is incomplete.');
            }
            $seen[$scope.':'.$name]=true;
            $required=(bool)$attribute['is_mandatory'] || ($scope==='sku' && count($skus)>1 && ($attribute['is_sale_prop']??0)==1);
            $targets=$scope==='sku'?$skus:[$product['Attributes'] ?? []];
            foreach ($targets as $index=>$target) {
                $value=$name==='__images__'?($target['Images']['Image'] ?? null):($target[$name] ?? null);
                if ($value===null || (is_string($value)&&trim($value)==='') || $value===[]) {
                    if (!$required) continue;
                    throw ValidationException::withMessages(['attributes'=>"Complete required {$scope} field {$name}".($scope==='sku'?' for variant '.($index+1):'').'.']);
                }
                if ($scope==='normal' && $name==='brand' && strtolower((string)($attribute['input_type']??''))==='singleselect' && empty($attribute['options'])) {
                    if (!is_string($value)) throw ValidationException::withMessages(['brand'=>'Select a Lazada brand.']);
                    app(LazadaBrands::class)->validate($token,$value);
                } else LazadaAttributeValue::validate($attribute,$value);
            }
        }
    }
}
