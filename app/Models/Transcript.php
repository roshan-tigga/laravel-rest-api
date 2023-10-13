<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transcript extends Model
{
    use HasFactory;

    protected $primaryKey = 'transcript_id';

    protected $table = 'lms_user_transcript';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
