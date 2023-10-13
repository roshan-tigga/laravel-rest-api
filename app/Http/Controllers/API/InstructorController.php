<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\RoleMaster;
use Illuminate\Http\Request;

use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Illuminate\Support\Facades\Redis;

class InstructorController extends BaseController
{
    public function getInstructorList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'user.user_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'firstName'){
            $sortColumn = 'user.first_name';
        }elseif($sort == 'lastName'){
            $sortColumn = 'user.last_name';
        }elseif($sort == 'email'){
            $sortColumn = 'user.email_id';
        }elseif($sort == 'phone'){
            $sortColumn = 'user.phone_number';
        }elseif($sort == 'roleName'){
            $sortColumn = 'role.role_name';
        }elseif($sort == 'isActive'){
            $sortColumn = 'user.is_active';
        }

        $users = DB::table('lms_user_master as user')
        ->leftJoin('lms_roles as role','user.role_id','=','role.role_id')
        ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')
        ->where(function($query) use ($request,$search){
            if($search != ''){
                $query->where('user.first_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('user.last_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('user.email_id', 'LIKE', '%'.$search.'%');
                $query->orWhere('user.phone_number', 'LIKE', '%'.$search.'%');
                $query->orWhere('role.role_name', 'LIKE', '%'.$search.'%');
            }
        })
        ->where('user.role_id','5')
        ->where('user.is_active','1')
        ->where('user.role_id','!=','0')
        ->where('user.role_id','!=','4')
        ->where(function($query) use ($organizationId,$roleId,$authId){
            if($roleId != 1){
                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('user.user_id as userId', 'user.first_name as firstName', 'user.last_name as lastName', 'user.email_id as email', 'user.phone_number as phone', 'role.role_name as roleName', 'user.is_active as isActive')
        ->get();
        foreach($users as $user){
            $user->groups = DB::table('lms_user_org_group as userGroup')
                ->leftjoin('lms_group_org as group','userGroup.group_id','=','group.group_id')
                ->where('userGroup.is_active','1')
                ->where('group.is_active','1')
                ->where('userGroup.user_id',$user->userId)
                ->where('userGroup.org_id',$organizationId)
                ->pluck('group.group_name');

            $user->categoryAssiged = DB::table('lms_org_user_category_assignment as categoryAssignment')
                ->leftjoin('lms_org_category as category','categoryAssignment.category_id','=','category.category_id')
                ->where('categoryAssignment.is_active','1')
                ->where('category.is_active','1')
                ->where('categoryAssignment.user_id',$user->userId)
                ->where('categoryAssignment.org_id',$organizationId)
                ->pluck('category.category_name as categoryName');
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$users],200);
    }

    public function getInstructorUserList()
    {
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;
        $authId = Auth::user()->user_id;

        $users = DB::table('lms_user_master as user')
        ->leftJoin('lms_org_master as org','user.org_id','=','org.org_id')
        ->where('user.is_active','!=','0')
        ->where('user.is_active','!=','4')
        ->where('user.is_active','1')
        ->where(function($query) {
            $query->orWhere('user.role_id','=','3');
            $query->orWhere('user.role_id','=','4');
            $query->orWhere('user.role_id','=','6');
        })
        ->where(function($query) use ($organizationId,$roleId,$authId){
            if($roleId != 1){
                $query->where('user.org_id',$organizationId);
                $query->where('user.role_id','>',$roleId);
                $userArray = userArray($authId,$roleId,$organizationId);
                $query->whereIn('user.user_id',$userArray);  
            }
        })
        ->select('user.user_id as userId', 'user.first_name as firstName','user.last_name as lastName','user.role_id as roleId')
        ->get();

        $userData = [];
        $usersData = [];

        if($users->count() > 0){
            foreach($users as $user){
                $userData['userId'] = $user->userId;
                $userData['firstName'] = $user->firstName;
                $userData['lastName'] = $user->lastName;
                $userData['groupnsAssignedNames'] = DB::table('lms_user_org_group as userGroup')
                ->leftjoin('lms_group_org as group','userGroup.group_id','=','group.group_id')
                ->where('userGroup.is_active','1')
                ->where('group.is_active','1')
                ->where('userGroup.user_id',$user->userId)
                ->where('userGroup.org_id',$organizationId)
                ->pluck('group.group_name');
                $usersData[] = $userData;
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$usersData],200);
    }

    public function bulkDeleteInstructor(Request $request){

        $organizationId = Auth::user()->org_id;
        try{
            if(!empty($request->instructorIds)){
                foreach($request->instructorIds as $instructorId){
                    $user = User::where('is_active','!=','0')->where('role_id','5')->where('org_id',$organizationId)->where('user_id',$instructorId);
                    if($user->count() > 0){

                        $user->update([
                            'is_active' => '0',
                        ]);

                        Redis::del('userRedis' . $instructorId);
                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'Instructor has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Instructor is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function userToInstructor(Request $request){
        try{
            if(!empty($request->userIds)){
                foreach($request->userIds as $userId){
                    $user = User::where('is_active','!=','0')->where('user_id',$userId);
                    if($user->count() > 0){
                        $user->update([
                            'role_id' => 5,
                        ]);
                    }
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'User role has been updated successfully.'],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getRoleListForInstructor(){
        $roles = RoleMaster::where('is_active','1')
        ->where(function($query) {
            $query->orWhere('role_id','=','3');
            $query->orWhere('role_id','=','4');
            $query->orWhere('role_id','=','6');
            $query->orWhere('role_id','=','7');
        })
        ->select('role_id as roleId','role_name as roleName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$roles],200);
    }

}
