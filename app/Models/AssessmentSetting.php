<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentSetting extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'assessment_setting_id';

    protected $table = 'lms_assessment_settings';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
