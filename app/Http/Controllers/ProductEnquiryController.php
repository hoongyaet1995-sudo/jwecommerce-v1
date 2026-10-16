<?php
namespace App\Http\Controllers;
use App\Models\{Product,ProductEnquiry};
use App\Services\ProductEnquiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
class ProductEnquiryController extends Controller
{
    public function support(Request $request,ProductEnquiry $enquiry,\App\Services\ProductEnquirySupportService $service){
        $service->connect($request,$enquiry);
        return redirect()->route('enquiries.receipt',['enquiry'=>$enquiry,'support'=>1]);
    }
    public function create(Request $request,string $handle){
        abort_unless(config('product_enquiries.enabled'),503,'Enquiries are not accepting new requests at the moment.');
        $product=Product::where('handle',$handle)->where('status','active')->where('purchase_mode','quote')->with(['variants','images'])->firstOrFail();
        $available=$product->variants->where('enabled',true);
        $variant=$available->firstWhere('id',$request->integer('variant'))??$available->first();
        request()->attributes->set('websiteRenderedTheme',app(\App\Services\WebsiteDesignService::class)->surface($product->category_id?'category:'.$product->category_id:null));
        return response()->view('storefront.enquiries.create',['product'=>$product,'selectedVariant'=>$variant])->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow');
    }
    public function store(Request $request,ProductEnquiryService $service){
        try {
            $data=$request->validateWithBag('enquiry',['product_id'=>['required','integer'],'variant_id'=>['nullable','integer'],'request_key'=>['required','uuid'],'name'=>['required','string','max:120'],'email'=>['required','email','max:254'],'phone'=>['nullable','string','max:40'],'requirements'=>['required','string','max:2000']]);
            $enquiry=$service->submit($request,$data);
        } catch(\Illuminate\Validation\ValidationException $e) {
            $e->errorBag='enquiry';
            // Lazy catalog batches may not exist after redirect. Recover at the
            // submitted product's canonical PDP; never accept a client URL.
            if($request->input('enquiry_context')==='product' && filter_var($request->input('product_id'),FILTER_VALIDATE_INT)) {
                $product=Product::whereKey($request->integer('product_id'))->where('status','active')->where('purchase_mode','quote')->first();
                if($product) {
                    $variant=$product->variants()->where('enabled',true)->whereKey($request->integer('variant_id'))->first();
                    $e->redirectTo(route('storefront.products.show',['handle'=>$product->handle]+($variant?['variant'=>$variant->id]:[])));
                }
            }
            throw $e;
        }
        return redirect()->route('enquiries.receipt',$enquiry);
    }
    public function receipt(Request $request,ProductEnquiry $enquiry,ProductEnquiryService $service){
        abort_unless($service->canView($request,$enquiry),404);
        $quotationVersions=\Illuminate\Support\Facades\Schema::hasTable('enquiry_quotation_versions')?\App\Models\EnquiryQuotationVersion::whereHas('quotation',fn($q)=>$q->where('product_enquiry_id',$enquiry->id))->orderByDesc('revision')->get():collect();
        return response()->view('storefront.enquiries.receipt',compact('enquiry','quotationVersions'))->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex, nofollow');
    }
}
