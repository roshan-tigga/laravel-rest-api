<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $primaryKey = 'area_id';

    protected $table = 'lms_area';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
