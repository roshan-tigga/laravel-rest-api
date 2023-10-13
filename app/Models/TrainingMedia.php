<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrainingMedia extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'training_media_id'; 
    protected $table = 'lms_training_media';

}
