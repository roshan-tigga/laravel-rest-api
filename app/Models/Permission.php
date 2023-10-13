<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $primaryKey = 'permission_id'; 

    protected $table = 'lms_permission';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';


    public function module(){
        return $this->hasOne(ModuleMaster::class,'module_id','module_id');
    }

    public function actions(){
        return $this->hasOne(ActionMaster::class,'actions_id','actions_id');
    }
}
