@if($live && $project)
<div class="mt-3">
 <h3>导演镜头设计</h3>
 <p class="text-secondary small">先看故事分段，再审阅镜头表达和素材责任。这里只准备内容，未授权制作。</p>
 @if(!$revision)
 <p class="ws-note">先生成上方选定故事的分镜与素材计划。</p>
 @elseif(!isset($revision->document['director_plan']))
 <button class="btn ws-primary w-100" data-creative-action="director">设计镜头与素材安排 <i class="bi bi-arrow-right"></i></button>
 @else
 @php($director=$revision->document['director_plan'])
 <button type="button" class="btn ws-primary w-100" data-bs-toggle="modal" data-bs-target="#directorWalkthrough"><i class="bi bi-camera-reels me-2"></i>查看导演稿 <span class="badge bg-light text-dark ms-2">{{ count($director['shots']) }} 段</span></button>
 @include('marketing.workspace.director-walkthrough')
 @include('marketing.workspace.material-package')
 <p class="ws-note mt-3">@if(isset($revision->context['director_review']))镜头设计已采用；素材与制作费用仍需独立确认。@else请先审阅镜头设计。采用设计不会生成或扣取媒体制作费用。@endif</p>
 <button class="btn ws-primary w-100 mt-2" data-creative-action="confirm_director" @disabled(isset($revision->context['director_review']))>采用这份镜头设计</button>
 <button class="btn btn-outline-secondary w-100 mt-2" data-creative-action="director">重新设计镜头（文字 AI）</button>
 @endif
</div>
@endif
