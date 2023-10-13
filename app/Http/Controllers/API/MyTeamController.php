<?php

namespace App\Http\Controllers\API;

use App\Models\TeamApproval;
use App\Models\User;
use App\Models\JobTitle;
use App\Models\Transcript;
use App\Models\OrganizationTrainingLibrary;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class MyTeamController extends BaseController
{

    public function getMyTeamList(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $name = $request->name ? $request->name : '';
        $email = $request->email ? $request->email : '';
        $jobTitle = $request->jobTitle ? $request->jobTitle : '';
        $role = $request->role ? $request->role : '';

        $myTeams = User::leftJoin('lms_roles as role','lms_user_master.role_id','=','role.role_id')
        ->where('lms_user_master.is_active',1)
        ->where('lms_user_master.is_supervisor',$authId)
        ->where('lms_user_master.org_id',$organizationId)
        ->where(function($query) use ($name,$email,$jobTitle,$role,$organizationId){
            if($name != ''){
                $query->where(DB::raw('CONCAT_WS(lms_user_master.first_name," ",lms_user_master.last_name) AS userName'), 'LIKE',$name);
            }
            if($email != ''){
                $query->where('lms_user_master.email_id','LIKE',$email);
            }
            if($jobTitle != ''){
                $jobTitles = JobTitle::where('is_active',1)->where('org_id',$organizationId)->where('job_title_name','LIKE',$jobTitle)->pluck('job_title_id')->toArray();
                $query->whereIn('lms_user_master.job_title',$jobTitles);
            }
            if($role != ''){
                $query->where('role.role_name','LIKE',$role);
            }
        })
        ->select('lms_user_master.user_id as userId',DB::raw('CONCAT(lms_user_master.first_name," ",lms_user_master.last_name) AS userName'),'lms_user_master.email_id as email','role.role_name as roleName','lms_user_master.job_title as jobTitle')
        ->get();
        if($myTeams->count() > 0){
            foreach($myTeams as $myTeam){
                if(!empty($myTeam->jobTitle)){
                    $jobTitle = JobTitle::where('is_active',1)->where('org_id',$organizationId)->whereIn('job_title_id',explode(',',$myTeam->jobTitle));
                    if($jobTitle->count() > 0){
                        $myTeam->jobTitle = $jobTitle->pluck('job_title_name');
                    }else{
                        $myTeam->jobTitle = '';
                    }
                    
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$myTeams],200);

        exit;

        $sort = $request->has('sort') ? $request->get('sort') : 'lms_team_approvals.team_approval_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'jobTitle'){
            $sortColumn = 'user.job_title';
        }elseif($sort == 'courseName'){
            $sortColumn = 'training.training_name';
        }elseif($sort == 'roleName'){
            $sortColumn = 'role.role_name';
        }elseif($sort == 'creditScore'){
            $sortColumn = 'training.credits';
        }elseif($sort == 'progress'){
            $sortColumn = 'training.points';
        }elseif($sort == 'isActive'){
            $sortColumn = 'lms_team_approvals.is_active';
        }

        $myTeams = TeamApproval::leftJoin('lms_user_master as user','user.user_id','=','lms_team_approvals.user_id')
        ->leftJoin('lms_training_library as training','training.training_id','=','lms_team_approvals.course_id')
        ->leftJoin('lms_training_types as trainingType','training.training_type_id','=','trainingType.training_type_id')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->where('lms_team_approvals.is_active','1')
        ->select('lms_team_approvals.team_approval_id as myTeamId',
        DB::raw('CONCAT(user.first_name," ",user.last_name) AS userName'), 
        'role.role_name as roleName', 'user.job_title as jobTitle', 
         'training.credits as creditScore', 'training.points as progress', 'lms_team_approvals.is_active as isActive')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('user.job_title', 'LIKE', '%'.$search.'%');
                $query->orWhere('training.training_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('user.first_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('user.last_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('role.role_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('training.credits', 'LIKE', '%'.$search.'%');
                $query->orWhere('training.points', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('lms_team_approvals.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('lms_team_approvals.is_active','2');
                }
            }
        })
        ->when($sort=='userName',function($query) use ($order){ 
            return $query->orderBy("user.first_name",$order)->orderBy('user.last_name',$order);
        }, function($query) use ($sortColumn,$order){                   
            return $query->orderBy($sortColumn,$order);
        })
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$myTeams],200);
    }

    public function getCourseListByUserId(Request $request)
    {
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'userIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $courses = DB::table('lms_org_assignment_user_course as assignment')
        ->join('lms_org_training_library as trainingLibrary','assignment.courses','=','trainingLibrary.training_id')
        ->whereIn('assignment.users',$request->userIds)
        ->where('assignment.org_id',$organizationId)
        ->select('trainingLibrary.training_id as courseId','trainingLibrary.training_name as courseName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$courses],200);
    }

    public function giveCredit(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'userIds' => 'required|array',
            'courseIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        foreach($request->courseIds as $courseId){
            $transcript = Transcript::whereIn('user_id',$request->userIds)
            ->where('training_id',$courseId)
            ->where('org_id',$organizationId);
            if($transcript->count() > 0){

                $organizationTrainingLibrary = OrganizationTrainingLibrary::where('training_id',$courseId);
                if($organizationTrainingLibrary->count() > 0){
                    $credits = $organizationTrainingLibrary->first()->credits;
                    $transcript->update([
                        'credit' => $credits ? $credits : 0
                    ]);
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Credited successfully.'],200);

    }

    public function viewCreditListByUserId($userId)
    {
        $organizationId = Auth::user()->org_id;
        $transcripts = Transcript::
        join('lms_org_training_library as trainingLibrary','lms_user_transcript.training_id','=','trainingLibrary.training_id')
        ->where('lms_user_transcript.user_id',$userId)
        ->where('lms_user_transcript.org_id',$organizationId)
        ->where('lms_user_transcript.status','!=','1')
        ->where('lms_user_transcript.is_active','=','1')
        ->select('trainingLibrary.training_name as courseName','lms_user_transcript.credit',DB::raw('(CASE WHEN lms_user_transcript.status = 1 THEN "Completed"  ELSE "InProgress" END) AS progress'),'lms_user_transcript.date_created as dateCreated')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$transcripts],200);
    }

    public function creditHistoryByUserId($userId)
    {
        $organizationId = Auth::user()->org_id;
        $transcripts = Transcript::
        join('lms_org_training_library as trainingLibrary','lms_user_transcript.training_id','=','trainingLibrary.training_id')
        ->where('lms_user_transcript.user_id',$userId)
        ->where('lms_user_transcript.org_id',$organizationId)
        ->where('lms_user_transcript.status','=','1')
        ->where('lms_user_transcript.is_active','=','1')
        ->select('trainingLibrary.training_name as courseName','lms_user_transcript.credit',DB::raw('(CASE WHEN lms_user_transcript.status = 1 THEN "Completed"  ELSE "InProgress" END) AS progress'),'lms_user_transcript.date_created as dateCreated')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$transcripts],200);
    }
}
