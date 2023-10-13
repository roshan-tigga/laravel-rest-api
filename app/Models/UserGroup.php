<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserGroup extends Model
{
    use HasFactory;

    protected $primaryKey = 'user_group_id';

    protected $table = 'lms_user_group';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
