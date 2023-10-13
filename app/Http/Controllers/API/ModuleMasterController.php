<?php

namespace App\Http\Controllers\API;

use App\Models\MenuMaster;
use App\Models\ModuleMaster;
use App\Models\MenuPermission;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class ModuleMasterController extends BaseController
{

    public function getModuleList(Request $request){
        $sort = $request->has('sort') ? $request->get('sort') : 'module.module_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $sortColumn = $sort;
        $moduleMasters = DB::table('lms_module_master as module')
        ->leftjoin('lms_module_master as parentModule','module.parent_module_id','=','parentModule.module_id')
        ->where('module.is_active','!=','0')
        ->orderBy($sortColumn,$order)
        ->select('module.module_id as moduleId','module.module_name as moduleName', 'module.method_name as methodName','module.route_url as routeUrl','module.controller_name as controllerName', 'module.parent_module_id as parentModuleId', 'module.description as description', 'parentModule.module_name as parentModuleName', 'module.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$moduleMasters],200);
    }

    public function addNewModule(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        
        $validator = Validator::make($request->all(), [
            'moduleName' => 'nullable|max:250',
            'routeUrl' => 'required|max:800',
            'controllerName' => 'required|max:250',
            'methodName' => 'required|max:250',
            'isParentModule' => 'required|integer|in:0,1',
            'parentModule' => 'required_if:isParentModule,==,1|nullable|integer',
            'description' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $parentMenu = Null;
        if($request->isParentModule == 1){
            $module = ModuleMaster::where('is_active','!=','0')->where('module_id',$request->parentModule);
            if($module->count() > 0){
                $parentMenu = $module->first()->menu_master_id;
            }
        }

        $menuMaster = new MenuMaster;
        $menuMaster->menu_name = $request->moduleName;
        $menuMaster->route_url = $request->routeUrl;
        $menuMaster->font_icon_name = '/media/icon/abs001.svg';
        $menuMaster->type = $request->isParentModule == 0 ? 1 : 2;
        $menuMaster->parent_menu_master_id = $parentMenu;
        $menuMaster->is_admin = NULL;
        $menuMaster->is_superadmin = NULL;
        $menuMaster->is_student = NULL;
        $menuMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $menuMaster->created_id = $authId;
        $menuMaster->modified_id = $authId;
        $menuMaster->save();
        $menuMasterId = $menuMaster->menu_master_id;

        $moduleMaster = new ModuleMaster;
        $moduleMaster->module_name = $request->moduleName;
        $moduleMaster->route_url = $request->routeUrl;
        $moduleMaster->controller_name = $request->controllerName;
        $moduleMaster->method_name = $request->methodName;
        $moduleMaster->description = $request->description;
        $moduleMaster->menu_master_id = $menuMasterId;
        $moduleMaster->parent_module_id = $request->isParentModule == 1 ? $request->parentModule : Null;
        $moduleMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $moduleMaster->created_id = $authId;
        $moduleMaster->modified_id = $authId;
        $moduleMaster->save();
        $moduleId = $moduleMaster->module_id;

        $menuPermission = new MenuPermission;
        $menuPermission->display_name = $request->moduleName;
        $menuPermission->module_id = $moduleId;
        $menuPermission->menu_master_id = $menuMasterId;
        $menuPermission->org_id = $organizationId;
        $menuPermission->role_id = 1;
        $menuPermission->is_active = 2;
        $menuPermission->created_id = $authId;
        $menuPermission->modified_id = $authId;
        $menuPermission->save();

        return response()->json(['status'=>true,'code'=>201,'data'=>['menuId'=>$menuMasterId],'message'=>'Module has been created successfully.'],201);
    }

    public function getModuleById($moduleId){
        $moduleMaster = DB::table('lms_module_master as module')
        ->leftjoin('lms_module_master as parentModule','module.parent_module_id','=','parentModule.module_id')
        ->where('module.is_active','!=','0')->where('module.module_id',$moduleId);
        if ($moduleMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Module is not found.'], 404);
        }
        $moduleMaster = $moduleMaster->select('module.module_id as moduleId','module.module_name as moduleName', 'module.route_url as routeUrl', 'module.controller_name as controllerName', 'module.method_name as methodName', 'module.parent_module_id as parentModuleId', 'parentModule.module_name as parentModuleName', 'module.description', 'module.is_active as isActive')->first();
        $moduleMaster->isParentModule = $moduleMaster->parentModuleId ==  '' ? 0 : 1;
        return response()->json(['status'=>true,'code'=>200,'data'=>$moduleMaster],200);
    }

    public function updateModule(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $validator = Validator::make($request->all(), [
            'moduleName' => 'nullable|max:250',
            'routeUrl' => 'required|max:800',
            'controllerName' => 'required|max:250',
            'methodName' => 'required|max:250',
            'isParentModule' => 'required|integer|in:0,1',
            'parentModule' => 'required_if:isParentModule,==,1|nullable|integer',
            'description' => 'nullable',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $moduleMaster = ModuleMaster::where('is_active','!=','0')->find($request->moduleId);
        if ($moduleMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Module is not found.'], 404);
        }

        $moduleMaster->module_name = $request->moduleName;
        $moduleMaster->route_url = $request->routeUrl;
        $moduleMaster->controller_name = $request->controllerName;
        $moduleMaster->method_name = $request->methodName;
        $moduleMaster->description = $request->description;
        $moduleMaster->parent_module_id = $request->isParentModule == 1 ? $request->parentModule : Null;
        $moduleMaster->is_active = $request->isActive == '' ? $moduleMaster->is_active ? $moduleMaster->is_active : '1' : $request->isActive;
        $moduleMaster->modified_id = $authId;
        $moduleMaster->save();

        $menuPermission = MenuPermission::where('is_active','!=','0')->where('role_id',$roleId)->where('org_id',$organizationId)->where('module_id',$request->moduleId);
        if($menuPermission->count() > 0){
            $menuPermission->update([
                'is_active' => 1,
                'modified_id' => $authId
            ]);
        }
        
        return response()->json(['status'=>true,'code'=>200,'message'=>'Module has been updated successfully.'],200);
    }

    public function deleteModule(Request $request){
        $validator = Validator::make($request->all(), [
            'moduleId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $moduleMaster = ModuleMaster::where('is_active','!=','0')->where('module_id',$request->moduleId);
        if($moduleMaster->count() > 0){
            $moduleMaster->update([
                'is_active' => '0',
            ]);
            return response()->json(['status'=>true,'code'=>200,'message'=>'Module has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Module is not found.'], 404);
        }
    }

    public function getModuleOptionList(){
        $moduleMasters = ModuleMaster::where('is_active','1')
        ->orderBy('module_name','ASC')
        ->select('module_id as moduleId','module_name as moduleName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$moduleMasters],200);
    }
    
}
