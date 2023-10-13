<?php

namespace App\Http\Controllers\API;

use App\Models\MenuPermission;
use App\Models\MenuMaster;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use DB;

class MenuController extends BaseController
{
    public function getMenuSubmenuList(){
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $menuMasters = DB::table('lms_menu_master as menuMaster')
        ->join('lms_menu as menu','menu.menu_master_id','=','menuMaster.menu_master_id')
        ->where('menuMaster.is_active','1')
        ->where('menuMaster.type','1-Menu')
        ->where('menu.is_active','1')
        ->where('menu.org_id',$organizationId)
        ->where('menu.role_id',$roleId)
        ->where(function($query) use ($roleId){
            if($roleId != 1){
                $query->whereRaw('FIND_IN_SET(?, menuMaster.roles)', [$roleId]);
            }
        })
        ->orderBy('menuMaster.order','ASC');
       
        $dataMenuMaster = [];
        if($menuMasters->count() > 0){

            $menuRole = [];
            $menuMasters = $menuMasters->select('menuMaster.menu_master_id','menuMaster.menu_name','menuMaster.route_url','menuMaster.font_icon_name','menuMaster.position','menuMaster.order','menuMaster.type','menuMaster.is_superadmin','menuMaster.is_admin','menuMaster.is_student','menu.menu_id','menu.display_name')->get();
            foreach($menuMasters as $menuMaster){

                $subMenus = DB::table('lms_menu_master as menuMaster')
                ->join('lms_menu as menu','menu.menu_master_id','=','menuMaster.menu_master_id')
                ->where('menuMaster.is_active','1')
                ->where('menuMaster.type','2-SubMenu')
                ->where('menu.is_active','1')
                ->where('menu.org_id',$organizationId)
                ->where('menu.role_id',$roleId)
                //->whereRaw('FIND_IN_SET(?, menuMaster.roles)', [$roleId])
                ->where(function($query) use ($roleId){
                    if($roleId != 1){
                        $query->whereRaw('FIND_IN_SET(?, menuMaster.roles)', [$roleId]);
                    }
                })
                ->where('menuMaster.parent_menu_master_id',$menuMaster->menu_master_id)
                ->orderBy('menuMaster.order','ASC');

                $dataSubMenu = [];
                if($subMenus->count() > 0){

                    $subMenuRole = [];
                    $subMenus = $subMenus->select('menuMaster.menu_master_id','menuMaster.menu_name','menuMaster.route_url','menuMaster.font_icon_name','menuMaster.position','menuMaster.order','menuMaster.type','menuMaster.parent_menu_master_id','menuMaster.is_superadmin','menuMaster.is_admin','menuMaster.is_student','menu.display_name')->get();
                    foreach($subMenus as $subMenu){

                        $tabMenus = DB::table('lms_menu_master as menuMaster')
                        ->join('lms_menu as menu','menu.menu_master_id','=','menuMaster.menu_master_id')
                        ->where('menuMaster.is_active','1')
                        ->where('menuMaster.type','3-Tab')
                        ->where('menu.is_active','1')
                        ->where('menu.org_id',$organizationId)
                        ->where('menu.role_id',$roleId)
                        ->whereRaw('FIND_IN_SET(?, menuMaster.roles)', [$roleId])
                        ->where('menuMaster.parent_menu_master_id',$subMenu->menu_master_id)
                        ->orderBy('menuMaster.order','ASC');

                        $dataTabMenu = [];
                        if($tabMenus->count() > 0){

                            $tabMenuRole = [];
                            $tabMenus = $tabMenus->select('menuMaster.menu_master_id','menuMaster.menu_name','menuMaster.route_url','menuMaster.font_icon_name','menuMaster.position','menuMaster.order','menuMaster.type','menuMaster.parent_menu_master_id','menuMaster.is_superadmin','menuMaster.is_admin','menuMaster.is_student','menu.display_name')->get();
                            foreach($tabMenus as $tabMenu){

                                $tabMenuRole['superAdmin'] = $tabMenu->is_superadmin;
                                $tabMenuRole['admin'] = $tabMenu->is_admin;
                                $tabMenuRole['student'] = $tabMenu->is_student;

                                $dataTabMenu[] = [
                                    'tabId' => $tabMenu->menu_master_id,
                                    'tabName' => $tabMenu->menu_name,
                                    'displayName' => $tabMenu->display_name,
                                    'routeUrl' => $tabMenu->route_url,
                                    'fontIconName' => $tabMenu->font_icon_name,
                                    'parentMenuMasterId' => $tabMenu->parent_menu_master_id,
                                    //'parentMenuMasterName' => MenuMaster::getMenuMasterName($subMenu->parent_menu_master_id),
                                    'position' => $tabMenu->position,
                                    'order' => $tabMenu->order,
                                    //'type' => $tabMenu->type,
                                    'menuRole' => $tabMenuRole
                                ];
                            }

                        }

                        $subMenuRole['superAdmin'] = $subMenu->is_superadmin;
                        $subMenuRole['admin'] = $subMenu->is_admin;
                        $subMenuRole['student'] = $subMenu->is_student;

                        $dataSubMenu[] = [
                            'subMenuId' => $subMenu->menu_master_id,
                            'subMenuName' => $subMenu->menu_name,
                            'displayName' => $subMenu->display_name,
                            'routeUrl' => $subMenu->route_url,
                            'fontIconName' => $subMenu->font_icon_name,
                            'parentMenuMasterId' => $subMenu->parent_menu_master_id,
                            //'parentMenuMasterName' => MenuMaster::getMenuMasterName($subMenu->parent_menu_master_id),
                            'position' => $subMenu->position,
                            'order' => $subMenu->order,
                            //'type' => $subMenu->type,
                            'menuRole' => $subMenuRole,
                            'tabs' => $dataTabMenu
                        ]; 

                    }

                }

                $menuRole['superAdmin'] = $menuMaster->is_superadmin;
                $menuRole['admin'] = $menuMaster->is_admin;
                $menuRole['student'] = $menuMaster->is_student;

                $dataMenuMaster[] = [
                    'menuId' => $menuMaster->menu_id,
                    'menuName' => $menuMaster->menu_name,
                    'displayName' => $menuMaster->display_name,
                    'menuMasterId' => $menuMaster->menu_master_id,
                    'menuMasterName' => $menuMaster->menu_name,
                    'menuMasterRouteUrl' => $menuMaster->route_url,
                    'fontIconName' => $menuMaster->font_icon_name,
                    'position' => $menuMaster->position,
                    'order' => $menuMaster->order,
                    //'type' => $menuMaster->type,
                    //'roleName' =>$menuMaster->role->role_name,
                    'menuRole' => $menuRole,
                    'subMenus' => $dataSubMenu
                ];  

            }

        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$dataMenuMaster], 200);




