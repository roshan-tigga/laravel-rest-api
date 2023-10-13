<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'group_setting_id';

    protected $table = 'lms_group_settings';

}
