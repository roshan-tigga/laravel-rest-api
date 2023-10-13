<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationRole;
use DB;
use Illuminate\Http\Request;
use Validator;
use Auth;
use App\Http\Controllers\API\BaseController as BaseController;

class OrganizationRoleController extends BaseController
{ 
    public function getOrgRoleList(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $roles = OrganizationRole::where('is_active', '=', '1')->where('role_id', '!=', '1')
        ->where('assigned', '=', '1')
        ->where('su_assigned', '=', '1')
        //->where('org_role_id', $roleId)
        ->where('org_id', $organizationId)
        ->select('org_role_id as roleId', 'role_name as roleName', 'role_type as roleType', 'description', 'is_active as isActive')->get();
        if ($roles->count() > 0) {
            return response()->json(['status' => true, 'code' => 200, 'data' => $roles], 200);
        } else {
            return response()->json(['status' => true, 'code' => 200, 'data' => []], 200);
        }
    }

    public function getOrgRoleById($roleId)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $roles = OrganizationRole::leftJoin('lms_roles','lms_roles.role_id','=','lms_org_roles.role_id')
            ->where('lms_org_roles.is_active', '!=', '0')->where('lms_org_roles.org_role_id', $roleId)
            ->where('lms_org_roles.org_id', $organizationId)
            ->select('lms_org_roles.org_role_id as roleId', 'lms_org_roles.role_name as roleName', 'lms_org_roles.role_type as roleType', 'lms_org_roles.description','lms_roles.role_name as superAdminRoleName', 'lms_org_roles.is_active as isActive')->first();
        if ($roles->count() > 0) {
            return response()->json(['status' => true, 'code' => 200, 'data' => $roles], 200);
        } else {
            return response()->json(['status' => true, 'code' => 200, 'data' => []], 200);
        }
    }

    public function updateOrgRoleById(Request $request)
    {
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'roleName' => 'required|max:40',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => false, 'code' => 400, 'errors' => $validator->errors()->all()], 400);
        }

        $roleMaster = OrganizationRole::where('is_active', '=', '1')->where('org_role_id', $request->roleId)
            ->where(function ($query) use ($roleId) {
                if ($roleId != 1) {
                    $query->where('org_id', $organizationId);
                }
            });
        if ($roleMaster->count() < 1) {
            return response()->json(['status' => false, 'code' => 404, 'error' => 'Role is not found.'], 404);
        } else {
            $roleMaster->update([
                'role_name' => $request->roleName,
                'description' => $request->description,
                'is_active' => $request->isActive == '' ? $roleMaster->first()->is_active ? $roleMaster->first()->is_active : '1' : $request->isActive
            ]);
            return response()->json(['status' => true, 'code' => 200, 'message' => 'Role has been updated successfully.'], 200);
        }
    }
}