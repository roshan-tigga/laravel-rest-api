<?php

namespace App\Http\Controllers\API;

use App\Models\JobTitle;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class JobTitleController extends BaseController
{
    public function getJobTitleList(Request $request){

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        if($request->orgId){
            $organizationId = $request->orgId;
        }

        $organizationIds = Organization::where('is_active','!=','0')
        ->where(function($query) use ($organizationId){
            $query->where('org_id',$organizationId);
            $query->orWhere('parent_org_id',$organizationId);
        })
        ->pluck('org_id');
        
        $jobTitles = JobTitle::where('is_active','!=','0')
        ->where('org_id',$organizationId)
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if($roleId != 1){
        //         if(!empty($organizationIds)){
        //             $query->whereIn('org_id',$organizationIds);
        //         }
        //     }
        // })
        ->orderBy('job_title_id','Desc')
        ->select('job_title_id as jobTitleId','job_title_name as jobTitleName', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$jobTitles],200);
    }

    public function addJobTitle(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        if($request->orgId){
            $organizationId = $request->orgId;
        }
       
        $validator = Validator::make($request->all(), [
            'jobTitleName' => 'max:250',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try {
            $jobTitle = new JobTitle;
            $jobTitle->job_title_name = $request->jobTitleName;
            $jobTitle->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $jobTitle->org_id = $organizationId;
            $jobTitle->created_id = $authId;
            $jobTitle->modified_id = $authId;
            $jobTitle->save();

            return response()->json(['status'=>true,'code'=>200,'data'=>$jobTitle->job_title_id,'message'=>'Job title has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

}
