<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ThemeMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'theme_id';

    protected $table = 'lms_theme_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
