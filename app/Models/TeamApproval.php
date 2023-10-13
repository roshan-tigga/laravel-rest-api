<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamApproval extends Model
{
    use HasFactory;

    protected $primaryKey = 'team_approval_id';

    protected $table = 'lms_org_team_approvals';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
