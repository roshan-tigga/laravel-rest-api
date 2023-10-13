<?php

namespace App\Http\Controllers\API;

use App\Models\MenuPermission;
use App\Models\MenuMaster;
use App\Models\ModuleMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Carbon\Carbon;

class MenuPermissionController extends BaseController
{
    public function getMenuList(Request $request){

        $organizationId = $request->organization;
        $roleId = $request->role;

        $sort = $request->has('sort') ? $request->get('sort') : 'menuId';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'displayName'){
            $sortColumn = 'displayName';
        }elseif($sort == 'moduleName'){
            $sortColumn = 'moduleName';
        }elseif($sort == 'isActive'){
            $sortColumn = 'isActive';
        }else{
            $sortColumn = 'menuId';
        }

        $menu = [];
        $menuMasters = MenuMaster::where('is_active','1')->where('type','!=','3-Tab') //->whereNull('parent_menu_master_id')
         ->where(function($query) use ($roleId){
            if($roleId != ''){
                $query->whereRaw('FIND_IN_SET(?, roles)', [$roleId]);
            }
        })
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('menu_name', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        });
        if($menuMasters->count() > 0){

            $displayName = '';
            foreach($menuMasters->get() as $menuMaster){

                $moduleName = '';
                $isActive = 2;
                $displayName = $menuMaster->menu_name;

                $menuPermission = MenuPermission::where('is_active','!=','0')
                ->where('role_id',$roleId)
                ->where('org_id',$organizationId)
                ->where('menu_master_id',$menuMaster->menu_master_id)
                ->where(function($query) use ($search){
                    if($search != ''){
                        $query->where('display_name', 'LIKE', '%'.$search.'%');
                        if(in_array($search,['active','act','acti','activ'])){
                            $query->orWhere('is_active','1');
                        }
                        if(in_array($search,['inactive','inact','inacti','inactiv'])){
                            $query->orWhere('is_active','2');
                        }
                    }
                });
                if($menuPermission->count() > 0){
                    $menuPermission = $menuPermission->first();
                    $displayName = $menuPermission->display_name;
                    $isActive = $menuPermission->is_active;

                    $moduleMaster = ModuleMaster::where('is_active','1')->where('module_id',$menuPermission->module_id)
                    ->where(function($query) use ($search){
                        if($search != ''){
                            $query->where('module_name', 'LIKE', '%'.$search.'%');
                        }
                    });
                    if($moduleMaster->count() > 0){
                        $moduleName = $moduleMaster->first()->module_name;
                    }
                    
                }else{
                    $moduleMaster = ModuleMaster::where('is_active','1')->where('menu_master_id',$menuMaster->menu_master_id)
                    ->where(function($query) use ($search){
                        if($search != ''){
                            $query->where('module_name', 'LIKE', '%'.$search.'%');
                        }
                    });
                    if($moduleMaster->count() > 0){
                        $moduleName = $moduleMaster->first()->module_name;
                    }
                }

                $menu[] = [
                    'menuId' => $menuMaster->menu_master_id,
                    'displayName' => $displayName,
                    'moduleName' => $moduleName,
                    'isActive' => $isActive
                ];

            }

            if(strtoupper($order) == 'DESC'){
                $order = SORT_DESC;
            }else{
                $order = SORT_ASC;
            }

            $keys = array_column($menu, $sortColumn);
            array_multisort($keys, $order, $menu);            

        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$menu],200);

        exit;

        $menu = DB::table('lms_menu as menu')
        ->leftJoin('lms_module_master as module','menu.module_id','=','module.module_id')
        ->leftJoin('lms_menu_master as menu_master','menu.menu_master_id','=','menu_master.menu_master_id')
        ->leftJoin('lms_org_master as org','menu.org_id','=','org.org_id')
        ->leftJoin('lms_roles as role','menu.role_id','=','role.role_id')
        ->where('menu.is_active','!=','0')
        ->where('module.is_active','1')
        ->where('menu_master.is_active','1')
        ->where('org.is_active','1')
        ->where('role.is_active','1')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('menu.display_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('module.module_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('menu_master.menu_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('org.organization_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('role.role_name', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('menu.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('menu.is_active','2');
                }
            }
        })
        ->where(function($query) use ($organizationId,$roleId){
            if($organizationId != ''){
                $query->where('menu.org_id',$organizationId);
            }
            if($roleId != ''){
                $query->where('menu.role_id',$roleId);
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('menu.menu_id as menuId', 'menu.display_name as displayName', 'module.module_name as moduleName', 'menu_master.menu_name as menuMasterName', 'org.organization_name as organizationName', 'role.role_name as roleName', 'menu.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$menu],200);
    }


    public function bulkUpdateMenuPermission(Request $request){
        $authId = Auth::id();
        $validator = Validator::make($request->all(), [
            'menus'=>'required|array',
            'menus.*.menuId' => 'required|integer',
            'menus.*.roleId' => 'required|integer',
            'menus.*.organizationId' => 'required|integer',
            'menus.*.displayName' => 'required',
            'menus.*.isActive' => 'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $i=0;
        if(!empty($request->menus)){
            if(is_array($request->menus)){
                if(count($request->menus) > 0){
                    foreach($request->menus as $menu){

                        $menuPermission = MenuPermission::where('menu_master_id',$menu['menuId'])->where('org_id',$menu['organizationId'])->where('role_id',$menu['roleId']);
                        if($menuPermission->count() > 0){
                            $menuPermission->update([
                                'display_name' => $menu['displayName'],
                                'is_active' => $menu['isActive'],
                                'modified_id' => $authId,
                                'date_modified' => Carbon::now()
                            ]);
                        }else{
                            $moduleMaster = ModuleMaster::where('is_active','1')->where('menu_master_id',$menu['menuId']);
                            if($moduleMaster->count() > 0){
                                $moduleMaster = $moduleMaster->first();
                                $menuMasterId = $moduleMaster->menu_master_id;
                                $moduleId = $moduleMaster->module_id;

                                $menuPermission = new MenuPermission;
                                $menuPermission->display_name = $menu['displayName'];
                                $menuPermission->is_active = $menu['isActive'];
                                $menuPermission->menu_master_id = $menuMasterId;
                                $menuPermission->module_id = $moduleId;
                                $menuPermission->org_id = $menu['organizationId'];
                                $menuPermission->role_id = $menu['roleId'];
                                $menuPermission->created_id = $authId;
                                $menuPermission->date_created = Carbon::now();
                                $menuPermission->modified_id = $authId;
                                $menuPermission->date_modified = Carbon::now();
                                $menuPermission->save();
                            }else{
                                $menuPermission = new MenuPermission;
                                $menuPermission->display_name = $menu['displayName'];
                                $menuPermission->is_active = $menu['isActive'];
                                $menuPermission->menu_master_id = $menu['menuId'];
                                $menuPermission->module_id = Null;
                                $menuPermission->org_id = $menu['organizationId'];
                                $menuPermission->role_id = $menu['roleId'];
                                $menuPermission->created_id = $authId;
                                $menuPermission->date_created = Carbon::now();
                                $menuPermission->modified_id = $authId;
                                $menuPermission->date_modified = Carbon::now();
                                $menuPermission->save();
                            }
                        }
                    
                        $i++;

                    }
                }
            }
        }

        if($i != 0){
            return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been updated successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>400,'error'=>'Menu is not update.'], 400);
        }
    }

    public function addNewMenu(Request $request){
        $authId = Auth::id();
        $validator = Validator::make($request->all(), [
            'displayName' => 'required|max:255',
            'menuMasterId' => 'nullable|integer',
            'moduleId' => 'nullable|integer',
            'organizationId' => 'nullable|integer',
            'roleId' => 'nullable|integer',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $menu = new MenuPermission;
        $menu->display_name = $request->displayName;
        $menu->module_id = $request->moduleId;
        $menu->menu_master_id = $request->menuMasterId;
        $menu->org_id = $request->organizationId;
        $menu->role_id = $request->roleId;
        $menu->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $menu->created_id = $authId;
        $menu->modified_id = $authId;
        $menu->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been created successfully.'],200);
    }

    public function getMenuById($menuId){
        $menuRedis = Redis::get('menuRedis' . $menuId);
        if(isset($menuRedis)){
            return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($menuRedis,false)],200);
        }else{
            $menu = DB::table('lms_menu as menu')->where('menu.is_active','!=','0')->where('menu.menu_id',$menuId);
            if ($menu->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
            }
            $menu = $menu->leftJoin('lms_module_master as module','menu.module_id','=','module.module_id')
            ->leftJoin('lms_menu_master as menu_master','menu.menu_master_id','=','menu_master.menu_master_id')
            ->leftJoin('lms_org_master as org','menu.org_id','=','org.org_id')
            ->leftJoin('lms_roles as role','menu.role_id','=','role.role_id')
            ->where('menu.is_active','!=','0')
            ->where('module.is_active','1')
            ->where('menu_master.is_active','1')
            ->where('org.is_active','1')
            ->where('role.is_active','1')
            ->select('menu.menu_id as menuId','menu.display_name as displayName', 'menu.module_id as moduleId', 'module.module_name as moduleName', 'menu.menu_master_id as menuMasterId', 'menu_master.menu_name as menuMasterName', 'menu.org_id as organizationId', 'org.organization_name as organizationName', 'menu.role_id as roleId', 'role.role_name as roleName', 'menu.is_active as isActive')->first();
            Redis::set('menuRedis' . $menuId, json_encode($menu,false));
            return response()->json(['status'=>true,'code'=>200,'data'=>$menu],200);
        }
    }

    public function updateMenu(Request $request){
        $authId = Auth::id();
        $validator = Validator::make($request->all(), [
            'menuId'=>'required|integer',
            'displayName' => 'required|max:255',
            'menuMasterId' => 'nullable|integer',
            'moduleId' => 'nullable|integer',
            'organizationId' => 'nullable|integer',
            'roleId' => 'nullable|integer',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $menu = MenuPermission::where('is_active','!=','0')->where('menu_id',$request->menuId);
        if ($menu->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
        }
        
        $menu->update([
            'display_name' => $request->displayName,
            'module_id' => $request->moduleId,
            'menu_master_id' => $request->menuMasterId,
            'org_id' => $request->organizationId,
            'role_id' => $request->roleId,
            'is_active' => $request->isActive == '' ? $menu->first()->is_active ? $menu->first()->is_active : '1' : $request->isActive,
            'modified_id' => $authId
        ]);

        $menu = DB::table('lms_menu as menu')
        ->leftJoin('lms_module_master as module','menu.module_id','=','module.module_id')
        ->leftJoin('lms_menu_master as menu_master','menu.menu_master_id','=','menu_master.menu_master_id')
        ->leftJoin('lms_org_master as org','menu.org_id','=','org.org_id')
        ->leftJoin('lms_roles as role','menu.role_id','=','role.role_id')
        ->where('menu.is_active','!=','0')
        ->where('module.is_active','1')
        ->where('menu_master.is_active','1')
        ->where('org.is_active','1')
        ->where('role.is_active','1')
        ->where('menu.menu_id',$request->menuId)
        ->select('menu.menu_id as menuId','menu.display_name as displayName', 'menu.module_id as moduleId', 'module.module_name as moduleName', 'menu.menu_master_id as menuMasterId', 'menu_master.menu_name as menuMasterName', 'menu.org_id as organizationId', 'org.organization_name as organizationName', 'menu.role_id as roleId', 'role.role_name as roleName', 'menu.is_active as isActive')
        ->first();
        
        Redis::del('menuRedis' . $request->menuId);
        Redis::set('menuRedis' . $request->menuId, json_encode($menu,false));

        return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been updated successfully.'],200);
    }

    public function deleteMenu(Request $request){
        $validator = Validator::make($request->all(), [
            'menuId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $menu = MenuPermission::where('is_active','!=','0')->where('menu_id',$request->menuId);
        if($menu->count() > 0){

            $menu->update([
                'is_active' => '0',
            ]);

            Redis::del('menuRedis' . $request->menuId);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
        }
    }
}
