<?php
namespace App\Services\Marketplace;

use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class LazadaBrands
{
    public function __construct(private LazadaClient $client) {}

    public function page(#[\SensitiveParameter] string $token, int $offset, ?int $storeId=null): array
    {
        if ($offset<0 || $offset>2147483647) throw new RuntimeException('Invalid brand page.');
        $page=$this->client->read('/category/brands/query',$token,['startRow'=>(string)$offset,'pageSize'=>'200'],$storeId);
        $rows=$page['module']??null; $total=$page['total_record']??null;
        if (!is_array($rows) || !array_is_list($rows) || count($rows)>200
            || (!is_int($total)&&!ctype_digit((string)$total)) || (int)$total<0) throw new RuntimeException('Incomplete Lazada brand page.');
        $names=[];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['name']??null) || trim($row['name'])===''
                || !preg_match('/^[1-9][0-9]*$/D',(string)($row['brand_id']??''))) throw new RuntimeException('Invalid Lazada brand identity.');
            $names[]=$row['name'];
        }
        foreach ($names as $name) Cache::put($this->key($name),$offset,now()->addDay());
        $next=$offset+count($rows);
        return ['names'=>array_values(array_unique($names)), 'next_offset'=>$rows && $next<(int)$total?$next:null];
    }

    public function validate(#[\SensitiveParameter] string $token, string $name, ?int $storeId=null): void
    {
        // Official CreateProduct contract provides this unbranded value. Seller/category eligibility remains provider-checked.
        if ($name==='No Brand') return;
        $offset=Cache::get($this->key($name));
        if (!is_int($offset) || !in_array($name,$this->page($token,$offset,$storeId)['names'],true)) {
            throw ValidationException::withMessages(['brand'=>'Select a current Lazada brand from the brand list.']);
        }
    }

    private function key(string $name): string
    {
        return 'lazada-brand-page:MY:'.config('marketplace.lazada.app_key').':'.hash('sha256',$name);
    }
}
