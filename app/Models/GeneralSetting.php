<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'general_setting_id';

    protected $table = 'lms_general_settings_master';

    // const CREATED_AT = 'date_created';
    // const UPDATED_AT = 'date_modified';
}
