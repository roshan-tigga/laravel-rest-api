<?php

namespace App\Http\Controllers\API;

use App\Models\ClassroomClasses;
use App\Models\ClassroomClassSessions;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class ClassRoomClassController extends BaseController
{
    public function getOrgClassList(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $classId = $request->has('classId') ? $request->get('classId') : '';


        $ClassroomClassSessions = ClassroomClassSessions::
        join('lms_org_classroom_classes','lms_org_classroom_classes.id','=','lms_org_classroom_class_sessions.class_id')
        ->leftJoin('lms_user_master','lms_user_master.user_id','=','lms_org_classroom_class_sessions.instructor_id')
        ->where('lms_org_classroom_class_sessions.is_active','!=','0')->where('lms_org_classroom_class_sessions.org_id',$organizationId)
        ->where(function($query) use ($classId){
            if($classId != ''){
                $query->where('lms_org_classroom_class_sessions.class_id',$classId);
            }
        })
        ->select('lms_org_classroom_class_sessions.id','lms_org_classroom_classes.class_name as className','lms_org_classroom_classes.class_status as classStatus','lms_org_classroom_class_sessions.date as startDate','lms_org_classroom_class_sessions.start_time as startTime','lms_org_classroom_class_sessions.location',
        DB::raw('CONCAT(lms_user_master.first_name," ",lms_user_master.last_name) AS instructor'),
        'lms_org_classroom_class_sessions.is_active as isActive')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$ClassroomClassSessions],200);
    }

    public function addOrgClass(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'className' => 'required',
            'classStatus' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $ClassroomClasses = new ClassroomClasses;
        $ClassroomClasses->classroom_course_id = $request->courseId;
        $ClassroomClasses->class_name = $request->className;
        $ClassroomClasses->class_status = $request->classStatus;
        $ClassroomClasses->class_certificate_id = $request->certificateId;
        $ClassroomClasses->max_seats = $request->maxSeats;
        $ClassroomClasses->delivery_type = $request->deliveryType;
        $ClassroomClasses->virtual_class_description = $request->virtualClassDescription;
        $ClassroomClasses->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $ClassroomClasses->org_id = $organizationId;
        $ClassroomClasses->created_id = $authId;
        $ClassroomClasses->modified_id = $authId;
        $ClassroomClasses->save();

        if(!empty($request->sessions)){
            foreach($request->sessions as $sessions){
                $ClassroomClassSessions = new ClassroomClassSessions;
                $ClassroomClassSessions->class_id = $ClassroomClasses->id;
                $ClassroomClassSessions->classroom_course_id = $request->courseId;
                $ClassroomClassSessions->org_id = $organizationId;
                $ClassroomClassSessions->date = $sessions['date'] ? date('Y-m-d',strtotime($sessions['date'])) : Null;
                $ClassroomClassSessions->hrs = $sessions['hrs'];
                $ClassroomClassSessions->minutes = $sessions['minutes'];
                $ClassroomClassSessions->start_time = $sessions['startTime'];
                $ClassroomClassSessions->timezone = $sessions['timezone'];
                $ClassroomClassSessions->instructor_id = $sessions['instructorId'];
                $ClassroomClassSessions->location = $sessions['location'];
                $ClassroomClassSessions->created_id = $authId;
                $ClassroomClassSessions->modified_id = $authId;
                $ClassroomClassSessions->save();
            }
        }

        return response()->json(['status'=>true,'code'=>201,'data'=>$ClassroomClasses->id,'message'=>'Class has been created successfully.'],201);
    }

    public function getOrgClassById($id){
        $ClassroomClasses = ClassroomClasses::where('is_active','!=','0')
        ->select('id','classroom_course_id as courseId','class_name as className','class_status as classStatus','class_certificate_id as certificateId','max_seats as maxSeats','delivery_type as deliveryType','virtual_class_description as virtualClassDescription','is_active as isActive')
        ->find($id);
        if(is_null($ClassroomClasses)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class not found.'],400);
        }

        $ClassroomClassSessions = ClassroomClassSessions::where('is_active','!=','0')->where('class_id',$ClassroomClasses->id);
        if($ClassroomClassSessions->count()){
            $ClassroomClasses->sessions = $ClassroomClassSessions
            ->select('id','date','hrs','minutes','start_time as startTime','timezone','instructor_id as instructorId','location','is_active as isActive')
            ->get();
        }else{
            $ClassroomClasses->sessions = Null;
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$ClassroomClasses],200);
    }

    public function getOrgClassSessionById($id){
        $ClassroomClassSessions = ClassroomClassSessions::where('is_active','!=','0')
        ->select('id','date','hrs','minutes','start_time as startTime','timezone','instructor_id as instructorId','location','is_active as isActive')
        ->find($id);
        if(is_null($ClassroomClassSessions)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class session not found.'],400);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$ClassroomClassSessions],200);
    }

    public function updateOrgClassById(Request $request,$id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'className' => 'required',
            'classStatus' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $ClassroomClasses = ClassroomClasses::where('is_active','!=','0')->find($id);
        if(is_null($ClassroomClasses)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class not found.'],400);
        }
        $ClassroomClasses->classroom_course_id = $request->courseId;
        $ClassroomClasses->class_name = $request->className;
        $ClassroomClasses->class_status = $request->classStatus;
        $ClassroomClasses->class_certificate_id = $request->certificateId;
        $ClassroomClasses->max_seats = $request->maxSeats;
        $ClassroomClasses->delivery_type = $request->deliveryType;
        $ClassroomClasses->virtual_class_description = $request->virtualClassDescription;
        $ClassroomClasses->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $ClassroomClasses->org_id = $organizationId;
        $ClassroomClasses->modified_id = $authId;
        $ClassroomClasses->save();

        if(!empty($request->sessions)){
            foreach($request->sessions as $sessions){
                if(!empty($sessions['id'])){
                    $ClassroomClassSessions = ClassroomClassSessions::find($sessions['id']);
                }else{
                    $ClassroomClassSessions = new ClassroomClassSessions;
                    $ClassroomClassSessions->class_id = $ClassroomClasses->id;
                    $ClassroomClassSessions->classroom_course_id = $request->courseId;
                    $ClassroomClassSessions->org_id = $organizationId;
                    $ClassroomClassSessions->created_id = $authId;
                }
                $ClassroomClassSessions->date = $sessions['date'] ? date('Y-m-d',strtotime($sessions['date'])) : Null;
                $ClassroomClassSessions->hrs = $sessions['hrs'];
                $ClassroomClassSessions->minutes = $sessions['minutes'];
                $ClassroomClassSessions->start_time = $sessions['startTime'];
                $ClassroomClassSessions->timezone = $sessions['timezone'];
                $ClassroomClassSessions->instructor_id = $sessions['instructorId'];
                $ClassroomClassSessions->location = $sessions['location'];
                $ClassroomClassSessions->modified_id = $authId;
                $ClassroomClassSessions->save();
            }
        }
        
        return response()->json(['status'=>true,'code'=>200,'message'=>'Class has been updated successfully.'],200);
    }

    public function updateOrgClassSessionById(Request $request,$id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $ClassroomClassSessions = ClassroomClassSessions::where('is_active','!=','0')->find($id);
        if(is_null($ClassroomClassSessions)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class session not found.'],400);
        }
        $ClassroomClassSessions->date = $request->date ? date('Y-m-d',strtotime($request->date)) : Null;
        $ClassroomClassSessions->hrs = $request->hrs;
        $ClassroomClassSessions->minutes = $request->minutes;
        $ClassroomClassSessions->start_time = $request->startTime;
        $ClassroomClassSessions->timezone = $request->timezone;
        $ClassroomClassSessions->instructor_id = $request->instructorId;
        $ClassroomClassSessions->location = $request->location;
        $ClassroomClassSessions->modified_id = $authId;
        $ClassroomClassSessions->save();
        
        return response()->json(['status'=>true,'code'=>200,'message'=>'Class session has been updated successfully.'],200);
    }

    public function deleteOrgClassById($id){
        $authId = Auth::user()->user_id;
        $ClassroomClasses = ClassroomClasses::where('is_active','!=','0')->find($id);
        if(is_null($ClassroomClasses)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class not found.'],400);
        }
        $ClassroomClasses->is_active = 0; 
        $ClassroomClasses->modified_id = $authId;
        $ClassroomClasses->save();


        $ClassroomClassSessions = ClassroomClassSessions::where('class_id',$id);
        if($ClassroomClassSessions->count() > 0){
            $ClassroomClassSessions->update([
                'is_active' => 0,
                'modified_id' => $authId,
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Class has been deleted successfully.'],200); 
    }

    public function deleteOrgClassSessionById($id){
        $authId = Auth::user()->user_id;
        $ClassroomClassSessions = ClassroomClassSessions::where('is_active','!=','0')->find($id);
        if(is_null($ClassroomClassSessions)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Class session not found.'],400);
        }
        $ClassroomClassSessions->is_active = 0; 
        $ClassroomClassSessions->modified_id = $authId;
        $ClassroomClassSessions->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Class session has been deleted successfully.'],200); 
    }

    public function getClassesAndSessionsByCourseId($id){
        $ClassroomClasses = ClassroomClasses::where('is_active','!=','0')
        ->select('id','classroom_course_id as courseId','class_name as className','class_status as classStatus','class_certificate_id as certificateId','max_seats as maxSeats','delivery_type as deliveryType','virtual_class_description as virtualClassDescription','is_active as isActive')
        ->where('classroom_course_id',$id)
        ->get();
        if($ClassroomClasses->count() > 0){
            foreach($ClassroomClasses as $ClassroomClass){
                $ClassroomClass->sessions = ClassroomClassSessions::where('is_active','!=','0')->where('class_id',$ClassroomClass->id)->select('id','date','hrs','minutes','start_time as startTime','timezone','instructor_id as instructorId','location','is_active as isActive')
                ->get();
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$ClassroomClasses],200);
    }
}
