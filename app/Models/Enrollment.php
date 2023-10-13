<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Enrollment extends Model
{
    use HasFactory;

    protected $primaryKey = 'enrollment_id';

    protected $table = 'lms_enrollment';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
