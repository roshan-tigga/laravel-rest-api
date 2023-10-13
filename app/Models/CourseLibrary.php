<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseLibrary extends Model
{
    use HasFactory;

    protected $primaryKey = 'org_course_catalog_id';

    protected $table = 'lms_org_course_catalog';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
