<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Services\CmsPaletteLibrary;
use Illuminate\Http\Request;
final class CmsPaletteLibraryController extends Controller
{
    public function index(CmsPaletteLibrary $library){return response()->json(['data'=>$library->all()]);}
    public function save(Request $request,CmsPaletteLibrary $library){
        $rules=['id'=>'required|uuid','name'=>'required|string|max:40','primary'=>'required|regex:/^#[a-fA-F0-9]{6}$/','secondary'=>'present|nullable|regex:/^#[a-fA-F0-9]{6}$/','colors'=>'required|array:paper,ink,muted,accent,light,secondary,secondaryInk,hover'];
        foreach(['paper','ink','muted','accent','light','secondary','secondaryInk','hover'] as $key)$rules['colors.'.$key]='required|regex:/^#[a-fA-F0-9]{6}$/';
        $input=$request->validate($rules);$input['name']=trim($input['name']);if($input['name']==='')throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Enter a palette name.']);
        return response()->json(['data'=>$library->save($input)]);
    }
}
