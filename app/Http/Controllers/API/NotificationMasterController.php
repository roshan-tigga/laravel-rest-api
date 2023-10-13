<?php

namespace App\Http\Controllers\API;

use App\Models\NotificationMaster;
use App\Models\Organization;
use App\Models\OrganizationNotification;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use DB; 

class NotificationMasterController extends BaseController
{

    public function getNotificationList(Request $request)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'lms_notification_master.notification_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $sortColumn = $sort;

        $notificationMaster = NotificationMaster::leftJoin('lms_notification_events as events','lms_notification_master.notification_event_id','=','events.notification_event_id')
        ->where('lms_notification_master.is_active','!=','0')
        ->orderBy($sortColumn,$order)
        ->select('lms_notification_master.notification_id as notificationId', 'lms_notification_master.notification_name as notificationName', 'lms_notification_master.notification_type as notificationType', 'lms_notification_master.subject', 'lms_notification_master.notification_content as notificationContent',DB::raw("DATE_FORMAT(lms_notification_master.notification_date, '%d-%m-%Y') as notificationDate"),'events.notification_event_name as notificationEventName', 'lms_notification_master.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }


    public function addNewNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'notificationName' => 'required|max:255',
            'notificationType' => 'required|in:text,email',
            'subject' => 'required|max:255',
            'notificationContent' => 'required',
            'notificationDate' => 'nullable|date',
            'notificationCategory' => 'required',
            'notificationEvent' => 'required',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $notificationMaster = new NotificationMaster;
            $notificationMaster->notification_name = $request->notificationName;
            $notificationMaster->notification_type = $request->notificationType;
            $notificationMaster->subject = $request->subject;
            $notificationMaster->notification_content = $request->notificationContent;
            $notificationMaster->notification_category_id = $request->notificationCategory;
            $notificationMaster->notification_event_id = $request->notificationEvent;
            $notificationMaster->notification_date = $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL;
            $notificationMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $notificationMaster->created_id = $authId;
            $notificationMaster->modified_id = $authId;
            $notificationMaster->save();  

            if($notificationMaster->notification_id != ''){

                if($request->notificationType == 'email'){

                }

                $organizations = Organization::where('is_active','!=','0');
                if($organizations->count() > 0){
                    $data = [];
                    foreach($organizations->select('org_id')->get() as $organization){

                        $organizationId = $organization->org_id;

                        $data[] = [
                            'org_notification_name' => $request->notificationName,
                            'org_notification_type' => $request->notificationType,
                            'org_subject' => $request->subject,
                            'org_notification_content' => $request->notificationContent,
                            'is_active' => '1',
                            'notification_id' => $notificationMaster->notification_id,
                            'is_default' => '1',
                            'org_id' => $organizationId,
                            'notification_date' => $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL,
                            'notification_event_id' => $request->notificationEvent,
                            'notification_category_id' => $request->notificationCategory,
                            'su_assigned' => 1,
                            'assigned' => 1,
                            'created_id' => $authId,
                            'modified_id' => $authId,
                            'date_created' => Carbon::now(),
                            'date_modified' => Carbon::now()
                        ];

                    }

                    if(!empty($data)){
                        OrganizationNotification::insert($data);
                    }
                }

            }

            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getNotificationById($notificationId)
    {
        $notificationMasterRedis = Redis::get('notificationMasterRedis' . $notificationId);
        if(isset($notificationMasterRedis)){
            $notificationMasterRedis = json_decode($notificationMasterRedis,false);
            if($notificationMasterRedis->notificationDate != ''){ 
                $notificationMasterRedis->notificationDate = date('Y-m-d',strtotime($notificationMasterRedis->notificationDate));
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMasterRedis],200);
        }else{
            $notificationMaster = NotificationMaster::
                leftJoin('lms_notification_events as events','lms_notification_master.notification_event_id','=','events.notification_event_id')
                ->leftJoin('lms_notification_category as category','lms_notification_master.notification_category_id','=','category.notification_category_id')
                ->where('lms_notification_master.is_active','!=','0')->where('lms_notification_master.notification_id',$notificationId);
            if ($notificationMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }
            $notificationMaster =  $notificationMaster->select('lms_notification_master.notification_id as notificationId', 'lms_notification_master.notification_name as notificationName', 'lms_notification_master.notification_type as notificationType', 'lms_notification_master.subject', 'lms_notification_master.notification_content as notificationContent', 'lms_notification_master.notification_date as notificationDate',
            'events.notification_event_id as notificationEventId','events.notification_event_name as notificationEventName',
            'category.notification_category_id as notificationCategoryId','category.notification_category_name as notificationCategoryName', 'lms_notification_master.is_active as isActive')->first();
            if($notificationMaster->notificationDate != ''){
                $notificationMaster->notificationDate = date('Y-m-d',strtotime($notificationMaster->notificationDate));
            }
            Redis::set('notificationMasterRedis' . $notificationId, $notificationMaster);
            return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
        }
    }


