<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    use HasFactory;

    protected $primaryKey = 'domain_id';

    protected $table = 'lms_domain';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
