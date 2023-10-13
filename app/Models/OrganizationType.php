<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationType extends Model
{
    use HasFactory;

    protected $primaryKey = 'organization_type_id'; 

    protected $table = 'lms_organization_type';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
