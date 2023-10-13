<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationResource extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'resource_id'; 
    protected $table = 'lms_org_resources';

    const CREATED_AT = 'date_created';

}
