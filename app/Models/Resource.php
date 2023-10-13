<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resource extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'resource_id'; 
    protected $table = 'lms_resources';

    const CREATED_AT = 'date_created';

}
