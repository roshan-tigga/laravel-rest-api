<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentQuestion extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'question_id';

    protected $table = 'lms_assessment_question';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
