<?php

namespace App\Http\Controllers\API;

use App\Models\GroupSetting;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController; 

class GroupSettingController extends BaseController
{
    public function getGroupSettingList(){
        $groupSettings = GroupSetting::where('is_active','1')->orderBy('order','ASC')
        ->select('group_setting_id as groupSettingId', 'group_setting_name as groupSettingName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$groupSettings],200);
    }
}
