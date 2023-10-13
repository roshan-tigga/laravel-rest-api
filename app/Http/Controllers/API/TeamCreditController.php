<?php

namespace App\Http\Controllers\API;

use App\Models\TeamApproval;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class TeamCreditController extends BaseController
{

    public function getTeamCreditList(Request $request)
    {

        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;

        $teamCredits = DB::table('lms_org_team_credit as teamCredit')
        ->leftJoin('lms_user_master as user','user.user_id','=','teamCredit.user_id')
        ->leftJoin('lms_org_training_library as training','training.training_id','=','teamCredit.training_id')
        ->leftJoin('lms_job_title as jobTitle','user.job_title','=','jobTitle.job_title_id')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->where(function($query) use ($authId,$roleId,$organizationId){
            if($roleId != 1){
                $query->where('teamCredit.user_id',$authId);
                $query->where('teamCredit.org_id',$organizationId);
            }
        })
        ->select('teamCredit.team_credit_id as teamCreditId',DB::raw('CONCAT(user.first_name," ",user.last_name) AS userName'),'user.email_id as emailId','role.role_name as roleName','teamCredit.credit_score as creditScore','jobTitle.job_title_name as jobTitle','teamCredit.is_active as isActive')
        ->get();
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$teamCredits],200);
    }


    public function getTeamCreditById($teamCreditId)
    {
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;

        $teamCredit = DB::table('lms_org_team_credit as teamCredit')
        ->leftJoin('lms_user_master as user','user.user_id','=','teamCredit.user_id')
        ->leftJoin('lms_org_training_library as training','training.training_id','=','teamCredit.training_id')
        ->leftJoin('lms_job_title as jobTitle','user.job_title','=','jobTitle.job_title_id')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->where(function($query) use ($authId,$roleId){
            if($roleId != 1){
                $query->where('teamCredit.user_id',$authId);
            }
        })
        ->where('team_credit_id',$teamCreditId);
        if($teamCredit->count() > 0){
            $teamCredit = $teamCredit->select('teamCredit.team_credit_id as teamCreditId',DB::raw('CONCAT(user.first_name," ",user.last_name) AS userName'),'user.email_id as emailId','role.role_name as roleName','teamCredit.credit_score as creditScore','jobTitle.job_title_name as jobTitle','teamCredit.is_active as isActive')->first();
            return response()->json(['status'=>true,'code'=>200,'data'=>$teamCredit],200);
        }else{
            return response()->json(['status'=>true,'code'=>404,'error'=>'Team credit is not found.'],404);
        }
    }
}
