<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationCustomNumberOfField extends Model
{
    use HasFactory;

    protected $primaryKey = 'custom_number_of_field_id';

    protected $table = 'lms_org_custom_number_of_fields';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
