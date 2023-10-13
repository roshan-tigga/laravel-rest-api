<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Organization extends Model
{
    use HasFactory;

    protected $primaryKey = 'org_id'; 

    protected $table = 'lms_org_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

    public function domain(){
        return $this->hasOne(Domain::class,'domain_id','domain_id');
    }

    public function parentOrganization(){
        return $this->hasOne(Organization::class,'org_id','org_id');
    }

    public function organizationType(){
        return $this->hasOne(OrganizationType::class,'organization_type_id','organization_type_id');
    }

    public function tags(){
        return $this->hasMany(Tag::class,'org_id','org_id');
    }

    public function user(){
        return $this->hasOne(User::class,'user_id','created_id');
    }

}
