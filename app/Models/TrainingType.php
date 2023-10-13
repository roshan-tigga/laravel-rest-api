<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingType extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'training_type_id'; 
    protected $table = 'lms_training_types';

}
