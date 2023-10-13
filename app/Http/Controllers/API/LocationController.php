<?php

namespace App\Http\Controllers\API;

use App\Models\Location;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class LocationController extends BaseController
{
    public function getLocationList(Request $request){

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
        
        $locations = Location::where('is_active','!=','0')
        ->where('org_id',$organizationId)
        // ->where(function($query) use ($organizationIds,$roleId){
        //     if($roleId != 1){
        //         if(!empty($organizationIds)){
        //             $query->whereIn('org_id',$organizationIds);
        //         }
        //     }
        // })
        ->orderBy('location_id','Desc')
        ->select('location_id as locationId','location_name as locationName', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$locations],200);
    }

    public function addLocation(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        if($request->orgId){
            $organizationId = $request->orgId;
        }
        $validator = Validator::make($request->all(), [
            'locationName' => 'max:250',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try {
            $location = new Location;
            $location->location_name = $request->locationName;
            $location->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $location->org_id = $organizationId;
            $location->created_id = $authId;
            $location->modified_id = $authId;
            $location->save();

            return response()->json(['status'=>true,'code'=>200,'data'=>$location->location_id,'message'=>'Location has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

}
