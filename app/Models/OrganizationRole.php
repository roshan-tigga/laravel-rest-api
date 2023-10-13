<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationRole extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'org_role_id'; 
    protected $table = 'lms_org_roles';

}
