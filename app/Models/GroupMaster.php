<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'group_id';

    protected $table = 'lms_group_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
