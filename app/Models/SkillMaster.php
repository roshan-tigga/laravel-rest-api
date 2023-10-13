<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SkillMaster extends Model
{
    use HasFactory;

    //public $timestamps = false;

    protected $primaryKey = 'skill_id'; 

    protected $table = 'lms_skills_master';


    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
