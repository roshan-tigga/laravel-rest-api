<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationCustomField extends Model
{
    use HasFactory;

    protected $primaryKey = 'custom_field_id';

    protected $table = 'lms_org_custom_fields';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
