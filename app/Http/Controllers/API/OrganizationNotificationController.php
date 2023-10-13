<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationNotification;
use App\Models\NotificationMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Carbon\Carbon;

class OrganizationNotificationController extends BaseController
{
    public function getOrganizationNotificationList(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'org_notification_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';

        $sortColumn = $sort;

        $notificationMaster = OrganizationNotification::leftJoin('lms_notification_events as events','lms_org_notification.notification_event_id','=','events.notification_event_id')
        ->where('lms_org_notification.is_active','!=','0')
        ->where('lms_org_notification.org_id',$organizationId)
        ->where('lms_org_notification.assigned','1')
        ->orderBy($sortColumn,$order)
        ->select('lms_org_notification.org_notification_id as notificationId', 'lms_org_notification.org_notification_name as notificationName', 'lms_org_notification.org_notification_type as notificationType', 'lms_org_notification.org_subject as subject','events.notification_event_name as notificationEventName','lms_org_notification.is_modified as isModified', 'lms_org_notification.is_active as isActive')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }

    public function activeInactiveNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationNotification = OrganizationNotification::where('is_active','!=','0')->where('org_id',$organizationId)->where('org_notification_id',$request->notificationId);
        if($OrganizationNotification->count() > 0){
            $isActive = $OrganizationNotification->first()->is_active;
            if($isActive == 1){
                $OrganizationNotification->update([
                    'is_active' => 2
                ]);
                return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been inactivated successfully.'],200);
            }else{
                $OrganizationNotification->update([
                    'is_active' => 1
                ]);
                return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been activated successfully.'],200);
            }
        }else{
            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification not found.'],200);
        }
    }

    public function addOrganizationNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            //'notificationOption' => 'required|integer',
            //'notification' => 'nullable|integer',
            'notificationName' => 'required|max:255',
            'notificationType' => 'required|in:text,email',
            'subject' => 'required|max:255',
            'notificationContent' => 'required',
            'notificationDate' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $organizationNotification = new OrganizationNotification;
            $organizationNotification->org_notification_name = $request->notificationName;
            $organizationNotification->org_notification_type = $request->notificationType;
            $organizationNotification->org_subject = $request->subject;
            $organizationNotification->org_notification_content = $request->notificationContent;
            $organizationNotification->notification_category_id = $request->notificationCategory;
            $organizationNotification->notification_event_id = $request->notificationEvent;
            $organizationNotification->org_id = $organizationId;
            $organizationNotification->notification_date = $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL;
            $organizationNotification->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $organizationNotification->created_id = $authId;
            $organizationNotification->modified_id = $authId;
            $organizationNotification->save();

            // if($request->notificationOption == 1){
            //     $organizationNotification = new OrganizationNotification;
            //     $organizationNotification->org_notification_name = $request->notificationName;
            //     $organizationNotification->org_notification_type = $request->notificationType;
            //     $organizationNotification->org_subject = $request->subject;
            //     $organizationNotification->org_notification_content = $request->notificationContent;
            //     $organizationNotification->org_id = $organizationId;
            //     $organizationNotification->notification_date = $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL;
            //     $organizationNotification->is_active = $request->isActive == '' ? '1' : $request->isActive;
            //     $organizationNotification->created_id = $authId;
            //     $organizationNotification->modified_id = $authId;
            //     $organizationNotification->save();
            // }elseif($request->notificationOption == 2){

            //     OrganizationNotification::where('notification_id',$request->notification)->update([
            //         'org_notification_content' => $request->notificationContent,
            //         'modified_id' => $authId
            //     ]);

            // }else{
            //     return response()->json(['status'=>true,'code'=>400,'message'=>'Notification has been not create.'],400);
            // } 
            
            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getOrganizationNotificationById($organizationNotificationId)
    {
        $organizationId = Auth::user()->org_id; 
        $organizationNotification = OrganizationNotification::
            leftJoin('lms_notification_events as events','lms_org_notification.notification_event_id','=','events.notification_event_id')
            ->leftJoin('lms_notification_category as category','lms_org_notification.notification_category_id','=','category.notification_category_id')
            ->where('lms_org_notification.is_active','!=','0')->where('lms_org_notification.assigned','=','1')->where('lms_org_notification.org_notification_id',$organizationNotificationId)->where('lms_org_notification.org_id',$organizationId);
        if ($organizationNotification->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
        }

        $organizationNotification = $organizationNotification->select('lms_org_notification.org_notification_id as notificationId', 'lms_org_notification.org_notification_name as notificationName', 'lms_org_notification.org_notification_type as notificationType', 'lms_org_notification.org_subject as subject', 'lms_org_notification.org_notification_content as notificationContent', 'lms_org_notification.notification_date as notificationDate',
        'events.notification_event_id as notificationEventId','events.notification_event_name as notificationEventName',
        'category.notification_category_id as notificationCategoryId','category.notification_category_name as notificationCategoryName', 
        'lms_org_notification.is_active as isActive')->first();
        
        if($organizationNotification->notificationDate != ''){
            $organizationNotification->notificationDate = date('Y-m-d',strtotime($organizationNotification->notificationDate));
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$organizationNotification],200);
    
    }

    public function updateOrganizationNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'notificationId' => 'required|integer',
            //'notification' => 'nullable|integer',
            'notificationName' => 'required|max:255',
            'notificationType' => 'required|in:text,email',
            'subject' => 'required|max:255',
            'notificationContent' => 'required',
            'notificationDate' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $organizationNotification = OrganizationNotification::where('is_active','!=','0')->where('assigned','=','1')->where('org_notification_id',$request->notificationId)->where('org_id',$organizationId);
        if ($organizationNotification->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
        }

        $organizationNotification->update([
            'org_notification_name' => $request->notificationName,
            'org_notification_type' => $request->notificationType,
            'org_subject' => $request->subject,
            'org_notification_content' => $request->notificationContent,
            'notification_category_id' => $request->notificationCategory,
            'notification_event_id' => $request->notificationEvent,
            'notification_date' => $request->notificationDate ? date('Y-m-d H:i:s',strtotime($request->notificationDate)) : NULL,
            'is_active' => $request->isActive == '' ? $organizationNotification->first()->is_active ? $organizationNotification->first()->is_active : '1' : $request->isActive,
            'is_modified' => ($organizationNotification->first()->su_assigned == 1) ? 1 : 0,
            'modified_id' => $authId,
            'date_modified' => date('Y-m-d H:i:s')
        ]);
        return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been updated successfully.'],200);
    }

    public function deleteOrganizationNotification(Request $request)
    {
        $organizationId = Auth::user()->org_id; 
        $validator = Validator::make($request->all(), [
            'notificationId'=>'required|integer'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
        
            $organizationNotification = OrganizationNotification::where('is_active','!=','0')->where('org_notification_id',$request->notificationId)->where('org_id',$organizationId);
            if ($organizationNotification->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }

            $organizationNotification->update([
                'is_active' => '0'
            ]);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkDeleteOrgNotification(Request $request)
    {
        $organizationId = Auth::user()->org_id; 
        $validator = Validator::make($request->all(), [
            'notificationIds'=>'required|array'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
        
            $organizationNotification = OrganizationNotification::where('is_active','!=','0')->whereIn('org_notification_id',$request->notificationIds)->where('org_id',$organizationId);
            if ($organizationNotification->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
            }

            $organizationNotification->update([
                'is_active' => '0'
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been deleted successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function resetOrganizationNotification(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id; 
        $validator = Validator::make($request->all(), [
            'notificationId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $organizationNotification = OrganizationNotification::where('is_active','!=','0')->where('assigned','=','1')->where('org_notification_id',$request->notificationId)->where('org_id',$organizationId)->whereNotNull('notification_id');
        if ($organizationNotification->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Notification is not found.'], 404);
        }

        $notification_id = $organizationNotification->first()->notification_id;
        $notificationMaster = NotificationMaster::where('notification_id',$notification_id);
        if($notificationMaster->count() > 0){

            $notificationMaster = $notificationMaster->first();

            $organizationNotification->update([
                'org_notification_name' => $notificationMaster->notification_name,
                'org_notification_type' => $notificationMaster->notification_type,
                'org_subject' => $notificationMaster->subject,
                'org_notification_content' => $notificationMaster->notification_content,
                'notification_category_id' => $notificationMaster->notification_category_id,
                'notification_event_id' => $notificationMaster->notification_event_id,
                'notification_date' => $notificationMaster->notification_date ? date('Y-m-d H:i:s',strtotime($notificationMaster->notification_date)) : NULL,
                'is_modified' => 0,
                'modified_id' => $authId,
                'date_modified' => date('Y-m-d H:i:s')
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Notification has been reset successfully.'],200);
    } 

    public function getOrgNotificationOptionList(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $type = $sort = $request->has('type') ? $request->get('type') : 'email';

        $notificationMaster = OrganizationNotification::leftJoin('lms_user_notification_assignment','lms_org_notification.org_notification_id','=','lms_user_notification_assignment.notification_id')
        ->where('lms_org_notification.is_active','!=','0')
        ->where(function($query) use ($type) {
            if(!empty($type)){
                $query->where('lms_org_notification.org_notification_type',$type);
            }
        })
        ->where('lms_org_notification.org_id',$organizationId)
        ->select('lms_org_notification.org_notification_id as notificationId', 'lms_org_notification.org_notification_name as notificationName', 'lms_org_notification.org_notification_type as notificationType',
        DB::raw('(CASE WHEN lms_user_notification_assignment.is_active = 1 THEN 1 ELSE 0 END) AS isChecked')
        )
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }

    public function displayNotification(){
        $roleId = Auth::user()->user->role_id;
        $nowDate = date('Y-m-d');

        if($roleId == 1){
            $organizationId = '';  
        }else{
            $organizationId = Auth::user()->org_id; 
        }

        $data = $allData = [];

        $notifications = OrganizationNotification::leftJoin('lms_org_master','lms_org_notification.org_id','=','lms_org_master.org_id')
        ->where('lms_org_notification.is_active','1')
        ->where('lms_org_master.is_active','1')
        ->whereDate('lms_org_notification.notification_date',$nowDate)
        ->where(function($query) use ($organizationId){
            if($organizationId != ''){
                $query->where('lms_org_notification.org_id',$organizationId);
            }
        })
        ->where('lms_org_notification.org_notification_type','notifications');
        $notifications->count();
        if($notifications->count() > 0){
            foreach($notifications->select('lms_org_master.organization_name','lms_org_notification.org_notification_name','lms_org_notification.org_subject')->get() as $notification){

                if($roleId == 1){
                    $data['organization'] = $notification->organization_name;
                }
                $data['notification_name'] = $notification->org_notification_name;
                $data['subject'] = $notification->org_subject;
                $allData[] = $data;
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);
    }

    public function getDisplayOrgNotificationList(){
        $organizationId = Auth::user()->org_id; 
        $notificationMaster = OrganizationNotification::where('is_active','=','1')->where('org_notification_type','=','text')->where('org_id',$organizationId)
        ->select('org_notification_id as notificationId', 'org_notification_name as notificationName','org_subject as subject','org_notification_content as notificationContent','notification_date as notificationDate')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$notificationMaster],200);
    }

    


}
