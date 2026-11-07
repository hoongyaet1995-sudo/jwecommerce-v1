@php($publishingAccounts=collect(app(\App\Services\Marketing\MarketingPosts::class)->accounts())->filter(fn($account)=>in_array($account['platform'],['facebook','instagram','tiktok'],true)))
<div class="mt-4 pt-3 border-top">
 <label class="form-label" for="automation-auto-publish">Automatic Publish Status</label>
 <select id="automation-auto-publish" name="auto_publish" class="form-select"><option value="0" @selected(empty($form['auto_publish']))>Off — Save drafts for review</option><option value="1" @selected(!empty($form['auto_publish']))>On — Publish when generation finishes</option></select>
 <p class="field-note mt-2">At the scheduled time, AI starts generating. When complete, posts enter the publishing queue for the accounts below. Manual Generate Post always saves drafts.</p>
 <div id="automation-publish-accounts" @if(empty($form['auto_publish'])) hidden @endif>
  <fieldset><legend class="form-label">Publishing accounts</legend>
  <p class="field-note">Select the actual accounts to publish to. Other platform versions remain drafts.</p>
  <div class="row g-3">
  @forelse($publishingAccounts as $account)
   @php($accountOptions=$form['publish_options'][$account['id']]??[])
   <div class="col-12"><div class="border rounded-3 p-3">
    <label class="d-flex gap-2 align-items-center"><input type="checkbox" name="publish_targets" value="{{ $account['id'] }}" @checked(in_array($account['id'],$form['publish_targets']??[],true)) @disabled(!$account['canSchedule'])><i class="bi bi-{{ $account['platform'] }}" aria-hidden="true"></i><strong>{{ $account['name'] }}</strong><span class="small text-muted">{{ ucfirst($account['platform']) }}</span></label>
    @if(!$account['canSchedule'])<p class="field-note mt-2 mb-0">Publishing access is unavailable. Review this account in Channels.</p>@endif
    @if($account['platform']==='tiktok')
    <div data-publish-details="{{ $account['id'] }}" class="mt-3">
     <label class="form-label" for="publish-privacy-{{ $account['id'] }}">TikTok visibility</label><select class="form-select" id="publish-privacy-{{ $account['id'] }}" data-account="{{ $account['id'] }}" data-publish-option="privacy"><option value="">Choose visibility</option>@foreach(['SELF_ONLY'=>'Only me','PUBLIC_TO_EVERYONE'=>'Everyone','MUTUAL_FOLLOW_FRIENDS'=>'Friends','FOLLOWER_OF_CREATOR'=>'Followers'] as $privacy=>$label)<option value="{{ $privacy }}" @selected(($accountOptions['privacy']??'')===$privacy)>{{ $label }}</option>@endforeach</select>
     <div class="row g-3 mt-1">@foreach(['allow_comment'=>'Allow comments','commercial'=>'Commercial content','your_brand'=>'Your brand','branded'=>'Branded content'] as $flag=>$label)<div class="col-12 col-sm-6"><label class="d-flex align-items-center gap-2"><input type="checkbox" data-account="{{ $account['id'] }}" data-publish-option="{{ $flag }}" @checked($accountOptions[$flag]??false)>{{ $label }}</label></div>@endforeach</div>
     <label class="d-flex align-items-start gap-2 mt-3"><input type="checkbox" data-account="{{ $account['id'] }}" data-publish-option="terms" @checked($accountOptions['terms']??false)><span>I confirm TikTok’s <a href="https://www.tiktok.com/legal/page/global/music-usage-confirmation/en" target="_blank" rel="noopener noreferrer">Music Usage Confirmation</a> and approve these publishing choices for this automation.</span></label>
     <p class="field-note mt-2 mb-0">Visibility and disclosure must also be allowed by your connected TikTok account when publishing.</p>
    </div>
    @endif
   </div></div>
  @empty<div class="col-12"><p class="field-note">No connected publishing accounts. Connect an account in Channels before enabling automatic publishing.</p></div>@endforelse
  </div></fieldset>
 </div>
</div>
