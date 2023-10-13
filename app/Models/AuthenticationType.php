<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthenticationType extends Model
{
    use HasFactory;

    protected $primaryKey = 'authentication_type_id';

    protected $table = 'lms_authentication_type';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
