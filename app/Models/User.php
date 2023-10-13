<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_id'; 

    protected $table = 'lms_user_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    

    public function login_details(){
        return $this->hasOne(Login::class,'user_id','user_id');
    }

    public function role(){
        return $this->hasOne(RoleMaster::class,'role_id','role_id');
    }

    public function organization(){
        return $this->hasOne(Organization::class,'org_id','org_id');
    }
}
