<?php

namespace App\Http\Controllers\API;

use App\Models\Permission;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Carbon\Carbon;

class PermissionController extends BaseController
{
    public function getPermissionList(){
        $permission = DB::table('lms_permission as permission')
        ->leftJoin('lms_module_master as module','permission.module_id','=','module.module_id')
        ->leftJoin('lms_actions_master as actions','permission.actions_id','=','actions.actions_id')
        ->leftJoin('lms_org_master as org','permission.org_id','=','org.org_id')
        ->leftJoin('lms_roles as role','permission.role_id','=','role.role_id')
        ->where('permission.is_active','!=','0')
        ->where('module.is_active','1')
        ->where('actions.is_active','1')
        ->where('org.is_active','1')
        ->where('role.is_active','1')
        ->orderBy('permission.permission_id','DESC')
        ->select('permission.permission_id as permissionId', 'module.module_name as moduleName', 'actions.action_name as actionName', 'org.organization_name as organizationName', 'role.role_name as roleName', 'permission.read_access as readAccess', 'permission.write_access as writeAccess', 'permission.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$permission],200);
    }

    public function addNewPermission(Request $request){
        $authId = Auth::id();
        $validator = Validator::make($request->all(), [
            'moduleId' => 'nullable|integer',
            'actionsId' => 'nullable|integer',
            'organizationId' => 'nullable|integer',
            'roleId' => 'nullable|integer',
            'read_access'=> 'nullable|boolean',
            'write_access'=> 'nullable|boolean',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $permission = new Permission;
            $permission->module_id = $request->moduleId;
            $permission->actions_id = $request->actionsId;
            $permission->org_id = $request->organizationId;
            $permission->role_id = $request->roleId;
            $permission->read_access = $request->readAccess;
            $permission->write_access = $request->writeAccess;
            $permission->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $permission->created_id = $authId;
            $permission->modified_id = $authId;
            $permission->save();

            return response()->json(['status'=>true,'code'=>200,'message'=>'Permission has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    

    public function getPermissionById($permissionId){
        try{
            $permissionRedis = Redis::get('permissionRedis' . $permissionId);
            if(isset($permissionRedis)){
                return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($permissionRedis,false)],200);
            }else{
                $permission = DB::table('lms_permission as permission')->where('permission.is_active','!=','0')->where('permission.permission_id',$permissionId);
                if ($permission->count() < 1) {
                    return response()->json(['status'=>false,'code'=>404,'error'=>'Permission is not found.'], 404);
                }
                $permission = $permission->leftJoin('lms_module_master as module','permission.module_id','=','module.module_id')
                ->leftJoin('lms_actions_master as actions','permission.actions_id','=','actions.actions_id')
                ->leftJoin('lms_org_master as org','permission.org_id','=','org.org_id')
                ->leftJoin('lms_roles as role','permission.role_id','=','role.role_id')
                ->where('module.is_active','1')
                ->where('actions.is_active','1')
                ->where('org.is_active','1')
                ->where('role.is_active','1')
                ->select('permission.permission_id as permissionId', 'permission.module_id as moduleId', 'module.module_name as moduleName', 'permission.actions_id as actionsId', 'actions.action_name as actionName', 'permission.org_id as organizationId', 'org.organization_name as organizationName', 'permission.role_id as roleId', 'role.role_name as roleName', 'permission.read_access as readAccess', 'permission.write_access as writeAccess', 'permission.is_active as isActive')->first();
                Redis::set('permissionRedis' . $permissionId, json_encode($permission,false));
                return response()->json(['status'=>true,'code'=>200,'data'=>$permission],200);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function updatePermission(Request $request){
        $authId = Auth::id();
        $validator = Validator::make($request->all(), [
            'permissionId'=>'required|integer',
            'moduleId' => 'nullable|integer',
            'actionsId' => 'nullable|integer',
            'organizationId' => 'nullable|integer',
            'roleId' => 'nullable|integer',
            'readAccess'=> 'nullable|boolean',
            'writeAccess'=> 'nullable|boolean',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $permission = Permission::where('is_active','!=','0')->where('permission_id',$request->permissionId);
            if ($permission->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Permission is not found.'], 404);
            }
            
            $permission->update([
                'module_id' => $request->moduleId,
                'actions_id' => $request->actionsId,
                'org_id' => $request->organizationId,
                'role_id' => $request->roleId,
                'read_access' => $request->readAccess,
                'write_access' => $request->writeAccess,
                'is_active' => $request->isActive == '' ? $permission->first()->is_active ? $permission->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

            $permission = DB::table('lms_permission as permission')
            ->leftJoin('lms_module_master as module','permission.module_id','=','module.module_id')
            ->leftJoin('lms_actions_master as actions','permission.actions_id','=','actions.actions_id')
            ->leftJoin('lms_org_master as org','permission.org_id','=','org.org_id')
            ->leftJoin('lms_roles as role','permission.role_id','=','role.role_id')
            ->where('permission.is_active','!=','0')
            ->where('module.is_active','1')
            ->where('actions.is_active','1')
            ->where('org.is_active','1')
            ->where('role.is_active','1')
            ->where('permission.permission_id',$request->permissionId)
            ->select('permission.permission_id as permissionId', 'permission.module_id as moduleId', 'module.module_name as moduleName', 'permission.actions_id as actionsId', 'actions.action_name as actionName', 'permission.org_id as organizationId', 'org.organization_name as organizationName', 'permission.role_id as roleId', 'role.role_name as roleName', 'permission.read_access as readAccess', 'permission.write_access as writeAccess', 'permission.is_active as isActive')
            ->first();
            
            Redis::del('permissionRedis' . $request->permissionId);
            Redis::set('permissionRedis' . $request->permissionId, json_encode($permission,false));

            return response()->json(['status'=>true,'code'=>200,'message'=>'Permission has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deletePermission(Request $request){
        $validator = Validator::make($request->all(), [
            'permissionId'=>'required|integer',
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        try{
            $permission = Permission::where('is_active','!=','0')->where('permission_id',$request->permissionId);
            if($permission->count() > 0){

                $permission->update([
                    'is_active' => '0',
                ]);

                Redis::del('permissionRedis' . $request->permissionId);

                return response()->json(['status'=>true,'code'=>200,'message'=>'Permission has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Permission is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getPermissionsByOrganizationIdAndRoleId(Request $request){
        
        $validator = Validator::make($request->all(), [
            'organizationId'=>'required|integer',
            'roleId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $sort = $request->has('sort') ? $request->get('sort') : 'permission_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'actionsName'){
            $sortColumn = 'action.action_name';
        }elseif($sort == 'readAccessPermission'){
            $sortColumn = 'permission.read_access';
        }elseif($sort == 'writeAccessPermission'){
            $sortColumn = 'permission.write_access';
        }

       
        $allData = [];

        $modules = DB::table('lms_module_master as module')
        //->Join('lms_menu_master as menu','module.menu_master_id','=','menu.menu_master_id')
        ->where('module.is_active','1')
        //->where('menu.is_active','1')
        //->whereRaw('FIND_IN_SET(?, menu.roles)', [$request->roleId])
        //->where('module.parent_module_id','!=','0')
        ->whereNotNull('module.parent_module_id')
        ->when($sort=='moduleName',function($query) use ($order){ 
            return $query->orderBy("module.module_name",$order);
        })
        ->select('module.module_id','module.module_name')
        ->get();
        if($modules->count()>0){
            foreach($modules as $module){
                $data = $actions = [];
                $permissions = DB::table('lms_permission as permission')
                ->join('lms_actions_master as action','permission.actions_id','=','action.actions_id')
                ->where('permission.module_id',$module->module_id)
                ->where('permission.org_id',$request->organizationId)
                ->where('permission.role_id',$request->roleId)
                ->where('permission.is_active','1')
                ->where('action.is_Active','1')
                ->when($sort!='moduleName',function($query) use ($sortColumn,$order){ 
                    return $query->orderBy($sortColumn,$order);
                })
                ->where(function($query) use ($search){
                    if($search != ''){
                        $query->where('action.action_name', 'LIKE', '%'.$search.'%');
                        $query->orWhere('module.module_name', 'LIKE', '%'.$search.'%');
                    }
                })
                ->select('permission.permission_id','permission.actions_id','action.action_name','permission.read_access','permission.write_access')
                ->get();
                if($permissions->count()>0){
                    foreach($permissions as $permission){
                        $actions[] = [
                            'permissionId' => $permission->permission_id,
                            'actionsId' => $permission->actions_id,
                            'actionsName' => $permission->action_name, 
                            'readAccessPermission' => $permission->read_access,
                            'writeAccessPermission' => $permission->write_access
                        ];
                    }
                }else{
                    $permissions = DB::table('lms_actions_master')
                    ->where('is_Active','1')
                    ->where('module_id',$module->module_id)
                    ->select('actions_id','action_name')
                    ->get();
                    if($permissions->count()>0){
                        foreach($permissions as $permission){
                            $actions[] = [
                                'permissionId' => '',
                                'actionsId' => $permission->actions_id, 
                                'actionsName' => $permission->action_name, 
                                'readAccessPermission' => 0,
                                'writeAccessPermission' => 0
                            ];
                        }
                    }
                }

                $data['moduleId'] = $module->module_id;
                $data['moduleName'] = $module->module_name; 
                $data['actions'] = $actions;
                $allData[] = $data;
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);


        exit;

        $sort = $request->has('sort') ? $request->get('sort') : 'permission_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'actionsName'){
            $sortColumn = 'action.action_name';
        }elseif($sort == 'readAccessPermission'){
            $sortColumn = 'permission.read_access';
        }elseif($sort == 'writeAccessPermission'){
            $sortColumn = 'permission.write_access';
        }
        

        $modulePermissions = DB::table('lms_permission as permission')
        ->join('lms_module_master as module','permission.module_id','=','module.module_id')
        ->where('permission.org_id',$request->organizationId)->where('permission.role_id',$request->roleId)->where('permission.is_Active','1')->where('module.is_Active','1')
        ->when($sort=='moduleName',function($query) use ($order){ 
            return $query->orderBy("module.module_name",$order);
        });
        if($modulePermissions->count() > 0){

            $allData = [];
           
            $modulePermissions = $modulePermissions->select('permission.module_id as moduleId')->groupBy('permission.module_id')->get();
            foreach($modulePermissions as $modulePermission){

                $data = $action = $actions = [];
                
                $permissions = DB::table('lms_permission as permission')
                ->join('lms_actions_master as action','permission.actions_id','=','action.actions_id')
                ->join('lms_module_master as module','permission.module_id','=','module.module_id')
                ->where('permission.module_id',$modulePermission->moduleId)->where('permission.org_id',$request->organizationId)->where('permission.role_id',$request->roleId)->where('permission.is_Active','1')
                ->where('action.is_Active','1')
                ->where('module.is_Active','1')
                ->orderBy($sortColumn,$order)
                ->where(function($query) use ($search){
                    if($search != ''){
                        $query->where('action.action_name', 'LIKE', '%'.$search.'%');
                        $query->orWhere('module.module_name', 'LIKE', '%'.$search.'%');
                    }
                })
                ;
                if($permissions->count() > 0){
                    $permissions = $permissions->select('permission.permission_id as permissionId','permission.actions_id as actionsId', 'action.action_name as actionName','permission.module_id as moduleId', 'module.module_name as moduleName','permission.read_access as readAccessPermission','permission.write_access as writeAccessPermission')->get();
                    foreach($permissions as $permission){
                        $action = [
                            'permissionId' => $permission->permissionId,
                            'actionsId' => $permission->actionsId,
                            'actionsName' => $permission->actionName, //DB::table('lms_actions_master')->where('actions_id',$permission->actionsId)->first()->action_name,
                            'readAccessPermission' => $permission->readAccessPermission,
                            'writeAccessPermission' => $permission->writeAccessPermission
                        ];
                        $actions[] = $action;
                    }
                    $data['moduleId'] = $permission->moduleId;
                    $data['moduleName'] = $permission->moduleName; //DB::table('lms_module_master')->where('is_Active','1')->where('module_id',$modulePermission->moduleId)->first()->module_name;
                    $data['actions'] = $actions;
                    $allData[] = $data;
                }
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Permission is not found.'], 404);
        }
    }

    public function addNewMultiplePermissions(Request $request){
        $authId = Auth::id();
        $data = [];
        $validator = Validator::make($request->all(), [
            'permissions'=>'required|array',
            'permissions.*.permissionId' => 'nullable|integer',
            'permissions.*.moduleId' => 'nullable|integer',
            'permissions.*.actionsId' => 'nullable|integer',
            'permissions.*.organizationId' => 'required|integer',
            'permissions.*.roleId' => 'nullable|integer',
            'permissions.*.readAccess'=> 'nullable|boolean',
            'permissions.*.writeAccess'=> 'nullable|boolean',
            'permissions.*.isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            if(!empty($request->permissions)){
                if(is_array($request->permissions)){
                    if(count($request->permissions) > 0){
                        foreach($request->permissions as $permission){
                            if(isset($permission['permissionId'])){
                                $permissionCheck = Permission::where(['permission_id' => $permission['permissionId'],'is_active'=>'1']);
                                if($permissionCheck->count() > 0){
                                    $permissionCheck->update([
                                        'module_id' => $permission['moduleId'],
                                        'actions_id' => $permission['actionsId'],
                                        'org_id' => $permission['organizationId'],
                                        'role_id' => $permission['roleId'],
                                        'read_access' => $permission['readAccess'],
                                        'write_access' => $permission['writeAccess'],
                                        'modified_id' => $authId,
                                        'date_modified' => Carbon::now()
                                    ]);
                                }else{
                                    $permissionCheck = Permission::where(['module_id' => $permission['moduleId'],'actions_id' => $permission['actionsId'], 'org_id' => $permission['organizationId'],'role_id' => $permission['roleId'],'is_active'=>'1']);
                                    if($permissionCheck->count() > 0){
                                        
                                    }else{
                                        $data[] = [
                                            'module_id' => $permission['moduleId'],
                                            'actions_id' => $permission['actionsId'],
                                            'org_id' => $permission['organizationId'],
                                            'role_id' => $permission['roleId'],
                                            'read_access' => $permission['readAccess'],
                                            'write_access' => $permission['writeAccess'],
                                            'is_active' => $permission['isActive'] == '' ? '1' : $permission['isActive'],
                                            'created_id' => $authId,
                                            'modified_id' => $authId,
                                            'date_created' => Carbon::now(),
                                            'date_modified' => Carbon::now()
                                        ];
                                    }
                                }
                            }else{
                                $permissionCheck = Permission::where(['module_id' => $permission['moduleId'],'actions_id' => $permission['actionsId'], 'org_id' => $permission['organizationId'],'role_id' => $permission['roleId'],'is_active'=>'1']);
                                if($permissionCheck->count() > 0){
                                        
                                }else{
                                    $data[] = [
                                        'module_id' => $permission['moduleId'],
                                        'actions_id' => $permission['actionsId'],
                                        'org_id' => $permission['organizationId'],
                                        'role_id' => $permission['roleId'],
                                        'read_access' => $permission['readAccess'],
                                        'write_access' => $permission['writeAccess'],
                                        'is_active' => $permission['isActive'] == '' ? '1' : $permission['isActive'],
                                        'created_id' => $authId,
                                        'modified_id' => $authId,
                                        'date_created' => Carbon::now(),
                                        'date_modified' => Carbon::now()
                                    ];
                                }
                            } 
                        }
                    }
                }
            }
            if(!empty($data)){
                Permission::insert($data); 
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Permissions has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

}
