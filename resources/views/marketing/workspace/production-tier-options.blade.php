<div class="pt-tier-grid" role="radiogroup" aria-label="制作档位">
@foreach(['ordinary'=>['普通','日常内容、创意测试，先把故事讲清楚。','优先经济模型','使用必要的最少素材','保留人物与故事要求'],'advanced'=>['高级','更重视人物一致性与复杂动作的表现。','按镜头匹配合适的模型','必要时控制开始与结束','优先复用已确认画面'],'professional'=>['专业级','对重要镜头提供更精细的视觉控制。','优先经过验收的表现路线','复杂镜头使用参考素材','完整核对声音与合成']] as $tier=>$copy)
<label class="pt-tier-card"><input type="radio" name="production_tier" value="{{ $tier }}" @checked(($selectedTier??'ordinary')===$tier)><strong>{{ $copy[0] }}</strong><p>{{ $copy[1] }}</p><ul><li>{{ $copy[2] }}</li><li>{{ $copy[3] }}</li><li>{{ $copy[4] }}</li></ul></label>
@endforeach
</div><p class="pt-tier-note">三个档位都完整表达你的故事。档位不等于固定模型、图片数量或生成次数。进入制作前，你会看到实际方案与费用。</p>
