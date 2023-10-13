<?php

namespace App\Http\Controllers\API;

use App\Models\Icon;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;


class IconController extends BaseController
{
    public function getIconList(){
        $actionMaster = Icon::where('is_active','1')
        ->select('icon_id as iconId','icon_name as iconName','icon_path as iconPath')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$actionMaster],200);
    }
}
