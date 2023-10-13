<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionType extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'question_type_id';

    protected $table = 'lms_question_types';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
