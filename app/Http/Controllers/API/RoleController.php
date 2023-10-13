<?php

namespace App\Http\Controllers\API;

use App\Models\User;
use App\Models\RoleMaster;
use App\Models\OrganizationRole;
use DB;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class RoleController extends BaseController
{
    public function getRoleList(Request $request)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'role_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if ($sort == 'roleName') {
            $sortColumn = 'role_name';
        } elseif ($sort == 'roleType') {
            $sortColumn = 'role_type';
        } elseif ($sort == 'description') {
            $sortColumn = 'description';
        } elseif ($sort == 'isActive') {
            $sortColumn = 'is_active';
        }

        $roles = RoleMaster::where('is_active', '!=', '0')
            ->where(function ($query) use ($search) {
                if ($search != '') {
                    $query->where('role_name', 'LIKE', '%' . $search . '%');
                    //$query->orWhere('role_type', 'LIKE', '%'.$search.'%');
                    $query->orWhere('description', 'LIKE', '%' . $search . '%');
                    if (in_array($search, ['active', 'act', 'acti', 'activ'])) {
                        $query->orWhere('is_active', '1');
                    }
                    if (in_array($search, ['inactive', 'inact', 'inacti', 'inactiv'])) {
                        $query->orWhere('is_active', '2');
                    }
                }
            })
            ->orderBy($sortColumn, $order)
            ->select('role_id as roleId', 'role_name as roleName', 'role_type as roleType', 'description', 'is_active as isActive')
            ->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $roles], 200);
    }

    public function getRoleOptionList(Request $reuest)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = $reuest->query('organization');
        if ($organizationId != '') {
            $roles = DB::table('lms_user_master as user')
                ->join('lms_roles as role', 'user.role_id', '=', 'role.role_id')
                ->where('user.is_active', '!=', '0')
                ->where('role.is_active', '!=', '0')
                ->where('role.role_id', '!=', '1')
                ->where('user.org_id', $organizationId);
            if ($roles->count() > 0) {
                $roles = $roles->orderBy('role.role_name', 'ASC')->select('role.role_id as roleId', 'role.role_name as roleName')->get();
            } else {
                $roles = [];
            }
        } else {
            $roles = RoleMaster::where('is_active', '!=', '0')
                ->where('role_id', '!=', '1') 
                ->where(function($query) use ($roleId){
                    if($roleId != 1){
                        if($roleId == 2){
                            $query->where('role_id','>','2');
                        }
                        if($roleId == 3){
                            $query->where('role_id','>','3');
                        }
                        if($roleId == 4){
                            $query->where('role_id','>','4');
                        }
                        if($roleId == 5){
                            $query->where('role_id','>','5');
                        }
                        if($roleId == 6){
                            $query->where('role_id','>','6');
                        }
                    }
                })
                ->select('role_id as roleId', 'role_name as roleName')
                ->get();
        }
        return response()->json(['status' => true, 'code' => 200, 'data' => $roles], 200);
    }

    public function addNewRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roleName' => 'required|max:40',
            'roleType' => 'required|max:60',
            'description' => 'required|max:255',
            'isActive' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $roleMaster = new RoleMaster;
        $roleMaster->role_name = $request->roleName;
        $roleMaster->role_type = $request->roleType;
        $roleMaster->description = $request->description;
        $roleMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $roleMaster->save();

        return response()->json(['status' => true, 'code' => 201, 'message' => 'Role has been created successfully.'], 201);
    }

    public function getRoleById($roleId)
    {
        $roleMaster = RoleMaster::where('is_active', '!=', '0')->where('role_id', $roleId);
        if ($roleMaster->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Role is not found.'], 404);
        }
        $roleMaster = $roleMaster->select('role_id as roleId', 'role_name as roleName', 'role_type as roleType', 'description', 'is_active as isActive')->first();
        $roleMaster->users = User::join('lms_user_login','lms_user_master.user_id','=','lms_user_login.user_id')
            ->leftJoin('lms_org_master','lms_user_master.org_id','=','lms_org_master.org_id')
            ->where('lms_user_master.is_active','1')->where('lms_user_master.role_id',$roleId)->select('lms_user_master.user_id as userId','lms_user_master.org_id as organizationId','lms_org_master.organization_name as organizationName','lms_user_login.user_name as username','lms_user_master.first_name as firstName','lms_user_master.last_name as lastName')->get();
        return response()->json(['status' => true, 'code' => 200, 'data' => $roleMaster], 200);
    }

    public function updateRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roleId' => 'required|integer',
            'roleName' => 'required|max:40',
            //'roleType' => 'required|max:60',
            'description' => 'required|max:255',
            'isActive' => 'integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $roleMaster = RoleMaster::where('is_active', '!=', '0')->where('role_id', $request->roleId);
        if ($roleMaster->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Role is not found.'], 404);
        } else {

            $roleMaster->update([
                'role_name' => $request->roleName,
                //'role_type' => $request->roleType,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $roleMaster->first()->is_active ? $roleMaster->first()->is_active : '1' : $request->isActive
            ]);

            $OrganizationRole = OrganizationRole::where('role_id',$request->roleId);
            if($OrganizationRole->count() > 0){
                $OrganizationRole->update([
                    'is_active' => $request->isActive
                ]);
            }

            return response()->json(['status' => true, 'code' => 200, 'message' => 'Role has been updated successfully.'], 200);
        }
    }

    public function deleteRole(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'roleId' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        try {
            $roleMaster = RoleMaster::where('is_active', '!=', '0')->where('role_id', $request->roleId);
            if ($roleMaster->count() > 0) {

                $roleMaster->update([
                    'is_active' => '0',
                ]);

                return response()->json(['status' => true, 'code' => 200, 'message' => 'Role has been deleted successfully.'], 200);
            } else {
                return response()->json(['status' => false, 'code' => 404, 'error' => 'Role is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status' => false, 'code' => 501, 'message' => $e->getMessage()], 501);
        }
    }

    public function getRoleListByOrg()
    {
        $organizationId = Auth::user()->org_id;
        $roles = OrganizationRole::
            join('lms_roles', 'lms_org_roles.role_id', '=', 'lms_roles.role_id')
            ->where('lms_org_roles.is_active', '1')
            ->where('lms_org_roles.org_id', $organizationId)
            ->select('lms_org_roles.org_role_id as roleId', 'lms_org_roles.role_name as roleName', 'lms_org_roles.role_type as roleType', 'lms_org_roles.description', 'lms_roles.role_name as superAdminRoleName', 'lms_org_roles.is_active as isActive')
            ->get();
        if ($roles->count() > 0) {
            return response()->json(['status' => true, 'code' => 200, 'data' => $roles], 200);
        } else {
            return response()->json(['status' => true, 'code' => 200, 'data' => []], 200);
        }
    }

    public function getRoleListByOrgId(Request $request,$organizationId)
    {
        $sort = $request->has('sort') ? $request->get('sort') : 'lms_org_roles.assigned';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $organizationId = ($organizationId == 0) ? $organizationId = Auth::user()->org_id  : $organizationId;

        $sortColumn = $sort;
        $OrganizationRole = OrganizationRole::
        join('lms_roles', 'lms_org_roles.role_id', '=', 'lms_roles.role_id')
        ->where('lms_org_roles.role_id', '!=','1')
        ->where('lms_org_roles.is_active', '=','1')
        ->where('lms_org_roles.su_assigned','=','1')
        ->where(function($query) use ($organizationId){
            //if($organizationId != 0){
                $query->where('lms_org_roles.org_id',$organizationId);
            //}
        })
        ->orderBy($sortColumn,$order)
        ->select('lms_org_roles.org_role_id as roleId', 'lms_org_roles.role_name as roleName', 'lms_org_roles.role_type as roleType', 'lms_org_roles.description', 'lms_roles.role_name as superAdminRoleName','lms_org_roles.assigned as isChecked')
        ->get();
        if ($OrganizationRole->count() > 0) {
            return response()->json(['status' => true, 'code' => 200, 'data' => $OrganizationRole], 200);
        } else {
            return response()->json(['status' => true, 'code' => 200, 'data' => []], 200);
        }
    }

    public function bulkUpdateOrgRole($organizationId, Request $request)
    {
        $authId = Auth::user()->user_id;
        // $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'roles' => 'required|array',
            'roles.*.roleId' => 'required|integer',
            'roles.*.roleName' => 'required',
            'roles.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        foreach ($request->roles as $role) {

            $roleId = $role['roleId'];
            $roleName = $role['roleName'];
            $isChecked = $role['isChecked'];

            $organizationRole = OrganizationRole::where('org_role_id', $roleId)->where('org_id', $organizationId);
            if ($organizationRole->count() > 0) {
                $organizationRole->update([
                    'role_name' => $roleName,
                    'assigned' => $isChecked
                ]);
            }
        }
        return response()->json(['status' => true, 'code' => 200, 'message' => 'Role has been updated successfully.'], 200);
    }

}