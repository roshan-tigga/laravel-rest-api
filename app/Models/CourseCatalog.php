<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseCatalog extends Model
{
    use HasFactory;

    protected $primaryKey = 'course_catalog_id';

    protected $table = 'lms_course_catalog';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
