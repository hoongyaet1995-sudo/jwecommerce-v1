@extends('marketing.assets.shell')
@section('title','Asset')
@section('content')


<div class="library-breadcrumb"><b>Asset</b></div>
<header class="library-heading"><div><span class="eyebrow">Creative assets</span><h1>Keep your media and avatars organized</h1><p class="quiet">Manage AI-generated media and avatar templates for your stories.</p></div></header>
<section class="asset-navigators" aria-label="Asset navigation">
<a class="asset-navigator" href="{{ route('marketing.assets.media') }}"><span class="asset-nav-icon"><i class="bi bi-collection-play"></i></span><div class="asset-nav-title"><h2>Media</h2><span class="asset-future">Coming soon</span></div><p>Save AI-generated images, audio and video to reuse in future projects.</p><div class="asset-types"><span><i class="bi bi-image"></i> Image</span><span><i class="bi bi-soundwave"></i> Audio</span><span><i class="bi bi-camera-video"></i> Video</span></div><span class="asset-nav-action">View Media plans <i class="bi bi-arrow-right"></i></span></a>
<a class="asset-navigator" href="{{ route('marketing.avatars') }}"><span class="asset-nav-icon"><i class="bi bi-person-vcard"></i></span><div class="asset-nav-title"><h2>Avatar</h2></div><p>Prepare reusable avatar templates. Select a lead character in the storyboard to keep the same identity and look.</p><div class="asset-types"><span><i class="bi bi-person-check"></i> Character references</span><span><i class="bi bi-layers"></i> Fixed versions</span></div><span class="asset-nav-action">Manage avatar templates <i class="bi bi-arrow-right"></i></span></a>
</section>
<p class="quiet library-footnote">Media stores generated assets; Avatar manages character templates available in Step 3.</p>

@endsection