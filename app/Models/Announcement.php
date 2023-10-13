<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $primaryKey = 'announcement_id';

    protected $table = 'lms_company_announcement';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
