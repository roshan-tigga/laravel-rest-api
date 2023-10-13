<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $primaryKey = 'location_id';

    protected $table = 'lms_location';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
