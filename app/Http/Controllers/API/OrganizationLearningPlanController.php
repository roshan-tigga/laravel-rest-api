<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationLearningPlan;
use App\Models\OrganizationLearningPlanRequirement;
use App\Models\OrganizationTrainingLibrary;
use App\Models\OrganizationCategory;
use App\Models\LearningPlanUserAssignment;
use App\Models\User;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class OrganizationLearningPlanController extends BaseController
{
    public function getOrgLearningPlanList(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationLearningPlan = OrganizationLearningPlan::
        where('is_active','!=','0')
        ->where('org_id',$organizationId)
        ->select('id','learning_plan_name as learningPlanName','progress','is_active as isActive')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationLearningPlan],200);
    }

    public function addOrgLearningPlan(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'learningPlanName' => 'required',
            //'forceOrder' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $OrganizationLearningPlan = new OrganizationLearningPlan;
        $OrganizationLearningPlan->learning_plan_name = $request->learningPlanName;
        $OrganizationLearningPlan->force_order = $request->forceOrder;
        $OrganizationLearningPlan->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $OrganizationLearningPlan->org_id = $organizationId;
        $OrganizationLearningPlan->created_id = $authId;
        $OrganizationLearningPlan->modified_id = $authId;
        $OrganizationLearningPlan->save();

        if(!empty($request->learningPlanRequirements)){
            foreach($request->learningPlanRequirements as $learningPlanRequirement){
                $OrganizationLearningPlanRequirement = new OrganizationLearningPlanRequirement;
                $OrganizationLearningPlanRequirement->learning_plan_id = $OrganizationLearningPlan->id;
                $OrganizationLearningPlanRequirement->requirement_id = @$learningPlanRequirement['requirementId'];
                $OrganizationLearningPlanRequirement->org_id = $organizationId;
                $OrganizationLearningPlanRequirement->due_date_setting = @$learningPlanRequirement['dueDateSetting'];
                $OrganizationLearningPlanRequirement->from_date_of_assign = @$learningPlanRequirement['fromDateOfAssign'];
                $OrganizationLearningPlanRequirement->from_date_of_expiration = @$learningPlanRequirement['fromDateOfExpiration'];
                $OrganizationLearningPlanRequirement->expiration_date_setting = @$learningPlanRequirement['expirationDateSetting'];
                $OrganizationLearningPlanRequirement->from_date_of_completion = @$learningPlanRequirement['fromDateOfCompletion'];
                $OrganizationLearningPlanRequirement->from_date_of_assignment = @$learningPlanRequirement['fromDateOfAssignment'];
                $OrganizationLearningPlanRequirement->created_id = $authId;
                $OrganizationLearningPlanRequirement->modified_id = $authId;
                $OrganizationLearningPlanRequirement->save();
            }
        }

        return response()->json(['status'=>true,'code'=>201,'message'=>'Learning Plan has been created successfully.'],201);
    }

    public function getOrgLearningPlanById($id){
        $OrganizationLearningPlan = OrganizationLearningPlan::where('is_active','!=','0')
        ->select('id','learning_plan_name as learningPlanName','force_order as forceOrder','progress','is_active as isActive')
        ->find($id);
        if(is_null($OrganizationLearningPlan)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning Plan not found.'],400);
        }

        $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::
        leftJoin('lms_org_training_library','lms_org_training_library.training_id','=','lms_org_learning_plan_requirements.requirement_id')
        ->leftJoin('lms_training_types','lms_training_types.training_type_id','=','lms_org_training_library.training_type_id')
        ->where('lms_org_learning_plan_requirements.is_active','!=','0')->where('lms_org_learning_plan_requirements.learning_plan_id',$id);
        if($OrganizationLearningPlanRequirement->count()){
            $OrganizationLearningPlan->learningPlanRequirements = $OrganizationLearningPlanRequirement
            ->select('lms_org_learning_plan_requirements.id','lms_org_learning_plan_requirements.requirement_id as requirementId','lms_training_types.training_type as type','lms_org_training_library.training_name as requirementName','lms_org_learning_plan_requirements.orders',
            'lms_org_learning_plan_requirements.due_date_setting as dueDateSetting','lms_org_learning_plan_requirements.from_date_of_assign as fromDateOfAssign','lms_org_learning_plan_requirements.from_date_of_expiration as fromDateOfExpiration','lms_org_learning_plan_requirements.expiration_date_setting as expirationDateSetting','lms_org_learning_plan_requirements.from_date_of_completion as fromDateOfCompletion','lms_org_learning_plan_requirements.from_date_of_assignment as fromDateOfAssignment',
            'lms_org_learning_plan_requirements.is_active as isActive')
            ->get();
        }else{
            $OrganizationLearningPlan->learningPlanRequirements = Null;
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationLearningPlan],200);
    }

    public function getOrgLearningPlanRequirementById($id){
        $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::
        leftJoin('lms_org_training_library','lms_org_training_library.training_id','=','lms_org_learning_plan_requirements.requirement_id')
        ->leftJoin('lms_training_types','lms_training_types.training_type_id','=','lms_org_training_library.training_type_id')
        ->where('lms_org_learning_plan_requirements.is_active','!=','0')
        ->select('lms_org_learning_plan_requirements.id','lms_org_learning_plan_requirements.requirement_id as requirementId','lms_training_types.training_type as type','lms_org_training_library.training_name as requirementName','lms_org_learning_plan_requirements.orders',
            'lms_org_learning_plan_requirements.due_date_setting as dueDateSetting','lms_org_learning_plan_requirements.from_date_of_assign as fromDateOfAssign','lms_org_learning_plan_requirements.from_date_of_expiration as fromDateOfExpiration','lms_org_learning_plan_requirements.expiration_date_setting as expirationDateSetting','lms_org_learning_plan_requirements.from_date_of_completion as fromDateOfCompletion','lms_org_learning_plan_requirements.from_date_of_assignment as fromDateOfAssignment',
            'lms_org_learning_plan_requirements.is_active as isActive')
        ->find($id);
        if(is_null($OrganizationLearningPlanRequirement)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning plan requirement not found.'],400);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationLearningPlanRequirement],200);
    }

    public function updateOrgLearningPlanRequirementById(Request $request,$id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::find($id);
        if(is_null($OrganizationLearningPlanRequirement)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning Plan requirement not found.'],400);
        }
        //$OrganizationLearningPlanRequirement->requirement_id = $request->requirementId;
        $OrganizationLearningPlanRequirement->due_date_setting = $request->dueDateSetting;
        $OrganizationLearningPlanRequirement->from_date_of_assign = $request->fromDateOfAssign;
        $OrganizationLearningPlanRequirement->from_date_of_expiration = $request->fromDateOfExpiration;
        $OrganizationLearningPlanRequirement->expiration_date_setting = $request->expirationDateSetting;
        $OrganizationLearningPlanRequirement->from_date_of_completion = $request->fromDateOfCompletion;
        $OrganizationLearningPlanRequirement->from_date_of_assignment = $request->fromDateOfAssignment;
        $OrganizationLearningPlanRequirement->modified_id = $authId;
        $OrganizationLearningPlanRequirement->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Learning plan requirement has been updated successfully.'],200);
    }

    public function updateOrgLearningPlanById(Request $request,$id){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'learningPlanName' => 'required',
            //'forceOrder' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $OrganizationLearningPlan = OrganizationLearningPlan::where('is_active','!=','0')->find($id);
        if(is_null($OrganizationLearningPlan)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning Plan not found.'],400);
        }
        $OrganizationLearningPlan->learning_plan_name = $request->learningPlanName;
        $OrganizationLearningPlan->force_order = $request->forceOrder;
        $OrganizationLearningPlan->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $OrganizationLearningPlan->org_id = $organizationId;
        $OrganizationLearningPlan->created_id = $authId;
        $OrganizationLearningPlan->modified_id = $authId;
        $OrganizationLearningPlan->save();

        if(!empty($request->learningPlanRequirements)){
            foreach($request->learningPlanRequirements as $learningPlanRequirement){
                if(!empty($learningPlanRequirement['id'])){
                    $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::find($learningPlanRequirement['id']);
                }else{
                    $OrganizationLearningPlanRequirement = new OrganizationLearningPlanRequirement;
                    $OrganizationLearningPlanRequirement->learning_plan_id = $OrganizationLearningPlan->id;
                    $OrganizationLearningPlanRequirement->org_id = $organizationId;
                    $OrganizationLearningPlanRequirement->created_id = $authId;
                }
                $OrganizationLearningPlanRequirement->requirement_id = @$learningPlanRequirement['requirementId'];
                $OrganizationLearningPlanRequirement->due_date_setting = @$learningPlanRequirement['dueDateSetting'];
                $OrganizationLearningPlanRequirement->from_date_of_assign = @$learningPlanRequirement['fromDateOfAssign'];
                $OrganizationLearningPlanRequirement->from_date_of_expiration = @$learningPlanRequirement['fromDateOfExpiration'];
                $OrganizationLearningPlanRequirement->expiration_date_setting = @$learningPlanRequirement['expirationDateSetting'];
                $OrganizationLearningPlanRequirement->from_date_of_completion = @$learningPlanRequirement['fromDateOfCompletion'];
                $OrganizationLearningPlanRequirement->from_date_of_assignment = @$learningPlanRequirement['fromDateOfAssignment'];
                $OrganizationLearningPlanRequirement->modified_id = $authId;
                $OrganizationLearningPlanRequirement->save();
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Learning Plan has been updated successfully.'],200);

    }

    public function deleteOrgLearningPlanById($id){
        $authId = Auth::user()->user_id;
        $OrganizationLearningPlan = OrganizationLearningPlan::where('is_active','!=','0')->find($id);
        if(is_null($OrganizationLearningPlan)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning Plan not found.'],400);
        }
        $OrganizationLearningPlan->is_active = 0; 
        $OrganizationLearningPlan->modified_id = $authId;
        $OrganizationLearningPlan->save();


        $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::where('learning_plan_id',$id);
        if($OrganizationLearningPlanRequirement->count() > 0){
            $OrganizationLearningPlanRequirement->update([
                'is_active' => 0,
                'modified_id' => $authId,
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Learning Plan has been deleted successfully.'],200); 
    }

    public function deleteOrgLearningPlanRequirementById($id){
        $authId = Auth::user()->user_id;
        $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::where('is_active','!=','0')->find($id);
        if(is_null($OrganizationLearningPlanRequirement)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Learning plan requirement not found.'],400);
        }
        $OrganizationLearningPlanRequirement->is_active = 0; 
        $OrganizationLearningPlanRequirement->modified_id = $authId;
        $OrganizationLearningPlanRequirement->save();
        return response()->json(['status'=>true,'code'=>200,'message'=>'Learning plan requirement has been deleted successfully.'],200); 
    }

    public function getOrgRequirementListByLearningPlanId($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $OrganizationTrainingLibrarys = OrganizationTrainingLibrary::
        join('lms_training_types','lms_training_types.training_type_id','=','lms_org_training_library.training_type_id')
        ->where('lms_org_training_library.is_active','1')->where('lms_org_training_library.org_id',$organizationId)
        ->select('lms_org_training_library.training_id as requirementId','lms_org_training_library.training_name as requirementName','lms_training_types.training_type as type','lms_org_training_library.category_id as category')
        ->get();
        if($OrganizationTrainingLibrarys->count() > 0){
            foreach($OrganizationTrainingLibrarys as $OrganizationTrainingLibrary){

                if(!empty($OrganizationTrainingLibrary->category)){
                    $categoryId = explode(',',$OrganizationTrainingLibrary->category);
                    $OrganizationTrainingLibrary->category = OrganizationCategory::whereIn('category_id',$categoryId)->pluck('category_name');
                }else{
                    $OrganizationTrainingLibrary->category = []; 
                }

                $OrganizationLearningPlanRequirement = OrganizationLearningPlanRequirement::where('is_active','!=','0')->where('learning_plan_id',$id)->where('requirement_id',$OrganizationTrainingLibrary->requirementId);
                if($OrganizationLearningPlanRequirement->count() > 0){
                    if($OrganizationLearningPlanRequirement->first()->is_active == 1){
                        $OrganizationTrainingLibrary->isChecked = 1;
                    }else{
                        $OrganizationTrainingLibrary->isChecked = 0;
                    }
                }else{
                    $OrganizationTrainingLibrary->isChecked = 0;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationTrainingLibrarys],200);
    }

    public function getUserListByLearningPlanId($id){

        $organizationId = Auth::user()->org_id;
        $users = User::where('is_active','=','1')
        ->where('org_id',$organizationId)
        ->select('user_id as userId','first_name as firstName','last_name as lastName')
        ->get();
        if($users->count() > 0){
            foreach($users as $user){
                $learningPlanUserAssignment = LearningPlanUserAssignment::where('user_id',$user->userId)->where('org_id',$organizationId)->where('learning_plan_id',$id);
                if($learningPlanUserAssignment->count() > 0){
                    $user->isChecked = $learningPlanUserAssignment->first()->is_active;
                }else{
                    $user->isChecked = 0;
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }

    public function learningPlanUserAssignment(Request $request){

        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'users' => 'required|array',
            'learningPlanIds' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $users = $request->users;
        $learningPlanIds = $request->learningPlanIds;

        foreach($users as $user){

            $userId = @$user['id'];
            $isChecked = @$user['isChecked'] ? $user['isChecked'] : 0;

            foreach($learningPlanIds as $learningPlanId){

                $learningPlanUserAssignment = LearningPlanUserAssignment::where('user_id',$userId)->where('org_id',$organizationId)->where('learning_plan_id',$learningPlanId);
                if($learningPlanUserAssignment->count() > 0){

                    $learningPlanUserAssignment->update([
                        'is_active' => $isChecked
                    ]);

                }else{
                    $learningPlanUserAssignment = new LearningPlanUserAssignment;
                    $learningPlanUserAssignment->learning_plan_id = $learningPlanId;
                    $learningPlanUserAssignment->user_id = $userId;
                    $learningPlanUserAssignment->org_id = $organizationId;
                    $learningPlanUserAssignment->is_active = $isChecked;
                    $learningPlanUserAssignment->save();
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Learning plan assignment successfully.'],200);
    }


}
