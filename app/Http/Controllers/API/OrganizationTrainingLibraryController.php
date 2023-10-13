<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationTrainingLibrary;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class OrganizationTrainingLibraryController extends BaseController
{
    public function trainingAssignment(Request $request){

        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'trainingIds' => 'required',
            'organizationIds' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {

            $organizationIds = NULL;
            if(!empty($request->organizationIds)){
                if(count($request->organizationIds) > 0){
                    $organizationIds = implode(',',$request->organizationIds);
                }
            }

            if(!empty($request->trainingIds)){
                foreach($request->trainingIds as $trainingId){
                    $organizationTrainingLibrary = OrganizationTrainingLibrary::where('training_id',$trainingId);
                    if($organizationTrainingLibrary->count() > 0){

                        $organizationTrainingLibrary->update([
                            'org_id'=>$organizationIds,
                            'modified_id' => $authId
                        ]);

                    }else{
                        $organizationTrainingLibrary = new OrganizationTrainingLibrary;
                        $organizationTrainingLibrary->training_id = $trainingId;
                        $organizationTrainingLibrary->org_id = $organizationIds;
                        $organizationTrainingLibrary->created_id = $authId;
                        $organizationTrainingLibrary->modified_id = $authId;
                        $organizationTrainingLibrary->save();
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Training assigned successfully.'],200);
            
        } catch (\Throwable $e) {
            abort(501, $e->getMessage());
        }
    }
}
