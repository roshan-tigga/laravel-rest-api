<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobTitle extends Model
{
    use HasFactory;

    protected $primaryKey = 'job_title_id';

    protected $table = 'lms_job_title';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
