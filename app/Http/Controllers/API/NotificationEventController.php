<?php
namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Models\NotificationEvent;
use Validator;
use App\Http\Controllers\Controller  as BaseController;


class NotificationEventController extends BaseController
{
    public function notificationEventListByCategoryId($notificationCategoryId){
        try{
            $notificationEvents = NotificationEvent::where('is_active','1')
            ->where('notification_category_id',$notificationCategoryId)
            ->select('notification_event_id as notificationEventId', 'notification_event_name as notificationEventName')
            ->get();
            return response()->json(['status'=>true,'code'=>200,'data'=>$notificationEvents],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'error'=>$e->getMessage()],501);
        }
    }
}