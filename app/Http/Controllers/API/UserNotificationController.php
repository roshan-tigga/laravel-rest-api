<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\NotificationMaster;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

use App\Mail\WelcomeMail;
use Mail;


class UserNotificationController extends BaseController
{
    public function userNotification(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'users' => 'required',
            'notificationType' => 'required|integer',
            'notification' => 'required|integer',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $insert=0;
            if(is_array($request->users)){
                foreach($request->users as $userId){

                    if($request->user == 0){

                        $user = User::where('is_active','1')->where('user_id',$userId);
                        if($user->count() > 0){

                            $user = $user->first();

                            $notificationMaster = NotificationMaster::where('is_active','1')->where('notification_id',$request->notification);
                            if($notificationMaster->count() > 0){

                                $notificationMaster = $notificationMaster->first();

                                $messageBody = dynamicField($notificationMaster->notification_content);

                                $mailData = [
                                    'subject' => $notificationMaster->subject,
                                    'messageBody' => $messageBody,
                                    'organizationName' => Auth::user()->organization->organization_name
                                ];


                                $organizationLogo = '';
                                if(Auth::user()->organization->logo_image != ''){
                                    $organizationLogo = getFileS3Bucket(getPathS3Bucket().'/organization_logo/'.Auth::user()->organization->logo_image);
                                }

                                session(['organizationName' => Auth::user()->organization->organization_name,'organizationLogo' => $organizationLogo]);
                               Mail::to($user->email_id)->send(new WelcomeMail($mailData));
                            }
                        }
                    }

                    $userNotification = new UserNotification;
                    $userNotification->user_id = $userId;
                    $userNotification->org_id = $organizationId;
                    $userNotification->notification_type = $request->notificationType;
                    $userNotification->notification_id = $request->notification;
                    $userNotification->is_active = $request->isActive == '' ? '1' : $request->isActive;
                    $userNotification->created_id = $authId;
                    $userNotification->modified_id = $authId;
                    $userNotification->save();
                    $insert++;
                }
            }

            if($insert == 0){
                return response()->json(['status'=>false,'code'=>400,'message'=>'Notification not created.'],200);
            }else{
                return response()->json(['status'=>true,'code'=>200,'message'=>'Notification created successfully.'],200);
            }

        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }

    }
}
