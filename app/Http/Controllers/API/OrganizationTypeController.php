<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationType;
use Illuminate\Http\Request;
use Auth;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;

class OrganizationTypeController extends BaseController
{
    public function getOrganizationType(){
        $organizationType = OrganizationType::where('is_active','1')->orderBy('organization_type','ASC')
        ->select('organization_type_id as organizationTypeId','organization_type as organizationType','is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$organizationType],200);
    }

    public function addOrganizationType(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'organizationType' => 'required',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $organizationType = new OrganizationType;
            $organizationType->organization_type = $request->organizationType;
            $organizationType->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $organizationType->created_id = $authId;
            $organizationType->modified_id = $authId;
            $organizationType->save();

            return response()->json(['status'=>true,'code'=>200,'data'=>$organizationType->organization_type_id,'message'=>'Organization type has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }

    }
}
