<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IltEnrollment extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'ilt_enrollment_id'; 
    protected $table = 'lms_ilt_enrollment';
}
