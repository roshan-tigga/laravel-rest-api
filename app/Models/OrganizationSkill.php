<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationSkill extends Model
{
    use HasFactory;

    //public $timestamps = false;

    protected $primaryKey = 'org_skill_id'; 

    protected $table = 'lms_org_skills';


    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
