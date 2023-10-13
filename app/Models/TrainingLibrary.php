<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingLibrary extends Model
{
    use HasFactory;

    protected $primaryKey = 'training_id'; 
    protected $table = 'lms_training_library';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
