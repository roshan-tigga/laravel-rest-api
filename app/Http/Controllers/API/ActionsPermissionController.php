<?php

namespace App\Http\Controllers\API;

use App\Models\ActionMaster;
use App\Models\ModuleMaster;
use App\Models\Permission;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;

class ActionsPermissionController extends BaseController
{
    public function getActionsPermissionList(Request $request){

        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $modulePermissions = Permission::where('org_id',$organizationId)->where('role_id',$roleId)->where('is_Active','1');
        if($modulePermissions->count() > 0){

            $allData = [];
           
            $modulePermissions = $modulePermissions->select('module_id as moduleId')->groupBy('moduleId')->get();
            foreach($modulePermissions as $modulePermission){

                $data = $action = $actions = [];
                $permissions = Permission::where('module_id',$modulePermission->moduleId)->where('org_id',$organizationId)->where('role_id',$roleId)->where('is_Active','1');
                if($permissions->count() > 0){
                    $permissions = $permissions->select('module_id as moduleId','read_access as readAccessPermission','write_access as writeAccessPermission','actions_id as actionsId')->get();
                    
                    foreach($permissions as $permission){

                        $actionsDetail = ActionMaster::where('actions_id',$permission->actionsId)
                        ->where('module_id',$permission->moduleId)->where('is_active','1')
                        ->select('actions_id as actionsId','action_name as actionName','controller_name as controllerName','method_name as methodName');

                        if($actionsDetail->count()){
                            $actionsDetail = $actionsDetail->first();
                            $action = [
                                'actionsId' => isset($actionsDetail->actionsId) ? $actionsDetail->actionsId : '',
                                'actionName' => isset($actionsDetail->actionName) ? $actionsDetail->actionName : '',
                                'actionControllerName' => isset($actionsDetail->controllerName) ? $actionsDetail->controllerName : '',
                                'actionMethodName' => isset($actionsDetail->methodName) ? $actionsDetail->methodName : '',
                                'readAccessPermission' => $permission->readAccessPermission,
                                'writeAccessPermission' => $permission->writeAccessPermission
                            ];
                            $actions[] = $action;
                        }
                    }

                    $moduleMaster = ModuleMaster::where('module_id',$modulePermission->moduleId)->where('is_active','1');
                    if($moduleMaster->count() > 0){
                        $moduleMaster = $moduleMaster->select('module_name','route_url','controller_name','method_name','parent_module_id')->first();

                        $data['moduleId'] = $modulePermission->moduleId;
                        $data['moduleName'] = $moduleMaster->module_name;
                        $data['moduleRouteUrl'] = $moduleMaster->route_url;
                        $data['moduleControllerName'] = $moduleMaster->controller_name;
                        $data['moduleMethodName'] = $moduleMaster->method_name;
                        $data['parentModuleId'] = $moduleMaster->parent_module_id;
                        $data['parentModule'] = $this->getActionsPermissionByModuleId($moduleMaster->parent_module_id,$organizationId,$roleId);
                        $data['actions'] = $actions;
                        $allData[] = $data;
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Permission is not found.'], 404);
        }

    }


    public function getActionsPermissionByModuleId($moduleId,$organizationId,$roleId){
        
        $data = $action = $actions = [];
        
        $permissions = Permission::where('module_id',$moduleId)->where('org_id',$organizationId)->where('role_id',$roleId)->where('is_Active','1');
        if($permissions->count() > 0){
            $permissions = $permissions->select('module_id as moduleId','read_access as readAccessPermission','write_access as writeAccessPermission','actions_id as actionsId')->get();
            foreach($permissions as $permission){

                $actionsDetail = ActionMaster::where('actions_id',$permission->actionsId)
                ->where('module_id',$permission->moduleId)->where('is_active','1')
                ->select('actions_id as actionsId','action_name as actionName','controller_name as controllerName','method_name as methodName');

                if($actionsDetail->count()){
                    $actionsDetail = $actionsDetail->first();
                    $action = [
                        'actionsId' => isset($actionsDetail->actionsId) ? $actionsDetail->actionsId : '',
                        'actionName' => isset($actionsDetail->actionName) ? $actionsDetail->actionName : '',
                        'actionControllerName' => isset($actionsDetail->controllerName) ? $actionsDetail->controllerName : '',
                        'actionMethodName' => isset($actionsDetail->methodName) ? $actionsDetail->methodName : '',
                        'readAccessPermission' => $permission->readAccessPermission,
                        'writeAccessPermission' => $permission->writeAccessPermission
                    ];
                    $actions[] = $action;
                }
                $moduleMaster = ModuleMaster::where('module_id',$permission->moduleId)->where('is_active','1')->select('module_name','route_url','controller_name','method_name','parent_module_id')->first();

                $data['moduleId'] = $permission->moduleId;
                $data['moduleName'] = $moduleMaster->module_name;
                $data['moduleRouteUrl'] = $moduleMaster->route_url;
                $data['moduleControllerName'] = $moduleMaster->controller_name;
                $data['moduleMethodName'] = $moduleMaster->method_name;
                $data['parentModuleId'] = $moduleMaster->parent_module_id;
                $data['parentModule'] = $this->getActionsPermissionByModuleId($moduleMaster->parent_module_id,$organizationId,$roleId);
                $data['actions'] = $actions;
                $allData[] = $data;
            }  
        }
        return $data;
    }


    public function getActionsPermissionListByModuleId($moduleId){
        //$organizationId = Auth::user()->org_id;
        //$roleId = Auth::user()->user->role_id;

        $actionsPermissions = Permission::with([
            'module' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('module_id','module_name');
            },
            'actions' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('actions_id','action_name');
            }
        ])
        ->where('is_active','1')
        ->select('permission_id','module_id','actions_id','read_access','write_access')
        ->where('module_id',$moduleId)
       // ->where('role_id',$roleId)
       // ->where('org_id',$organizationId)
        ->get();

       $data = $allData = [];
       if($actionsPermissions->count() > 0){
            foreach($actionsPermissions as $row){
                $data['actionsId'] = $row->actions_id;
                $data['actionName'] = $row->actions->action_name;
                $data['moduleId'] = $row->module_id;
                $data['moduleName'] = $row->module->module_name;
                $data['readAccessPermission'] = $row->read_access;
                $data['writeAccessPermission'] = $row->write_access;
                $allData[] = $data;
            }
       }
        return response()->json(['status'=>true,'code'=>200,'data'=>$allData],200);
    }
}
