<?php

namespace App\Http\Controllers\API;

use App\Models\DynamicLink;
use Illuminate\Http\Request; 
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Illuminate\Support\Facades\Redis;

class DynamicLinkController extends BaseController
{
    public function getDynamicLinkList(Request $request){
        $dynamicLinks = DynamicLink::where('is_active','!=','0')
        ->select('dynamic_link_id as dynamicLinkId', 'dynamic_link_name as dynamicLinkName', 'dynamic_link_tag as dynamicLinkTag', 'dynamic_link_value as dynamicLinkValue', 'show_notification as showNotification', 'show_certificate as showCertificate', 'show_other as showOther', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$dynamicLinks],200);
    }
}
