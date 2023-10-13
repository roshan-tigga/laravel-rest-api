<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingStatus extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'training_status_id'; 
    protected $table = 'lms_training_status';

}
