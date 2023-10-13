<?php

namespace App\Http\Controllers\API;

use App\Models\Area;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class AreaController extends BaseController
{
    public function getAreaList(Request $request){
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
        
        $areas = Area::where('is_active','!=','0')
        ->where('org_id',$organizationId)
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if($roleId != 1){
        //         if(!empty($organizationIds)){
        //             $query->whereIn('org_id',$organizationIds);
        //         }
        //     }
        // })
        ->orderBy('area_id','Desc')
        ->select('area_id as areaId','area_name as areaName', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$areas],200);
    }

    public function addArea(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        if($request->orgId){
            $organizationId = $request->orgId;
        }
        $validator = Validator::make($request->all(), [
            'areaName' => 'max:250',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try {
            $area = new Area;
            $area->area_name = $request->areaName;
            $area->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $area->org_id = $organizationId;
            $area->created_id = $authId;
            $area->modified_id = $authId;
            $area->save();

            return response()->json(['status'=>true,'code'=>200,'data'=>$area->area_id,'message'=>'Area has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

}
