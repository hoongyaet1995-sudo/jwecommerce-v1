<?php
namespace App\Services;

use App\Models\{Category, Product, ProductImage, ProductVariant};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Unsaved presentation models. No catalogue, stock or customer records are created. */
final class WebsiteThemeDemoData
{
    public const SCENARIOS = ['all-products','plp','filter','quick-buy','pdp','cart-drawer','login','register','forgot-password','reset-password','my-account','account-orders','account-vouchers','account-addresses','account-profile','account-saved-items','shopping-cart','checkout','quotation-checkout','checkout-success','payment-pending','order-tracking','order-tracking-verified','review-dialog','return-dialog'];

    /** Unsaved quotation checkout fixture; only consumed by the protected demo renderer. */
    public function quotationCheckout(array $catalog): array
    {
        $product = $catalog['demoProducts']->first();
        $document = [
            'merchant'=>app(\App\Support\ShopBrand::class)->name(),
            'number'=>'DEMO-QT-100328', 'title'=>'Agreed quotation',
            'customer'=>['name'=>'Alex Tan','email'=>'sample@example.test'],
            'currency'=>['code'=>'MYR','left'=>'RM ','right'=>''],
            'items'=>[['name'=>$product->title,'description'=>'Agreed scope · Demo only','qty'=>'2','price'=>'100.00','amountMinor'=>20000]],
            'totalMinor'=>20000, 'notes'=>'Demonstration only. No payment will be processed.',
        ];
        $version = new \App\Models\EnquiryQuotationVersion([
            'id'=>'00000000-0000-4000-8000-000000000001','revision'=>1,'document'=>$document,
        ]);
        return [
            'quotationCheckout'=>true, 'version'=>$version,
            'acceptance'=>(object)['accepted_at'=>now()], 'order'=>null,
            'checkoutEmail'=>'sample@example.test','savedAddresses'=>[],
            'methods'=>['bank_transfer'=>['label'=>'Bank transfer','bankTransfer'=>[
                'bankAccounts'=>[['id'=>'demo-bank','bankName'=>'Sample bank · Demo only','accountNumber'=>'DEMO-0000']],
            ]]],
        ];
    }

    public function cart(array $catalog): array
    {
        $items=$catalog['demoProducts']->filter(fn($p)=>$p->isBuyMode())->take(2)->map(fn($p)=>['variant'=>$p->variants->first(),'quantity'=>1,'available'=>25]);
        $subtotal=$items->sum(fn($i)=>$i['variant']->price*$i['quantity']);
        return ['items'=>$items,'coupon'=>null,'summary'=>['subtotal'=>$subtotal,'discount'=>0,'shipping'=>800,'tax'=>0,'total'=>$subtotal+800,'tax_quote'=>['status'=>'not_configured']], 'shippingEstimateAvailable'=>true,'shippingOption'=>null,'shippingProgressThreshold'=>null,'addons'=>collect()];
    }

    public function order(array $catalog, bool $paid): array
    {
        $home=in_array($catalog['demoTheme']??'food',['home_deco','beauty','tech','baby','jewelry','wedding','fashion','universal'],true);
        $subtotal=$catalog['demoProducts']->take(2)->sum('base_price');
        $order=new \App\Models\Order(['number'=>'DEMO-JW100328','currency'=>'MYR','currency_exchange_rate'=>1,'status'=>$paid?'processing':'pending_payment','subtotal'=>$subtotal,'discount_amount'=>0,'shipping_amount'=>800,'payment_surcharge_amount'=>0,'tax_amount'=>0,'total'=>$subtotal+800]);
        $items=$catalog['demoProducts']->filter(fn($p)=>$p->isBuyMode())->take(2)->map(fn($p)=>new \App\Models\OrderItem(['title'=>$p->title,'variant_title'=>$p->variants->first()->title,'quantity'=>1,'unit_price'=>$p->base_price,'line_total'=>$p->base_price,'image_url'=>$p->images->first()->path]));
        $order->setRelation('items',$items)->setRelation('payments',collect([new \App\Models\OrderPayment(['gateway'=>in_array($catalog['demoTheme']??'',['tech','baby','jewelry','wedding','fashion','universal'],true)&&$paid?'online':'bank_transfer','status'=>$paid?'paid':'pending','configuration_snapshot'=>in_array($catalog['demoTheme']??'',['baby','jewelry','wedding','fashion','universal'],true)&&!$paid?['bankAccount'=>['bankName'=>'Sample bank','accountNumber'=>'1234 5678 9012','accountHolder'=>'JW STUDIO DEMO']]:null])]))->setRelation('addresses',collect([new \App\Models\OrderAddress(['type'=>'shipping','first_name'=>$home?'Alex':'Sample','last_name'=>$home?'Tan':'Customer','line_1'=>$home?'12 Jalan Harmoni':'12 Sample Street','city'=>'Kuala Lumpur','postal_code'=>'50000','country'=>'MY'])]));
        if(($catalog['demoTheme']??'')==='wedding'&&!$paid){$order->payments->first()->configuration_snapshot=['bankAccount'=>['bankName'=>'DEMO-ONLY BANK','accountNumber'=>'DEMO-0000','accountHolderName'=>'Sample studio'],'verificationInstructions'=>['Demonstration only. Do not transfer money.'],'quietDisclosure'=>'A receipt upload does not confirm payment.'];}
        return ['order'=>$order,'paymentProof'=>$home&&!$paid?['proofStatus'=>'missing','originalFileName'=>null,'rejectionReason'=>null,'canUpload'=>true,'orderId'=>-1,'paymentId'=>-1]:null,'taxLine'=>null,'recommendations'=>$catalog['demoProducts']->take(3)];
    }

