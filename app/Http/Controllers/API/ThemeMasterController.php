<?php

namespace App\Http\Controllers\API;

use App\Models\ThemeMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ThemeMasterController extends BaseController
{
    public function getThemeList(Request $request){
        $sort = $request->has('sort') ? $request->get('sort') : 'theme_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'themeName'){
            $sortColumn = 'theme_name';
        }elseif($sort == 'backgroundColor'){
            $sortColumn = 'background_color';
        }elseif($sort == 'textColor'){
            $sortColumn = 'text_color';
        }elseif($sort == 'isActive'){
            $sortColumn = 'is_active';
        }

        $themeMasters = ThemeMaster::where('is_active','1')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('theme_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('background_color', 'LIKE', '%'.$search.'%');
                $query->orWhere('text_color', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('theme_id as themeId', 'theme_name as themeName','background_color as backgroundColor', 'text_color as textColor', 'is_deafult as isDeafult', 'is_active as isActive')
        ->get();
        // foreach($themeMasters as $themeMaster){ 
        //     if($themeMaster->imageIcon != ''){
        //         $themeMaster->imageIcon = getFileS3Bucket(getPathS3Bucket().'/themes/'.$themeMaster->imageIcon);
        //     }
        // }
        return response()->json(['status'=>true,'code'=>200,'data'=>$themeMasters],200);
    }

    public function addNewTheme(Request $request){

        $validator = Validator::make($request->all(), [
            'themeName' => 'required',
            'themeCode' => 'required|unique:lms_theme_master,theme_code',
            'imageIcon' => 'required|mimes:jpeg,jpg,png',
            'themeBaseColor' => 'required',
            'backgroundColor' => 'required',
            'themeForegroundColor' => 'required',
            'themeProperty' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $path = getPathS3Bucket().'/themes';
        $s3ImageIcon = Storage::disk('s3')->put($path, $request->imageIcon);
        $imageIcon = substr($s3ImageIcon, strrpos($s3ImageIcon, '/') + 1);

        $themeMaster = new ThemeMaster;
        $themeMaster->theme_name = $request->themeName;
        $themeMaster->theme_code = $request->themeCode;
        $themeMaster->image_icon = $imageIcon;
        $themeMaster->theme_base_color = $request->themeBaseColor;
        $themeMaster->background_color = $request->backgroundColor;
        $themeMaster->theme_foreground_color = $request->themeForegroundColor;
        $themeMaster->theme_property = $request->themeProperty;
        $themeMaster->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Theme has been created successfully.'],200);
    }

    public function getThemeById($themeId){
        $themeMasterRedis = Redis::get('themeMasterRedis' . $themeId);
        if(isset($themeMasterRedis)){
            $themeMasterRedis = json_decode($themeMasterRedis,false);
            if($themeMasterRedis->image_icon != ''){
                $themeMasterRedis->image_icon = getFileS3Bucket(getPathS3Bucket().'/themes/'.$themeMasterRedis->image_icon);
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$themeMasterRedis],200);
        }else{
            $themeMaster = ThemeMaster::where(['is_active'=>'1','theme_id'=>$themeId]);
            if ($themeMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Theme is not found.'], 404);
            }

            $themeMaster = $themeMaster->select('theme_id as themeId', 'theme_name as themeName', 'theme_code as themeCode', 'image_icon as imageIcon', 'theme_base_color as themeBaseColor', 'background_color as backgroundColor', 'theme_foreground_color as themeForegroundColor', 'theme_property as themeProperty', 'is_active as isActive', 'date_created as dateCreated', 'date_modified as dateModified')->first();
            
            Redis::set('themeMasterRedis' . $themeId, $themeMaster);

            if($themeMaster->imageIcon != ''){
                $themeMaster->imageIcon = getFileS3Bucket(getPathS3Bucket().'/themes/'.$themeMaster->imageIcon);
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$themeMaster],200);
        }
    }

    public function updateThemeById(Request $request){

        $validator = Validator::make($request->all(), [
            'themeId' => 'required|integer',
            'themeName' => 'required',
            'themeCode' => 'required|unique:lms_theme_master,theme_code,'.$request->themeId.',theme_id',
            'imageIcon' => 'mimes:jpeg,jpg,png',
            'themeBaseColor' => 'required',
            'backgroundColor' => 'required',
            'themeForegroundColor' => 'required',
            'themeProperty' => 'required'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        $themeMaster = ThemeMaster::where(['is_active'=>'1','theme_id'=>$request->themeId]);
        if ($themeMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Theme is not found.'], 404);
        }

        $themeMaster->update([
            'theme_name' => $request->themeName,
            'theme_code' => $request->themeCode,
            'theme_base_color' => $request->themeBaseColor,
            'background_color' => $request->backgroundColor,
            'theme_foreground_color' => $request->themeForegroundColor,
            'theme_property' => $request->themeProperty
        ]);

        if($request->file('imageIcon') != ''){
            $path = getPathS3Bucket().'/themes';
            $s3ImageIcon = Storage::disk('s3')->put($path, $request->imageIcon);
            $imageIcon = substr($s3ImageIcon, strrpos($s3ImageIcon, '/') + 1);
            $themeMaster->update([
                'image_icon' => $imageIcon
            ]);
        }

        $themeMaster = $themeMaster->select('theme_id as themeId', 'theme_name as themeName', 'theme_code as themeCode', 'image_icon as imageIcon', 'theme_base_color as themeBaseColor', 'background_color as backgroundColor', 'theme_foreground_color as themeForegroundColor', 'theme_property as themeProperty', 'is_active as isActive', 'date_created as dateCreated', 'date_modified as dateModified')->first();
        Redis::del('themeMasterRedis' . $request->themeId);
        Redis::set('themeMasterRedis' . $request->themeId, $themeMaster);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Theme has been updated successfully.'],200);
    }


    public function deleteTheme(Request $request){
        $validator = Validator::make($request->all(), [
            'themeId' => 'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $themeMaster = ThemeMaster::where(['is_active'=>'1','theme_id'=>$request->themeId]);
        if($themeMaster->count() > 0){

            $themeMaster->update([
                'is_active' => '0',
            ]);

            Redis::del('themeMasterRedis' . $request->themeId);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Theme has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Theme is not found.'], 404);
        }
    }

    public function setTheme(Request $request){
        $validator = Validator::make($request->all(), [
            'themeId' => 'required|integer'
        ]);  

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $themeMaster = ThemeMaster::where(['is_active'=>'1','theme_id'=>$request->themeId]);
        if($themeMaster->count() > 0){

            ThemeMaster::where(['is_active'=>'1','is_deafult'=>'1'])->update(['is_deafult'=>'0']);

            $themeMaster->update([
                'is_deafult' => '1',
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Theme has been set successfully.'],200);

        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Theme is not found.'], 404);
        }
    }
}
