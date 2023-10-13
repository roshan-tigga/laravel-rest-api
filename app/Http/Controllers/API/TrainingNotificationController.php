<?php

namespace App\Http\Controllers\API;

use App\Models\TrainingNotification;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;

class TrainingNotificationController extends BaseController
{
    public function getTrainingNotificationList(){
       
        $notifications = TrainingNotification::where('is_active','1')->select('training_notification_id as notificationId','notification_name as notificationName','notification_type as notificationType')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$notifications],200);
    }
}
