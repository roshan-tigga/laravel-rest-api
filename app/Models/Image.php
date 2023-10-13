<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'image_id'; 
    protected $table = 'lms_image';

    const CREATED_AT = 'date_created';

}
