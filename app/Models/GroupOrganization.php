<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupOrganization extends Model
{
    use HasFactory;

    protected $primaryKey = 'group_id';

    protected $table = 'lms_group_org';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
