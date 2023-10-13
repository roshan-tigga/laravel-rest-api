<?php

namespace App\Http\Controllers\API;

use App\Models\DynamicField;
use App\Models\NotificationDynamicField;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Illuminate\Support\Facades\Redis;

class DynamicFieldController extends BaseController
{
    public function getDynamicFieldList(Request $request){
        $dynamicFields = DynamicField::where('is_active','!=','0')
        ->select('dynamic_field_id as dynamicFieldId', 'dynamic_fields_name as dynamicFieldName', 'dynamic_fields_tag as dynamicFieldTag', 'dynamic_fields_value as dynamicFieldValue', 'show_notification as showNotification', 'show_certificate as showCertificate', 'show_other as showOther', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$dynamicFields],200);
    }

    public function getDynamicFieldListByEventId($notificationEventId){
        try{
            $data = $allData = [];
            $dynamicFields = NotificationDynamicField::where('is_active','1')
            ->whereRaw("find_in_set('".$notificationEventId."',notification_event_id)")
            ->select('dynamic_field_id as dynamicFieldsId', 'dynamic_fields_name as dynamicFieldsName', 'dynamic_fields_tag as dynamicFieldsTag')
            ->get();
            return response()->json(['status'=>true,'code'=>200,'data'=>$dynamicFields],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'error'=>$e->getMessage()],501);
        }
    }
}
