<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCatalogInprogress extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'lms_user_catalog_inprogress';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
