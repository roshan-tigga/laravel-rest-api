<?php

namespace App\Http\Controllers\API;

use App\Models\NotificationCategory;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;

class NotificationCategoryController extends BaseController 
{
    public function getNotificationCategoryList(){
        try{
            $categories = NotificationCategory::where('is_active','1')->orderBy('notification_category_id','ASC')->select('notification_category_id as notificationCategoryId', 'notification_category_name as notificationCategoryName')->get();
            return response()->json(['status'=>true,'code'=>200,'data'=>$categories],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'error'=>$e->getMessage()],501);
        }
    }
}
