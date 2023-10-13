<?php

namespace App\Http\Controllers\API;

use App\Models\Division;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class DivisionController extends BaseController
{
    public function getDivisionList(Request $request){
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
        
        $divisions = Division::where('is_active','!=','0')
        ->where('org_id',$organizationId)
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if($roleId != 1){
        //         if(!empty($organizationIds)){
        //             $query->whereIn('org_id',$organizationIds);
        //         }
        //     }
        // })
        ->orderBy('division_id','Desc')
        ->select('division_id as divisionId','division_name as divisionName', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$divisions],200);
    }

    public function addDivision(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        if($request->orgId){
            $organizationId = $request->orgId;
        }
        $validator = Validator::make($request->all(), [
            'divisionName' => 'max:250',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try {
            $division = new Division;
            $division->division_name = $request->divisionName;
            $division->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $division->org_id = $organizationId;
            $division->created_id = $authId;
            $division->modified_id = $authId;
            $division->save();

            return response()->json(['status'=>true,'code'=>200,'data'=>$division->division_id,'message'=>'Division has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

}
