<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamCredit extends Model
{
    use HasFactory;

    protected $primaryKey = 'team_credit_id';

    protected $table = 'lms_team_credit';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
