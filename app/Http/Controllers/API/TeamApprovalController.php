<?php

namespace App\Http\Controllers\API;

use App\Models\TeamApproval;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class TeamApprovalController extends BaseController
{

    public function getTeamApprovalList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;

        $teamApprovals = DB::table('lms_org_team_approvals as teamApprovals')
        ->leftJoin('lms_user_master as user','user.user_id','=','teamApprovals.user_id')
        ->leftJoin('lms_org_training_library as training','training.training_id','=','teamApprovals.course_id')
        ->leftJoin('lms_training_types as trainingType','training.training_type_id','=','trainingType.training_type_id')
        ->leftJoin('lms_job_title as jobTitle','user.job_title','=','jobTitle.job_title_id')
        ->where('teamApprovals.is_active','1')
        ->where(function($query) use ($authId,$roleId){
            if($roleId != 1){
                $query->where('teamApprovals.user_id',$authId);
            }
        })
        ->select('teamApprovals.team_approval_id as teamApprovalId', 
        DB::raw('CONCAT(user.first_name," ",user.last_name) AS userName'), 'jobTitle.job_title_name as jobTitle', 'training.training_name as courseName', 'trainingType.training_type as courseType', 'training.date_created as trainingDate', 
        'trainingType.training_type as trainingType',
        DB::raw('(CASE 
        WHEN teamApprovals.team_approval_status = "0" THEN "Pending" 
        WHEN teamApprovals.team_approval_status = "1" THEN "Approved" 
        ELSE "Rejected" 
        END) AS Status'),
        'teamApprovals.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$teamApprovals],200);
    }

    public function teamApproved(Request $request)
    {
        $authId = Auth::user()->user_id;
        TeamApproval::where('is_active','1')->where('team_approval_id',$request->teamApprovalId)->update([
            'team_approval_status'=>'1',
            'modified_id' => $authId,
            'date_modified' => date('Y-m-d H:i:s')
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Team approved successfully.'],200);
    }

    public function teamRejected(Request $request)
    {
        $authId = Auth::user()->user_id;
        TeamApproval::where('is_active','1')->where('team_approval_id',$request->teamApprovalId)->update([
            'team_approval_status'=>'2',
            'modified_id' => $authId,
            'date_modified' => date('Y-m-d H:i:s')
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Team rejected successfully.'],200);
    }
}
