<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserRequirementCourse extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_requirement_course_id';

    protected $table = 'lms_user_requirement_courses';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