    public function account(array $catalog): array
    {
        $data=WebsiteThemeAccountPreview::empty();
        $item=['id'=>-1,'productVariantId'=>-1000,'title'=>$catalog['demoProducts']->first()->title,'variantTitle'=>'500 ml','imageUrl'=>asset('images/themes/food/olive-oil.png'),'quantity'=>1,'unitPrice'=>6800,'lineTotal'=>6800,'sku'=>'DEMO-OIL','reviews'=>[],'reviewStatus'=>null,'returnRequest'=>null,'returnEligibleUntil'=>'2099-01-01'];
        $data['orders']=[['id'=>-1,'number'=>'DEMO-JW100328','createdAt'=>'2026-10-01T10:00:00','status'=>'delivered','currency'=>'MYR','currencyExchangeRate'=>1,'subtotal'=>6800,'discountAmount'=>0,'shippingAmount'=>800,'taxAmount'=>0,'shippingMethod'=>'standard','shippingLabel'=>'Standard delivery','shippedAt'=>'2026-10-02T10:00:00','addresses'=>[],'payments'=>[],'total'=>7600,'trackingCarrier'=>'Sample courier','trackingNumber'=>'DEMO-12345','paymentProof'=>null,'items'=>[$item]]];
        $data['addresses']=[['id'=>-1,'label'=>'Home','firstName'=>'Sample','lastName'=>'Customer','phone'=>'0123456789','line1'=>'12 Sample Street','line2'=>'','street'=>'12 Sample Street','apartment'=>'','city'=>'Kuala Lumpur','state'=>'Kuala Lumpur','postalCode'=>'50000','country'=>'MY','isDefault'=>true,'isDefaultShipping'=>true]];
        $data['wishlist']=$catalog['demoProducts']->take(3)->map(fn($p)=>['product'=>['id'=>$p->id,'handle'=>$p->handle,'title'=>$p->title,'images'=>[['path'=>$p->images->first()->path]],'variants'=>[['price'=>$p->base_price]],'basePrice'=>$p->base_price]])->all();
        $data['dashboard']=['orderCount'=>1,'activeLogisticsCount'=>0];
        $data['returnPolicy']['enabled']=true;
        $data['vouchers']=['eligible'=>[
            ['id'=>-1,'code'=>'PANTRY10','description'=>'10% off selected pantry essentials.','minSpendDisplay'=>'Minimum spend RM 30.00','status'=>'eligible','statusLabel'=>'Available','discountType'=>'percentage','title'=>'Pantry essentials','value'=>10,'discountDisplay'=>'10%','minSpend'=>3000,'categoryRestriction'=>null],
            ['id'=>-2,'code'=>'DELIVERY','description'=>'Free standard delivery on qualifying orders.','minSpendDisplay'=>'Minimum spend RM 50.00','status'=>'eligible','statusLabel'=>'Available','discountType'=>'free_shipping','title'=>'Standard delivery','value'=>0,'discountDisplay'=>'Free shipping','minSpend'=>5000,'categoryRestriction'=>null],
        ],'used'=>[],'expired'=>[],'counts'=>['eligible'=>2,'used'=>0,'expired'=>0]];
        if (($catalog['demoTheme']??'food')==='home_deco') $data=app(HomeThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'food')==='baby') $data=app(BabyThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'food')==='tech') $data=app(TechThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'food')==='beauty') $data=app(BeautyThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'')==='universal') $data=app(UniversalThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'')==='fashion') $data=app(FashionThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'')==='jewelry') $data=app(JewelryThemeDemoData::class)->account($catalog,$data);
        if (($catalog['demoTheme']??'')==='wedding') $data=app(WeddingThemeDemoData::class)->account($catalog,$data);
        return $data;
    }
    public function catalog(Request $request): array
    {
        $catalog = $this->catalogData($request);
        $mode = $request->query('purchaseMode', 'buy');
        $display = $request->query('priceDisplay', 'exact');
        abort_unless(in_array($mode, ProductPurchaseSettings::MODES, true) && in_array($display, ProductPurchaseSettings::PRICE_DISPLAYS, true), 422);
        foreach ($catalog['demoProducts'] as $product) {
            if ($product->exists) throw new \LogicException('Purchase previews require unsaved products.');
            if(($catalog['demoTheme']??'')!=='wedding'||$request->has('purchaseMode')) $product->purchase_mode = $mode;
            if(($catalog['demoTheme']??'')!=='wedding'||$request->has('priceDisplay')) $product->price_display = $display;
            foreach ($product->variants as $variant) $variant->setRelation('product', $product);
        }
        return $catalog;
    }

    private function catalogData(Request $request): array
    {
        if ($request->input('theme')==='wedding') return app(WeddingThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='home_deco') return app(HomeThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='universal') return app(UniversalThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='fashion') return app(FashionThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='jewelry') return app(JewelryThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='baby') return app(BabyThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='tech') return app(TechThemeDemoData::class)->catalog($request);
        if ($request->input('theme')==='beauty') return app(BeautyThemeDemoData::class)->catalog($request);
        $category = new Category(['name'=>'Good things for your everyday table.','slug'=>'demo-pantry','description'=>'Thoughtfully selected pantry staples for simpler, happier meals at home.','image_url'=>asset('images/themes/food/hero.png')]);
        $category->id = -1;
        $products = collect([
            ['Tuscan Extra Virgin Olive Oil','olive-oil',6800], ['Classic Tomato Passata','passata',1800],
            ['House Blend Coffee Beans','coffee',4800], ['Penne Rigate Pasta','pasta',1200],
            ['Wildflower Honey','honey',3800], ['Jasmine Green Tea','tea',2800],
        ])->map(function ($row, $index) use ($category) {
            $product = new Product(['title'=>$row[0],'handle'=>'theme-demo-'.$row[1],'brand'=>'JW STUDIO','status'=>'active','subtitle'=>'Thoughtfully sourced pantry essentials for simple, flavourful everyday meals.','base_price'=>$row[2], 'description'=>'Thoughtfully sourced ingredients for everyday meals.','options'=>[['name'=>'Size','values'=>['250 ml','500 ml'],'displayType'=>'pills']]]);
            $product->id = -100-$index;
            $images = collect([$row[1], 'story'])->map(function ($image, $position) use ($product) {
                $model = new ProductImage(['path'=>asset('images/themes/food/'.$image.'.png'),'alt'=>$product->title,'position'=>$position]);
                $model->id=-1000-$position; return $model;
            });
            $variants=collect(['250 ml','500 ml'])->map(function ($size,$position) use ($product,$row) {
                $variant=new ProductVariant(['title'=>$size,'sku'=>'DEMO-'.$row[1].'-'.$position,'price'=>$row[2]+$position*1000,'stock'=>25,'enabled'=>true,'options'=>['Size'=>$size]]);
                $variant->id=$product->id*10-$position;
                $variant->setRelation('product',$product);
                return $variant;
            });
            $content=new \App\Models\ProductPdpContent(['story'=>['label'=>'A MORE THOUGHTFUL PANTRY','heading'=>'Everyday goodness','description'=>'Good food starts with simple, quality ingredients. Chosen for authentic flavour and versatility — a pantry essential for brighter, more delicious days.','image'=>asset('images/themes/food/story.png')],'specifications'=>[['label'=>'Origin','value'=>'Italy'],['label'=>'Volume','value'=>'500 ml']]]);
            return $product->setRelation('category',$category)->setRelation('images',$images)->setRelation('variants',$variants)->setRelation('pdpContent',$content)->setRelation('reviews',collect())->setRelation('pdpRelatedProducts',collect());
        });
        // Identity check prevents this presentation-only projection from affecting real models.
        $request->attributes->set('websiteThemeDemoVariants',$products->flatMap(fn($p)=>$p->variants)->all());
        $request->attributes->set('websiteThemeDemoProducts',$products);
        $sort=$request->input('sort','');
        $min=max(1200,$request->integer('min_price',1200)); $max=min(7800,$request->integer('max_price',7800));
        $visible=$products->filter(fn($p)=>$p->base_price >= $min && $p->base_price <= $max);
        if($sort==='price_asc')$visible=$visible->sortBy('base_price');
        if($sort==='price_desc')$visible=$visible->sortByDesc('base_price');
        $filters=['search'=>'','category'=>null,'sort'=>$sort,'layout'=>'grid','minPrice'=>$min,'maxPrice'=>$max,'boundsMinPrice'=>1200,'boundsMaxPrice'=>7800,'priceRequested'=>$min!==1200||$max!==7800,'inStock'=>false,'colors'=>[],'materials'=>[],'minRating'=>0,'variantFiltered'=>false];
        $facets=['priceBounds'=>['min'=>1200,'max'=>7800],'total'=>6,'categoryCounts'=>[-1=>6],'colors'=>[],'materials'=>[],'hasRatings'=>false,'ratings'=>[]];
        return (new CatalogFilterResult($filters,new LengthAwarePaginator($visible->values(),$visible->count(),12),$facets))->toViewData()+[
            'categories'=>collect([$category]),'collection'=>null,'listingCategory'=>$category,
            'catalogListingSettings'=>['title'=>'Everyday essentials','description'=>$category->description],
            'listingRouteName'=>'admin.website-design.preview','listingRouteParameters'=>['theme'=>$request->input('theme'),'surface'=>'plp'],
            'demoProducts'=>$products,
        ];
    }
}
