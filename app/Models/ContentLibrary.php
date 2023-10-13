<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentLibrary extends Model
{
    use HasFactory;

    protected $primaryKey = 'content_id';

    protected $table = 'lms_content_library';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified'; 
}
