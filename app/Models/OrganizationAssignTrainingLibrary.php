<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationAssignTrainingLibrary extends Model
{
    use HasFactory;

    protected $primaryKey = 'org_training_id'; 
    protected $table = 'lms_org_assign_training_library';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
