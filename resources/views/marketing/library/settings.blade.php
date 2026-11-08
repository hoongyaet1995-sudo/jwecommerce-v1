@extends('marketing.library.layout',['navActive'=>'settings'])
@section('title','Settings')
@section('subtitle','Manage email delivery and reusable avatar settings.')
@section('breadcrumb')<span>Settings</span>@endsection
@section('content')<div class="settings-grid"><a class="settings-card" href="{{ route('marketing.settings.email') }}"><span class="settings-icon"><i class="bi bi-envelope"></i></span><h2>Email</h2><p>Manage your sending account, sender details and email delivery settings.</p><span class="settings-action">Open Email settings <i class="bi bi-arrow-right"></i></span></a><a class="settings-card" href="{{ route('marketing.avatars') }}"><span class="settings-icon"><i class="bi bi-person-vcard"></i></span><h2>Avatar</h2><p>Manage reusable character references, appearance versions and usage permissions.</p><span class="settings-action">Open Avatar settings <i class="bi bi-arrow-right"></i></span></a></div>@endsection
