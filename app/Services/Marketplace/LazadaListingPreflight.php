<?php
namespace App\Services\Marketplace;

use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Current category, required-field and supported attribute-value checks. */
final class LazadaListingPreflight
{
    public function __construct(private LazadaClient $client) {}

    public function validate(#[\SensitiveParameter] string $token, array $payload, ?int $storeId=null): void
    {
        $product=$payload['Request']['Product'] ?? [];
        $id=$product['PrimaryCategory'] ?? null;
        if ((!is_int($id)&&!is_string($id)) || !preg_match('/^[1-9][0-9]*$/D',(string)$id)) {
            throw ValidationException::withMessages(['category'=>'Select a Lazada leaf category.']);
        }
        $categories=LazadaListingCategories::flatten($this->client->read('/category/tree/get',$token,[],$storeId));
        if (!collect($categories)->contains(fn($row)=>(string)$row['category_id']===(string)$id && !$row['has_children'])) {
            throw ValidationException::withMessages(['category'=>'This category is no longer an available leaf category.']);
        }
        $schema=$this->client->read('/category/attributes/get',$token,['primary_category_id'=>(string)$id],$storeId);
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
            $label=$attribute['label'] ?? '';
            $isSizeChart=$scope==='normal' && (
                preg_match('/size[ _-]*chart/i',$name)===1
                || (is_string($label) && preg_match('/size[ _-]*chart/i',$label)===1)
            );
            if ($isSizeChart && array_key_exists('jw_size_chart',$payload)) {
                $sizeChart=$payload['jw_size_chart'];
                if (!is_string($sizeChart) || filter_var($sizeChart,FILTER_VALIDATE_URL)===false
                    || !str_ends_with(strtolower((string)parse_url($sizeChart,PHP_URL_HOST)),'.slatic.net')) {
                    throw ValidationException::withMessages(['size_chart'=>'Select a valid Lazada size chart image.']);
                }
                continue;
            }
            $targets=$scope==='sku'?$skus:[$product['Attributes'] ?? []];
            foreach ($targets as $index=>$target) {
                $value=$name==='__images__'?($target['Images']['Image'] ?? null):($target[$name] ?? null);
                if ($value===null || (is_string($value)&&trim($value)==='') || $value===[]) {
                    if (!$required) continue;
                    throw ValidationException::withMessages(['attributes'=>"Complete required {$scope} field {$name}".($scope==='sku'?' for variant '.($index+1):'').'.']);
                }
                if ($scope==='normal' && $name==='brand' && strtolower((string)($attribute['input_type']??''))==='singleselect' && empty($attribute['options'])) {
                    if (!is_string($value)) throw ValidationException::withMessages(['brand'=>'Select a Lazada brand.']);
                    app(LazadaBrands::class)->validate($token,$value,$storeId);
                } else LazadaAttributeValue::validate($attribute,$value);
            }
        }
    }
}
