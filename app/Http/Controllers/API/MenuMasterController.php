<?php

namespace App\Http\Controllers\API;
use DB;
use App\Models\MenuMaster;
use App\Models\MenuPermission;
use App\Models\ModuleMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class MenuMasterController extends BaseController
{
    public function getMenuMasterList(Request $request){

        $sort = $request->has('sort') ? $request->get('sort') : 'menu_master.menu_master_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        $menus = DB::table('lms_menu_master as menu_master')
        ->leftJoin('lms_menu_master as parentMenu', 'menu_master.parent_menu_master_id', '=', 'parentMenu.menu_master_id')
        ->where('menu_master.is_active','!=','0')
        ->orderBy($sortColumn,$order)
        ->select('menu_master.menu_master_id as menuId', 'menu_master.menu_name as menuName', 'menu_master.type as type', 'menu_master.parent_menu_master_id as parentMenuId', 'parentMenu.menu_name as parentMenuName','menu_master.position as position', 'menu_master.order as order', 'menu_master.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$menus],200);
    }

    public function addNewMenuMaster(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $validator = Validator::make($request->all(), [
            'menuName' => 'required|max:64',
            'isMenuGroup' => 'required|integer|in:0,1',
            'routeUrl' => 'required_unless:isMenuGroup,==,1',
            'routeUrl' => 'required_if:isMenuGroup,==,0|nullable|regex:/^[a-zA-Z0-9]*$/',
            'isParentMenu' => 'required|integer|in:0,1',
            'parentMenu' => 'required_if:isParentMenu,==,1',
            'fontIconName' => 'required|max:64',
            'order' => 'required|integer',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $role = $request->roles;
        $isAdmin = NULL;
        $isSuperadmin = NULL;
        $isStudent = NULL;
        if(!empty($request->menuRole)){
            $menuRole = $request->menuRole;
            $isAdmin = $menuRole['admin'];
            $isSuperadmin = $menuRole['superAdmin'];
            $isStudent = $menuRole['student'];
            
            if($isAdmin == 1 || $isSuperadmin == 1 || $isStudent == 1){
                if($isAdmin == 1){
                    $role[] = '2';
                    $role[] = '3';
                    $role[] = '4';
                    $role[] = '5';
                    $role[] = '6';
                }
                if($isSuperadmin == 1){
                    $role[] = '1';
                }
                if($isStudent == 1){
                    $role[] = '7';
                }
            }else{
                $role = [];
            }
        }else{
            $role = [];
        }

        if(is_array($role)){
            $role = implode(',',$role);
        }

        $menuMaster = new MenuMaster;
        $menuMaster->menu_name = $request->menuName;
        $menuMaster->route_url = $request->isMenuGroup == 1 ? '' : $request->routeUrl;
        $menuMaster->font_icon_name = $request->fontIconName;
        $menuMaster->parent_menu_master_id = $request->isParentMenu == 1 ? $request->parentMenu : Null;
        $menuMaster->position = $request->position;
        $menuMaster->order = $request->order;
        $menuMaster->type = $request->type;

        $menuMaster->roles = $role;

        $menuMaster->is_admin = $isAdmin;
        $menuMaster->is_superadmin = $isSuperadmin;
        $menuMaster->is_student = $isStudent;

        $menuMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $menuMaster->created_id = $authId;
        $menuMaster->modified_id = $authId;
        $menuMaster->save();

        $moduleId = Null;
        if($request->isMenuGroup == 0){
            $parentModule = Null;
            if($menuMaster->menu_master_id != ''){
                if($request->parentMenu != ''){
                    $module = ModuleMaster::where('is_active','!=','0')->where('menu_master_id',$request->parentMenu);
                    if($module->count() > 0){
                        $parentModule = $module->first()->module_id;
                    }
                }

                $moduleMaster = new ModuleMaster;
                $moduleMaster->module_name = $request->menuName;
                $moduleMaster->route_url = $request->routeUrl;
                $moduleMaster->parent_module_id = $parentModule;
                $moduleMaster->menu_master_id = $menuMaster->menu_master_id;
                $moduleMaster->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $moduleMaster->created_id = $authId;
                $moduleMaster->modified_id = $authId;
                $moduleMaster->save();
                $moduleId = $moduleMaster->module_id;
            }
        } 


        if($roleId == 1){

            $menu = new MenuPermission;
            $menu->display_name = $request->menuName;
            $menu->module_id = $moduleId;
            $menu->menu_master_id = $menuMaster->menu_master_id;
            $menu->org_id = $organizationId;
            $menu->role_id = 1;
            $menu->is_active = 1;
            $menu->created_id = $authId;
            $menu->modified_id = $authId;
            $menu->save();

            if($isAdmin == 1){
                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 2;
                $menu->is_active = 2;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();

                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 3;
                $menu->is_active = 2;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();

                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 4;
                $menu->is_active = 2;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();

                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 5;
                $menu->is_active = 2;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();

                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 6;
                $menu->is_active = 2;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();
            }

            if($isStudent == 1){
                $menu = new MenuPermission;
                $menu->display_name = $request->menuName;
                $menu->module_id = $moduleId;
                $menu->menu_master_id = $menuMaster->menu_master_id;
                $menu->org_id = $organizationId;
                $menu->role_id = 7;
                $menu->is_active = 1;
                $menu->created_id = $authId;
                $menu->modified_id = $authId;
                $menu->save();
            }
        }

        
        
        
        // if($request->isMenuGroup == 0){
        //     if($isSuperadmin == 1){
        //         $menu = new MenuPermission;
        //         $menu->display_name = $request->menuName;
        //         $menu->module_id = $moduleId;
        //         $menu->menu_master_id = $menuMaster->menu_master_id;
        //         $menu->org_id = $organizationId;
        //         $menu->role_id = 1;
        //         $menu->is_active = 2;
        //         $menu->created_id = $authId;
        //         $menu->modified_id = $authId;
        //         $menu->save();
        //     }
        // }else{
        //     if($isSuperadmin == 1){
        //         $menu = new MenuPermission;
        //         $menu->display_name = $request->menuName;
        //         $menu->module_id = $moduleId;
        //         $menu->menu_master_id = $menuMaster->menu_master_id;
        //         $menu->org_id = $organizationId;
        //         $menu->role_id = 1;
        //         $menu->is_active = 1;
        //         $menu->created_id = $authId;
        //         $menu->modified_id = $authId;
        //         $menu->save();
        //     }  
        // }
        return response()->json(['status'=>true,'code'=>201,'data'=>['moduleId'=>$moduleId],'message'=>'Menu has been created successfully.'],201);
    }

    public function getMenuMasterById($menuId){

        $menuMaster = DB::table('lms_menu_master as menu_master')
        ->leftJoin('lms_menu_master as parentMenu', 'menu_master.parent_menu_master_id', '=', 'parentMenu.menu_master_id')
        ->where('menu_master.is_active','!=','0')
        ->where('menu_master.menu_master_id',$menuId);
        if ($menuMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
        }
        
        $menuMaster = $menuMaster->select('menu_master.menu_master_id as menuId', 'menu_master.menu_name as menuName', 'menu_master.route_url as routeUrl', 'menu_master.font_icon_name as fontIconName', 'menu_master.type as type', 'menu_master.parent_menu_master_id as parentMenuId', 'parentMenu.menu_name as parentMenuName','menu_master.position as position', 'menu_master.order as order',  'menu_master.is_admin as isAdmin', 'menu_master.is_superadmin as isSuperadmin', 'menu_master.is_student as isStudent', 'menu_master.is_active as isActive','menu_master.roles')->first();
        $menuArray['menuId'] = $menuMaster->menuId;
        $menuArray['menuName'] = $menuMaster->menuName;
        $menuArray['isMenuGroup'] = $menuMaster->routeUrl == '' ? 1 : 0;
        $menuArray['routeUrl'] = $menuMaster->routeUrl;
        $menuArray['fontIconName'] = $menuMaster->fontIconName;
        $menuArray['type'] = $menuMaster->type;
        $menuArray['isParentMenu'] = $menuMaster->parentMenuId == '' ? 0 : 1;
        $menuArray['parentMenuId'] = $menuMaster->parentMenuId;
        $menuArray['parentMenuName'] = $menuMaster->parentMenuName;
        $menuArray['position'] = $menuMaster->position;
        $menuArray['order'] = $menuMaster->order;
        $menuArray['isActive'] = $menuMaster->isActive;
        
        $menuRole=[];
        $menuRole['superAdmin'] = $menuMaster->isSuperadmin;
        $menuRole['admin'] = $menuMaster->isAdmin;
        $menuRole['student'] = $menuMaster->isStudent;
        $menuArray['menuRole'] = $menuRole;

        return response()->json(['status'=>true,'code'=>200,'data'=>$menuArray],200);
    }

    public function updateMenuMaster(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $validator = Validator::make($request->all(), [
            'menuName' => 'required|max:64',
            'isMenuGroup' => 'required|integer|in:0,1',
            'routeUrl' => 'required_unless:isMenuGroup,==,1',
            'routeUrl' => 'required_if:isMenuGroup,==,0|nullable|regex:/^[a-zA-Z0-9]*$/',
            'isParentMenu' => 'required|integer|in:0,1',
            'parentMenu' => 'required_if:isParentMenu,==,1',
            'fontIconName' => 'required|max:64',
            'order' => 'required|integer',
            'isActive' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $menuMaster = MenuMaster::where('is_active','!=','0')->find($request->menuId);
        if ($menuMaster->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
        }

        $role = $request->roles;
        $isAdmin = NULL;
        $isSuperadmin = NULL;
        $isStudent = NULL;
        if(!empty($request->menuRole)){
            $menuRole = $request->menuRole;
            $isAdmin = $menuRole['admin'];
            $isSuperadmin = $menuRole['superAdmin'];
            $isStudent = $menuRole['student'];
            
            if($isAdmin == 1 || $isSuperadmin == 1 || $isStudent == 1){
                if($isAdmin == 1){
                    $role[] = '2';
                    $role[] = '3';
                    $role[] = '4';
                    $role[] = '5';
                    $role[] = '6';
                }
                if($isSuperadmin == 1){
                    $role[] = '1';
                }
                if($isStudent == 1){
                    $role[] = '7';
                }
            }else{
                $role = [];
            }
        }else{
            $role = [];
        }

        if(is_array($role)){
            $role = implode(',',$role);
        }

        $menuMaster->menu_name = $request->menuName;
        $menuMaster->route_url = $request->isMenuGroup == 1 ? '' : $request->routeUrl;
        $menuMaster->font_icon_name = $request->fontIconName;
        $menuMaster->parent_menu_master_id = $request->isParentMenu == 1 ? $request->parentMenu : Null;
        $menuMaster->position = $request->position;
        $menuMaster->order = $request->order;
        $menuMaster->type = $request->type;

        $menuMaster->roles = $role;

        $menuMaster->is_admin = $isAdmin;
        $menuMaster->is_superadmin = $isSuperadmin;
        $menuMaster->is_student = $isStudent;

        $menuMaster->is_active = $request->isActive == '' ? $menuMaster->is_active ? $menuMaster->is_active : '1' : $request->isActive;
        $menuMaster->created_id = $authId;
        $menuMaster->modified_id = $authId;
        $menuMaster->save();

        $menuPermission = MenuPermission::where('is_active','!=','0')->where('role_id',$roleId)->where('org_id',$organizationId)->where('menu_master_id',$request->menuId);
        if($menuPermission->count() > 0){
            $menuPermission->update([
                'is_active' => 1,
                'modified_id' => $authId
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been updated successfully.'],200);
    }

    public function deleteMenuMaster(Request $request){
        $validator = Validator::make($request->all(), [
            'menuId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $menuMaster = MenuMaster::where('is_active','!=','0')->where('menu_master_id',$request->menuId);
        if($menuMaster->count() > 0){

            $menuMaster->update([
                'is_active' => '0',
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Menu has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Menu is not found.'], 404);
        }
    }

    public function getParentMenuMasterList()
    {
        $menuMaster = MenuMaster::where('is_active','1')->orderBy('menu_name','ASC')
        ->where('type','1-Menu')
        ->orWhere('type','2-SubMenu')
        ->select('menu_master_id as menuId', 'menu_name as menuName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$menuMaster],200);
    }


    public function getMenuMasterOptionList()
    {
        $menuMaster = MenuMaster::where('is_active','1')->orderBy('menu_name','ASC')
        ->select('menu_master_id as menuId', 'menu_name as menuName', 'route_url as routeUrl')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$menuMaster],200);
    }

    
}
