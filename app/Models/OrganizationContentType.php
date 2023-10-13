<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationContentType extends Model
{
    use HasFactory;

    protected $primaryKey = 'content_types_id';

    protected $table = 'lms_org_content_types';

}
