<?php

namespace App\Http\Controllers\API;

use App\Models\UserRequirementCourse;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;


class TrainingAssignController extends BaseController
{
    public function trainingAssignToUser(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'trainingIds' => 'required',
            'userIds' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {

            if(isset($request->trainingIds)){

                if(is_array($request->trainingIds) && is_array($request->userIds)){

                    if(count($request->trainingIds) > 0 && count($request->userIds) > 0){

                        foreach($request->trainingIds as $training){

                            $trainingId = $training['trainingId'];
                            $assignId = $training['assignId'];

                            foreach($request->userIds as $userId){

                                $userRequirementCourse = UserRequirementCourse::where('org_training_id',$trainingId)->where('user_id',$userId);
                                if($userRequirementCourse->count() > 0){
            
                                    $userRequirementCourse->update([
                                        'status'=>$request->status,
                                        'modified_id' => $authId
                                    ]);
            
                                }else{
                                    $userRequirementCourse = new UserRequirementCourse;
                                    if($assignId == ''){
                                        $userRequirementCourse->org_training_id = $trainingId;
                                    }
                                    $userRequirementCourse->org_assign_training_id = $assignId;
                                    $userRequirementCourse->user_id = $userId;
                                    $userRequirementCourse->org_id = $organizationId;
                                    $userRequirementCourse->status = $request->status;
                                    $userRequirementCourse->role_id = $request->roleId;
                                    $userRequirementCourse->created_id = $authId;
                                    $userRequirementCourse->modified_id = $authId;
                                    $userRequirementCourse->save();
                                }
                            }
                        }
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Training assigned to user successfully.'],200);
            
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function addTrainingCategorytoGroupUserAssignment(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'groups' => 'nullable|array',
            'users' => 'nullable|array',
            'category' => 'nullable|array',
            'courses' => 'nullable|array' 
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(!empty($request->category) && empty($request->courses)){
            $validator = Validator::make($request->all(), [
                'category' => 'required|array',
                'courses' => 'required|array' 
            ]);
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(!empty($request->users) && !empty($request->courses)){

            foreach($request->users as $userId){
                foreach($request->courses as $courseId){
                    $check = DB::table('lms_org_assignment_user_course')->where('users',$userId)->where('courses',$courseId)->where('org_id',$organizationId);
                    if($check->count() > 0){
                        // $check->update([
                        //     'users' => $userId,
                        //     'groups' => implode(',',$request->groups),
                        //     'category' => implode(',',$request->category),
                        //     'courses' => $courseId,
                        //     'date_modified' => date('Y-m-d H:i:s'),
                        //     'modified_id' => $authId,
                        // ]);
                    }else{
                        DB::table('lms_org_assignment_user_course')->insert([
                            'users' => $userId,
                            'groups' => implode(',',$request->groups),
                            'category' => implode(',',$request->category),
                            'courses' => $courseId,
                            'date_created' => date('Y-m-d H:i:s'),
                            'date_modified' => date('Y-m-d H:i:s'),
                            'created_id' => $authId,
                            'modified_id' => $authId,
                            'org_id' => $organizationId
                        ]);
                    }
                }
            }
        }else{
            if(is_array($request->category) && is_array($request->groups)){
                if(count($request->category) > 0 && count($request->groups) > 0){
                    foreach($request->category as $categoryId){
                        foreach($request->groups as $groupId){

                            $categoryGroupAssignment = DB::table('category_group_assignment')->where('group_id',$groupId)
                            ->where('category_id',$categoryId);
                            if($categoryGroupAssignment->count() > 0){

                            }else{
                                DB::table('category_group_assignment')->insert([
                                    'category_id'=>$categoryId,
                                    'group_id'=>$groupId,
                                    'is_active'=>1,
                                    'org_id'=>$organizationId,
                                    'user_id'=>$authId,
                                    'created_id' => $authId,
                                    'modified_id' => $authId
                                ]);
                            }
                        }
                    }
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Category assigned to group successfully.'],200);
    }
}
