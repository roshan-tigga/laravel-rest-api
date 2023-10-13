<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationTrainingHandout extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'training_handout_id'; 
    protected $table = 'lms_org_training_handouts';

}
