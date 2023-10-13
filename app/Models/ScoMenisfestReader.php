<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoMenisfestReader extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'lms_sco_menisfest_reader';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
