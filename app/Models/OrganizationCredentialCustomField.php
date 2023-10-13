<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationCredentialCustomField extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'lms_org_credential_custom_field';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
