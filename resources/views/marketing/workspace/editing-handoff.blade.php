<section class="ws-card mb-4" aria-label="来自剪辑助手的需求交接"><span class="ws-badge">来自成片 · 需求已带入</span><h2 class="mt-3">先检查分镜与素材，再决定重新制作</h2><p style="white-space:pre-wrap">{{ $editingHandoff['request'] }}</p>
<ol><li>检查受影响的分镜、导演设计与原台词。</li><li>确认人物、商品或场景的真实参考，准备对应生产素材。</li><li>再确认受影响片段的生成范围与费用。</li></ol>
<div class="ws-tip">本轮已完成真实需求交接，重新生成在下一轮接入。当前成片与素材保留；进入本页不自动生成或扣费。{{ $editingHandoff['current']?'':'此需求的来源版本已变化，请先重新核对。' }}</div>
<a class="btn btn-light mt-3" href="{{ route('marketing.workspace.show',['project'=>$project->id,'screen'=>'finished','film'=>$editingHandoff['base_film_id']]) }}">返回这条需求对应的成片</a></section>
