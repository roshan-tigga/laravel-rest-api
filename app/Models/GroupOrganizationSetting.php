<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupOrganizationSetting extends Model
{
    use HasFactory;

    protected $primaryKey = 'group_org_setting_id';

    protected $table = 'lms_group_org_settings';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
