<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Division extends Model
{
    use HasFactory;

    protected $primaryKey = 'division_id';

    protected $table = 'lms_division';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
