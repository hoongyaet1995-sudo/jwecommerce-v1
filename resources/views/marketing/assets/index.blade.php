@extends('marketing.assets.shell')
@section('title','Asset')
@section('content')


<div class="library-breadcrumb"><b>Asset</b></div>
<header class="library-heading"><div><span class="eyebrow">创作资产</span><h1>把素材与人物，放在合适的地方</h1><p class="quiet">管理 AI 生成的素材，以及故事里会用到的人物模板。</p></div></header>
<section class="asset-navigators" aria-label="Asset 页面导航">
<a class="asset-navigator" href="{{ route('marketing.assets.media') }}"><span class="asset-nav-icon"><i class="bi bi-collection-play"></i></span><div class="asset-nav-title"><h2>Media</h2><span class="asset-future">后续接入</span></div><p>收藏 AI 生成的图片、声音与视频，让每次创作的素材都能再次使用。</p><div class="asset-types"><span><i class="bi bi-image"></i> Image</span><span><i class="bi bi-soundwave"></i> Audio</span><span><i class="bi bi-camera-video"></i> Video</span></div><span class="asset-nav-action">查看 Media 规划 <i class="bi bi-arrow-right"></i></span></a>
<a class="asset-navigator" href="{{ route('marketing.avatars') }}"><span class="asset-nav-icon"><i class="bi bi-person-vcard"></i></span><div class="asset-nav-title"><h2>Avatar</h2></div><p>准备可复用的数字人模板。在分镜中选定主人物，沿用同一身份与造型。</p><div class="asset-types"><span><i class="bi bi-person-check"></i> 人物参考</span><span><i class="bi bi-layers"></i> 固定版本</span></div><span class="asset-nav-action">管理人物模板 <i class="bi bi-arrow-right"></i></span></a>
</section>
<p class="quiet library-footnote">Media 用于收藏生成素材；Avatar 用于管理可被 Step 3 选择的人物模板。</p>

@endsection