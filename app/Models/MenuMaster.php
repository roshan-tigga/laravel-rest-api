<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'menu_master_id';

    protected $table = 'lms_menu_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    public static function getMenuMasterName($menuMasterId){
       $menuMaster = MenuMaster::where('menu_master_id',$menuMasterId)->where('is_active','1');
       if($menuMaster->count() > 0){
        return $menuMaster->select('menu_name')->first()->menu_name;
       }
       return '';  
    }

    public static function getTabMenu($menuMasterId){
        $menuMaster = MenuMaster::where('parent_menu_master_id',$menuMasterId)->where('type','3-Tab')->where('is_active','1')->orderBy('order','ASC');
        $dataTab = [];
        if($menuMaster->count() > 0){
            $tabMenuRole = [];
            foreach($menuMaster->get() as $tab){

                $tabMenuRole['superAdmin'] = $tab->is_superadmin;
                $tabMenuRole['admin'] = $tab->is_admin;
                $tabMenuRole['student'] = $tab->is_student;

                $dataTab[] = [
                    'tabId' => $tab->menu_master_id,
                    'tabName' => $tab->menu_name,
                    'routeUrl' => $tab->route_url,
                    'fontIconName' => $tab->font_icon_name,
                    'parentMenuMasterId' => $tab->parent_menu_master_id,
                    'parentMenuMasterName' => MenuMaster::getMenuMasterName($tab->parent_menu_master_id),
                    'position' => $tab->position,
                    'order' => $tab->order,
                    'menuRole' => $tabMenuRole,
                    //'type' => $tab->type
                ]; 
            }
        }
        return $dataTab;
        
     }

    
}
