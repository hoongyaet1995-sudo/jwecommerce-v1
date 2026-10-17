<?php
namespace App\Http\Controllers\Admin;

use App\Domain\Cms\PageDocumentContract;
use App\Http\Controllers\Controller;
use App\Services\AI\CmsAiGeneratorService;
use App\Services\AI\ProviderFailure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class CmsAiGeneratorController extends Controller
{
    public function metadata(\App\Services\AI\CmsAiStoreContext $context){return response()->json(['data'=>$context->metadata()]);}
    public function __invoke(Request $request, CmsAiGeneratorService $service, PageDocumentContract $contract)
    {
        $input=$request->validate(['action'=>'required|in:brief,answer,generate,refine,image,quality','document'=>'required|array','direction'=>'required|string|max:25000','target'=>'nullable|string|max:180','token'=>'nullable|uuid','answers'=>'sometimes|array','mode'=>'sometimes|in:create,improve,section','sourcePage'=>'nullable|string|max:80','sectionId'=>'required_if:action,image|string|max:180','imageTarget'=>'required_if:action,image|string|max:250','imagePrompt'=>'required_if:action,image|string|max:6000','imageModel'=>'required_if:action,image|string|max:150','quality'=>'required_if:action,image|in:medium,high','ratio'=>'required_if:action,image|in:16:9,4:3,1:1','requestId'=>'required_if:action,image|uuid','referenceUrl'=>'nullable|string|max:1500']);
        $raw=json_decode($request->getContent());
        abort_unless(($raw->document??null) instanceof \stdClass,422,'The document must be a JSON object.');
        $document=$contract->validate(app(\App\Domain\Cms\PageDocumentJsonCodec::class)->normalizeDocumentToken($raw->document));
        abort_if(count($document['sections'])>12,422,'Use up to 12 sections per generation.');
        $target=$input['target']??null;
        if($target)abort_unless(collect($document['sections'])->contains('id',$target),422,'Select an available section.');
        $key='cms-ai:'.$request->user()->getAuthIdentifier().':';
        try {
            if($input['action']==='quality')return response()->json(['data'=>['issues'=>app(\App\Services\AI\CmsAiQuality::class)->review($document)]]);
            if($input['action']==='image'){
                $confirmed=Cache::get($key.($input['token']??''));
                abort_unless($confirmed&&empty($confirmed['brief']['questions']),422,'Confirm a complete Brief before generating images.');
                $result=app(\App\Services\AI\CmsAiImageService::class)->generate($document,$input,(int)$request->user()->getAuthIdentifier(),$confirmed);
                $contract->validate($result['document']);
                return response()->json(['data'=>$result]);
            }
            if(in_array($input['action'],['brief','answer'],true)){
                $history=[];
                $facts=app(\App\Services\AI\CmsAiStoreContext::class)->facts($input['sourcePage']??null);
                $compose=($input['mode']??'improve')==='create';
                if($compose)$document=app(\App\Services\AI\CmsAiSectionCatalog::class)->seed($document,$facts,$input['direction']);
                $contract->validate($document);
                if($input['action']==='answer'){
                    $previous=Cache::get($key.($input['token']??''));
                    abort_unless($previous&&$previous['target']===$target,422,'The question session expired. Prepare the Brief again.');
                    $compose=$previous['compose']??false;
                    $answers=(new \App\Services\AI\CmsAiBriefContract)->answers($previous['brief']['questions'],$input['answers']??[]);
                    $history=$previous['history']??[];
                    foreach($previous['brief']['questions'] as $q)$history[]=['question'=>$q['question'],'answer'=>$answers[$q['id']]];
                    $input['direction']=($previous['baseDirection']??$previous['direction']).'\nConfirmed merchant answers: '.json_encode(array_slice($history,-24),JSON_UNESCAPED_UNICODE);
                    abort_if(strlen($input['direction'])>25000,422,'The requirements are too long. Shorten them before continuing.');
                }
                $brief=$service->brief($target?array_values(array_filter($document['sections'],fn($s)=>$s['id']===$target)):$document['sections'],$input['direction'].'\nVerified store facts: '.json_encode($facts,JSON_UNESCAPED_UNICODE),$compose);
                if($compose){$byId=array_column($document['sections'],null,'id');$document['sections']=array_map(function($plan)use($byId){$s=$byId[$plan['id']];if(isset($plan['layout'])){$layout=app(\App\Services\AI\CmsAiCapabilities::class)->layout($s['type']);if(!in_array($plan['layout'],$layout['values']??[],true))throw \Illuminate\Validation\ValidationException::withMessages(['brief'=>'Unsupported AI layout.']);$s[$layout['field']]=$plan['layout'];}if(isset($plan['surface'])){if(!in_array($plan['surface'],app(\App\Services\AI\CmsAiCapabilities::class)->surfaces($s['type']),true))throw \Illuminate\Validation\ValidationException::withMessages(['brief'=>'This layout does not support that surface.']);if($plan['surface']==='default')unset($s['surface']);else $s['surface']=$plan['surface'];}return $s;},$brief['sections']);}
                $token=(string)Str::uuid();
                Cache::put($key.$token,['document'=>$document,'direction'=>$input['direction'],'baseDirection'=>$previous['baseDirection']??$input['direction'],'target'=>$target,'brief'=>$brief,'history'=>$history,'facts'=>$facts,'compose'=>$compose],now()->addMinutes(30));
                return response()->json(['data'=>['brief'=>$brief,'document'=>$document,'token'=>$token,'direction'=>$input['direction'],'answerHistory'=>$history]]);
            }
            if($input['action']==='generate'){
                $confirmed=Cache::get($key.($input['token']??''));
                abort_unless($confirmed&&json_encode($confirmed['document'])===json_encode($document)&&$confirmed['direction']===$input['direction']&&$confirmed['target']===$target,422,'The Brief changed or expired. Review it again.');
                abort_if(!empty($confirmed['brief']['questions']),422,'Answer the open Brief questions before generating.');
                $candidateKey=$key.$input['token'].':candidate';
                if($candidate=Cache::get($candidateKey))return response()->json(['data'=>['document'=>$candidate]]);
            }
            $facts=$confirmed['facts']??app(\App\Services\AI\CmsAiStoreContext::class)->facts($input['sourcePage']??null);
            $plan=$input['action']==='generate'?'\nConfirmed section tasks: '.json_encode($confirmed['brief']['sections'],JSON_UNESCAPED_UNICODE):'';
            $lock=$input['action']==='generate'?Cache::lock($key.$input['token'].':generating',45):null;
            if($lock)abort_unless($lock->get(),409,'This draft is still generating. Wait for the current request.');
            try{
                if($lock&&($candidate=Cache::get($candidateKey)))return response()->json(['data'=>['document'=>$candidate]]);
                $document['sections']=$service->write($document['sections'],$input['direction'].$plan.'\nVerified store facts: '.json_encode($facts,JSON_UNESCAPED_UNICODE),$target,$input['action']==='generate');
                $contract->validate($document);
                if($lock)Cache::put($candidateKey,$document,now()->addMinutes(30));
            }finally{$lock?->release();}
            return response()->json(['data'=>['document'=>$document]]);
        } catch(ProviderFailure $failure){return response()->json(['message'=>$failure->getMessage()],502);}
    }
}
