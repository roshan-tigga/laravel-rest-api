<?php

namespace App\Http\Controllers\API;

use App\Models\UserGroup;
use App\Models\GroupOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class UserGroupController extends Controller
{
    public function userOrgGroupAssign(Request $request){

        $validator = Validator::make($request->all(), [
            'users' => 'required',
            'groups' => 'required'
        ]);

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $authId = Auth::user()->user_id;
            $organizationId = Auth::user()->org_id;

            $userData = [];

            if(isset($request->groups)){

                if(is_array($request->groups) && is_array($request->users)){

                    if(count($request->groups) > 0 && count($request->users) > 0){

                        foreach($request->users as $userId){

                            foreach($request->groups as $group){

                                $groupId = $group['groupId'];
                                $checked = ($group['checked'] == 1) ? 1 : 0;

                                $userGroup = DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($userGroup->count() > 0){
                                    $userGroup->update([
                                        'is_active'=>$checked,
                                        'modified_id' => $authId,
                                    ]);
                                }else{
                                    DB::table('lms_user_org_group')->insert([
                                        'is_active' => $checked,
                                        'group_id' => $groupId,
                                        'user_id' => $userId,
                                        'org_id' => $organizationId,
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                        'date_created'=> Carbon::now(),
                                        'date_modified'=> Carbon::now()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'User group assigned successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function groupOrgUserAssign(Request $request){

        if(is_array($request->groups)){
            $validator = Validator::make($request->all(), [
                'users' => 'required|array',
                'users.*' => 'integer',
                'groups' => 'required|array',
                'groups.*' => 'integer'
            ]);
        }else{
            $validator = Validator::make($request->all(), [
                'users' => 'required|array',
                'users.*.userId' => 'integer',
                'groupId' => 'required|integer'
            ]);
        }

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        if(is_array($request->groups)){
            if(isset($request->users)){

                $groups = GroupOrganization::where('org_id',$organizationId)
                ->whereIn('group_id',$request->groups)
                ->orWhereIn('primary_group_id',$request->groups)->pluck('group_id')->toArray();

                if(is_array($groups) && is_array($request->users)){

                    if(count($groups) > 0 && count($request->users) > 0){

                        foreach($groups as $groupId){

                            foreach($request->users as $userId){

                                $userGroup = DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($userGroup->count() > 0){

                                }else{
                                    DB::table('lms_user_org_group')->insert([
                                        'is_active' => 1,
                                        'group_id' => $groupId,
                                        'user_id' => $userId,
                                        'org_id' => $organizationId,
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                        'date_created'=> Carbon::now(),
                                        'date_modified'=> Carbon::now()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
        }else{

            $groups = GroupOrganization::where('org_id',$organizationId)
            ->where('group_id',$request->groupId)
            ->orWhere('primary_group_id',$request->groupId)->pluck('group_id')->toArray();

            if(is_array($groups) && is_array($request->users)){

                if(count($groups) > 0 && count($request->users) > 0){

                    foreach($groups as $groupId){

                        foreach($request->users as $user){

                            $userId = $user['userId'];
                            $isChecked = ($user['isChecked'] == 1) ? 1 : 0;

                            $userGroup = DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                            if($userGroup->count() > 0){
                                $userGroup->update([
                                    'is_active' => $isChecked,
                                    'modified_id' => $authId,
                                    'date_modified'=> Carbon::now()
                                ]);
                            }else{
                                DB::table('lms_user_org_group')->insert([
                                    'is_active' => $isChecked,
                                    'group_id' => $groupId,
                                    'user_id' => $userId,
                                    'org_id' => $organizationId,
                                    'created_id' => $authId,
                                    'modified_id' => $authId,
                                    'date_created'=> Carbon::now(),
                                    'date_modified'=> Carbon::now()
                                ]);
                            }
                        }
                    }
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Group user assigned successfully.'],200);
    }

    public function userGroupAssign(Request $request){

        $validator = Validator::make($request->all(), [
            'users' => 'required',
            'groups' => 'required'
        ]);

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $authId = Auth::user()->user_id;
            $organizationId = Auth::user()->org_id;

            $userData = [];

            if(isset($request->groups)){

                if(is_array($request->groups) && is_array($request->users)){

                    if(count($request->groups) > 0 && count($request->users) > 0){

                        foreach($request->users as $userId){

                            foreach($request->groups as $group){

                                $groupId = $group['groupId'];
                                $checked = ($group['checked'] == 1) ? 1 : 0;

                                $userGroup = UserGroup::where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($userGroup->count() > 0){
                                    $userGroup->update([
                                        'is_active'=>$checked,
                                        'modified_id' => $authId,
                                    ]);
                                }else{
                                    UserGroup::insert([
                                        'is_active' => $checked,
                                        'group_id' => $groupId,
                                        'user_id' => $userId,
                                        'org_id' => $organizationId,
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                        'date_created'=> Carbon::now(),
                                        'date_modified'=> Carbon::now()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'User group assigned successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function groupUserAssign(Request $request){

        $validator = Validator::make($request->all(), [
            'users' => 'required',
            'groups' => 'required'
        ]);

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $authId = Auth::user()->user_id;
            $organizationId = Auth::user()->org_id;

            $userData = [];

            if(isset($request->users)){

                if(is_array($request->groups) && is_array($request->users)){

                    if(count($request->groups) > 0 && count($request->users) > 0){

                        foreach($request->groups as $groupId){

                            foreach($request->users as $user){

                                $userId = $user['userId'];
                                $checked = ($user['checked'] == 1) ? 1 : 0;

                                $userGroup = UserGroup::where('group_id',$groupId)->where('user_id',$userId)->where('org_id',$organizationId);
                                if($userGroup->count() > 0){
                                    $userGroup->update([
                                        'is_active'=>$checked,
                                        'modified_id' => $authId,
                                    ]);
                                }else{
                                    UserGroup::insert([
                                        'is_active' => $checked,
                                        'group_id' => $groupId,
                                        'user_id' => $userId,
                                        'org_id' => $organizationId,
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                        'date_created'=> Carbon::now(),
                                        'date_modified'=> Carbon::now()
                                    ]);
                                }
                            }
                        }
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Group user assigned successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getOrgUserGroupUnassignmentList($userId){
        
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $groupArray = [];
        $groupsArray = [];
        
        $groups = DB::table('lms_group_org')->where('org_id',$organizationId)->where('is_active','1');
        if($groups->count() > 0){
            foreach($groups->get() as $group){

                $userGroups = DB::table('lms_user_org_group')
                ->where('org_id',$organizationId)
                ->where('group_id',$group->group_id)
                ->where('user_id',$userId)
                ->where('is_active','1');
                if($userGroups->count() > 0){
                   
                }else{
                    $groupArray['groupId'] = $group->group_id;
                    $groupArray['groupName'] = $group->group_name;
                    $groupsArray[] = $groupArray;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$groupsArray],200);

    }

    public function orgUserGroupAssignment(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'userId' => 'required|integer',
            'groupIds' => 'required|array'
        ]);  

        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $groups = GroupOrganization::where('org_id',$organizationId)
        ->whereIn('group_id',$request->groupIds)
        ->orWhereIn('primary_group_id',$request->groupIds)->pluck('group_id')->toArray();

        if(isset($request->userId)){

            if(count($groups) > 0){

                foreach($groups as $groupId){

                    $userGroup = DB::table('lms_user_org_group')->where('group_id',$groupId)->where('user_id',$request->userId)->where('org_id',$organizationId);
                    if($userGroup->count() > 0){

                    }else{
                        DB::table('lms_user_org_group')->insert([
                            'is_active' => 1,
                            'group_id' => $groupId,
                            'user_id' => $request->userId,
                            'org_id' => $organizationId,
                            'created_id' => $authId,
                            'modified_id' => $authId,
                            'date_created'=> Carbon::now(),
                            'date_modified'=> Carbon::now()
                        ]);
                    }
                }
                
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'User assigned to group successfully.'],200);
    }
}