        exit;

        $menus = MenuPermission::with([
            'menu' => function($query){
                $query->where(['type'=>'1-Menu','is_active'=>'1'])->orderBy('order','ASC')
                ->select('menu_master_id','menu_name','route_url','font_icon_name','parent_menu_master_id','position','order','type','menu_role','is_superadmin','is_admin','is_student');
            },
            'subMenu' => function($query){
                $query->where(['type'=>'2-SubMenu','is_active'=>'1'])->orderBy('order','ASC')
                ->select('menu_master_id','menu_name','route_url','font_icon_name','parent_menu_master_id','position','order','type','menu_role','is_superadmin','is_admin','is_student');
            },
            'role' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('role_id','role_name');
            }
        ])
        ->where('is_active','1')
        ->where(['org_id'=>$organizationId, 'role_id'=>$roleId]) 
        ->select('menu_id','display_name','menu_master_id','org_id','module_id','role_id')
        ->get();
        $data= [];
        $allData= [];

        if($menus->count() > 0){
            $menuRole = [];
            foreach($menus as $a => $menu){
                $dataSubMenu = []; 
                if(isset($menu->menu)){ 
                    if(isset($menu->subMenu)){

                        $subMenuRole = [];
                        foreach($menu->subMenu as $a => $subMenu){

                            $subMenuRole['superAdmin'] = $subMenu->is_superadmin;
                            $subMenuRole['admin'] = $subMenu->is_admin;
                            $subMenuRole['student'] = $subMenu->is_student;
  
                            $dataSubMenu[] = [
                                'subMenuId' => $subMenu->menu_master_id,
                                'subMenuName' => $subMenu->menu_name,
                                'routeUrl' => $subMenu->route_url,
                                'fontIconName' => $subMenu->font_icon_name,
                                'parentMenuMasterId' => $subMenu->parent_menu_master_id,
                                'parentMenuMasterName' => MenuMaster::getMenuMasterName($subMenu->parent_menu_master_id),
                                'position' => $subMenu->position,
                                'order' => $subMenu->order,
                                //'type' => $subMenu->type,
                                'menuRole' => $subMenuRole,
                                'tabs' => MenuMaster::getTabMenu($subMenu->menu_master_id)
                            ]; 
                            
                        }  
                    }


                    $menuRole['superAdmin'] = $menu->menu->is_superadmin;
                    $menuRole['admin'] = $menu->menu->is_admin;
                    $menuRole['student'] = $menu->menu->is_student;

                    $dataMenu = [
                        'menuId' => $menu->menu_id,
                        'menuName' => $menu->display_name,
                        'menuMasterId' => $menu->menu_master_id,
                        'menuMasterName' => $menu->menu->menu_name ? $menu->menu->menu_name : '',
                        'menuMasterRouteUrl' => $menu->menu->route_url ? $menu->menu->route_url : '',
                        'fontIconName' => $menu->menu->font_icon_name ? $menu->menu->font_icon_name : '',
                        'position' => $menu->menu->position ? $menu->menu->position : '',
                        'order' => $menu->menu->order ? $menu->menu->order : '',
                        //'type' => $menu->menu->type ? $menu->menu->type : '',
                        'roleName' =>$menu->role->role_name,
                        'menuRole' => $menuRole,
                        'subMenus' => $dataSubMenu
                    ];   
                    
                    
                    $allData[] = $dataMenu;
                }

                $sort = array_column($allData, 'order');
                array_multisort($sort, SORT_ASC, $allData);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$allData], 200);
    }

    public function getStudentMenuList(){
        $organizationId = Auth::user()->org_id;
        $roleId = Auth::user()->user->role_id;

        $menus = MenuPermission::with([
            'menu' => function($query){
                $query->where(['type'=>'1-Menu','is_active'=>'1'])->orderBy('order','ASC')
                ->select('menu_master_id','menu_name','route_url','font_icon_name','parent_menu_master_id','position','order','type');
            },
            'subMenu' => function($query){
                $query->where(['type'=>'2-SubMenu','is_active'=>'1'])->orderBy('order','ASC')
                ->select('menu_master_id','menu_name','route_url','font_icon_name','parent_menu_master_id','position','order','type');
            },
            'role' => function($query){
                $query->where(['is_active'=>'1'])
                ->select('role_id','role_name');
            },
        ])
        ->where(['org_id'=>$organizationId, 'role_id'=>$roleId,'is_active'=>'1']) 
        ->select('menu_id','display_name','menu_master_id','org_id','module_id','role_id')
        ->get();
        $data= [];
        $allData= [];

        if($menus->count() > 0){
            foreach($menus as $a => $menu){
                $dataSubMenu = []; 
                if(isset($menu->menu)){ 
                    if(isset($menu->subMenu)){
                        foreach($menu->subMenu as $a => $subMenu){
                            
                            $dataSubMenu[] = [
                                'subMenuId' => $subMenu->menu_master_id,
                                'subMenuName' => $subMenu->menu_name,
                                'routeUrl' => $subMenu->route_url,
                                'fontIconName' => $subMenu->font_icon_name,
                                'parentMenuMasterId' => $subMenu->parent_menu_master_id,
                                'parentMenuMasterName' => MenuMaster::getMenuMasterName($subMenu->parent_menu_master_id),
                                'position' => $subMenu->position,
                                'order' => $subMenu->order,
                                //'type' => $subMenu->type,
                                'tabs' => MenuMaster::getTabMenu($subMenu->menu_master_id)
                            ]; 
                            
                        }  
                    }

                    $dataMenu = [
                        'menuId' => $menu->menu_id,
                        'menuName' => $menu->display_name,
                        'menuMasterId' => $menu->menu_master_id,
                        'menuMasterName' => $menu->menu->menu_name ? $menu->menu->menu_name : '',
                        'menuMasterRouteUrl' => $menu->menu->route_url ? $menu->menu->route_url : '',
                        'fontIconName' => $menu->menu->font_icon_name ? $menu->menu->font_icon_name : '',
                        'position' => $menu->menu->position ? $menu->menu->position : '',
                        'order' => $menu->menu->order ? $menu->menu->order : '',
                        //'type' => $menu->menu->type ? $menu->menu->type : '',
                        'roleName' =>$menu->role->role_name,
                        'subMenus' => $dataSubMenu
                    ];   
                    
                    
                    $allData[] = $dataMenu;
                }

                $sort = array_column($allData, 'order');
                array_multisort($sort, SORT_ASC, $allData);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$allData], 200);
    } 
}
