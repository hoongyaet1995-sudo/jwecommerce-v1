@extends('marketing.library.layout',['navActive'=>'video-studio'])
@section('title','Video Studio')
@section('subtitle','A dedicated workspace for making videos.')
@section('breadcrumb')<span>Video Studio</span>@endsection
@section('content')<div class="placement"><h2>Make videos. Keep every asset.</h2><p>Projects and templates belong to the production workspace. Their images, candidates, clips and finished videos automatically appear in Content Library.</p><div class="placement-grid"><div><i class="bi bi-camera-video"></i><h3>Video projects</h3><p>Continue a story, review scenes and manage film versions.</p><a class="secondary" href="{{ route('marketing.index') }}">Open video projects</a></div><div><i class="bi bi-grid"></i><h3>Video templates</h3><p>Choose a starting point for a new video project.</p><a class="secondary" href="{{ route('marketing.templates.entry') }}">Browse video templates</a></div></div></div>@endsection