    public function updateNotificationById(Request $request)
    {
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'notificationId'=>'required|integer',
            'notificationName' => 'required|max:255',
            'notificationType' => 'required|in:text,email',
            'subject' => 'required|max:255',
            'notificationContent' => 'required',
            'notificationDate' => 'nullable|date',
            'notificationCategory' => 'required',
            'notificationEvent' => 'required',
            'isActive' => 'integer'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
        
            $notificationMaster = NotificationMaster::where('is_active','!=','0')->where('notification_id',$request->notificationId);
            if ($notificationMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }

            $notificationMaster->update([
                'notification_name' => $request->notificationName,
                'notification_type' => $request->notificationType,
                'subject' => $request->subject,
                'notification_content' => $request->notificationContent,
                'notification_category_id' => $request->notificationCategory,
                'notification_event_id' => $request->notificationEvent,
                'notification_date' => $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL,
                'is_active' => $request->isActive == '' ? $notificationMaster->first()->is_active ? $notificationMaster->first()->is_active : '1' : $request->isActive,
                'modified_id' => $request->authId
            ]);

            $notificationMaster = NotificationMaster::
                leftJoin('lms_notification_events as events','lms_notification_master.notification_event_id','=','events.notification_event_id')
                ->leftJoin('lms_notification_category as category','lms_notification_master.notification_category_id','=','category.notification_category_id')
                ->where('lms_notification_master.is_active','!=','0')->where('lms_notification_master.notification_id',$request->notificationId);

            $notificationMaster =  $notificationMaster->select('lms_notification_master.notification_id as notificationId', 'lms_notification_master.notification_name as notificationName', 'lms_notification_master.notification_type as notificationType', 'lms_notification_master.subject', 'lms_notification_master.notification_content as notificationContent', 'lms_notification_master.notification_date as notificationDate',
                'events.notification_event_id as notificationEventId','events.notification_event_name as notificationEventName',
                'category.notification_category_id as notificationCategoryId','category.notification_category_name as notificationCategoryName', 'lms_notification_master.is_active as isActive')->first();

            Redis::del('notificationMasterRedis' . $request->notificationId);
            Redis::set('notificationMasterRedis' . $request->notificationId, json_encode($notificationMaster,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function deleteNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notificationId'=>'required|integer'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        try{
            $notificationMaster = NotificationMaster::where('is_active','!=','0')->where('notification_id',$request->notificationId);
            if ($notificationMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }

            $notificationMaster->update([
                'is_active' => '0'
            ]);

            Redis::del('notificationMasterRedis' . $request->notificationId);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkDeleteNotification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notificationIds'=>'required|array'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        try{
            $notificationMaster = NotificationMaster::where('is_active','!=','0')->whereIn('notification_id',$request->notificationIds);
            if ($notificationMaster->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }

            $notificationMaster->update([
                'is_active' => '0'
            ]);

            if(!empty($request->notificationIds)){
                foreach($request->notificationIds as $notificationId){
                    Redis::del('notificationMasterRedis' . $notificationId);
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getNotificationOptionList()
    {
        $notificationMaster = NotificationMaster::where('is_active','!=','0')
        ->select('notification_id as notificationId', 'notification_name as notificationName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }

    public function getDisplayNotificationList(){
        $notificationMaster = NotificationMaster::where('is_active','=','1')->where('notification_type','=','text')
        ->select('notification_id as notificationId', 'notification_name as notificationName','subject','notification_content as notificationContent','notification_date as notificationDate')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }

    public function notificationAssignToOrgNotification(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'notificationIds' => 'required|array',
            'organizationIds' => 'required|array',
            'organizationIds.*.organizationId' => 'required|integer',
            'organizationIds.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(is_array($request->notificationIds) && is_array($request->organizationIds)){
            
            if(!empty($request->notificationIds) && !empty($request->organizationIds)){
                foreach($request->organizationIds as $organization){

                    $organizationId = $organization['organizationId'];
                    $isChecked = $organization['isChecked'];

                    $organizationNotification = OrganizationNotification::whereIn('notification_id',$request->notificationIds)->where('org_id',$organizationId);
                    if($organizationNotification->count() > 0){
                        
                    }else{

                        $notificationMasters = NotificationMaster::whereIn('notification_id',$request->notificationIds);
                        if($notificationMasters->count() > 0){
                            foreach($notificationMasters->get() as $notificationMaster){

                                $notification = new OrganizationNotification;
                                $notification->notification_id = $notificationMaster->notification_id;
                                $notification->org_notification_name = $notificationMaster->notification_name;
                                $notification->org_notification_type = $notificationMaster->notification_type;
                                $notification->org_subject = $notificationMaster->subject;
                                $notification->org_notification_content = $notificationMaster->notification_content;
                                $notification->notification_event_id = $notificationMaster->notification_event_id;
                                $notification->notification_category_id = $notificationMaster->notification_category_id;
                                $notification->notification_date = $notificationMaster->notification_date;
                                $notification->is_active = $notificationMaster->is_active;
                                $notification->su_assigned = 1;
                                $notification->org_id = $organizationId;
                                $notification->created_id = $authId;
                                $notification->modified_id = $authId;
                                $notification->save();

                            }
                        }
                    } 
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Notification assigned to organization notification successfully.'],200);
    }

    
    public function getNotificationListByOrgId(Request $request,$organizationId)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'assigned';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $$organizationId = ($organizationId == 0) ? $organizationId = Auth::user()->org_id  : $organizationId;

        $sortColumn = $sort;
        $notificationMasters = OrganizationNotification::leftJoin('lms_notification_events as events','lms_org_notification.notification_event_id','=','events.notification_event_id')
        ->where('lms_org_notification.is_active','!=','0')
        ->where('lms_org_notification.su_assigned','=','1')
        ->where(function($query) use ($organizationId){
            //if($organizationId != 0){
                $query->where('lms_org_notification.org_id',$organizationId);
            //}
        })
        ->orderBy($sortColumn,$order)
        ->select('lms_org_notification.org_notification_id as notificationId', 'lms_org_notification.org_notification_name as notificationName', 'lms_org_notification.org_notification_type as notificationType','events.notification_event_name as notificationEventName', 'lms_org_notification.assigned as isChecked')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMasters],200);
    }

    public function bulkUpdateOrgNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|integer',
            'notifications' => 'required|array',
            'notifications.*.notificationId' => 'required|integer',
            'notifications.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        foreach($request->notifications as $notification){
            $notificationId = $notification['notificationId'];
            $isChecked = $notification['isChecked'];
            
            $organizationNotification = OrganizationNotification::where('org_notification_id',$notificationId)->where('org_id',$request->organizationId);
            if($organizationNotification->count() > 0){
                $organizationNotification->update([
                    'assigned' => $isChecked
                ]);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been updated successfully.'],200);
    }
}
