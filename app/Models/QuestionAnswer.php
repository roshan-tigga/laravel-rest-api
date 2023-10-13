<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionAnswer extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'answer_id';

    protected $table = 'lms_question_answer';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
