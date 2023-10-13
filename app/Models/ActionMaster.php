<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActionMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'actions_id';

    protected $table = 'lms_actions_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    public static function getActionsName($actionsId){
        $actionMaster = ActionMaster::where('actions_id',$actionsId)->where('is_active','1');
        if($actionMaster->count() > 0){
         return $actionMaster->select('action_name')->first()->action_name;
        }
        return '';
         
    }

    public function module(){
        return $this->hasOne(ModuleMaster::class,'module_id','module_id');
    }

}
