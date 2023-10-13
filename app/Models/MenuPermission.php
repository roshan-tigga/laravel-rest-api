<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuPermission extends Model
{
    use HasFactory;

    protected $primaryKey = 'menu_id'; 

    protected $table = 'lms_menu';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';



    public function subMenu(){
        return $this->hasMany(MenuMaster::class,'parent_menu_master_id','menu_master_id');
    }

    public function menu(){
        return $this->hasOne(MenuMaster::class,'menu_master_id','menu_master_id');
    }

    public function role(){
        return $this->hasOne(RoleMaster::class,'role_id','role_id');
    }
 
}
