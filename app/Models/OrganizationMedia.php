<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationMedia extends Model
{
    use HasFactory;

    protected $primaryKey = 'media_id';

    protected $table = 'lms_org_media';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
