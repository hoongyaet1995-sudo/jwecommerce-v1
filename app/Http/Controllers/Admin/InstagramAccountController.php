<?php
namespace App\Http\Controllers\Admin;
class InstagramAccountController extends FacebookAccountController {
 protected string $platform='instagram';
 public function __construct(\App\Services\Social\InstagramAccountService $instagram){parent::__construct($instagram);}
 public function check(\Illuminate\Http\Request $request,string $id){
  $response=parent::check($request,$id);$data=$response->getData(true);
  foreach(['checkReason','messagingError'] as $field)if(is_string($data['account'][$field]??null))$data['account'][$field]=str_replace(['Facebook','Messenger','Page'],['Instagram','Instagram','account'],$data['account'][$field]);
  return response()->json($data)->header('Cache-Control','no-store');
 }
 public function index(\Illuminate\Http\Request $request){
  $response=parent::index($request);
  return response()->json($response->getData(true)+app(\App\Services\Social\SocialHubOAuth::class)->permitted('instagram'))->header('Cache-Control','no-store');
 }
}
