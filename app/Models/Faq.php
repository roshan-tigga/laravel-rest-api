<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasFactory;

    protected $primaryKey = 'faq_id';

    protected $table = 'lms_faq';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
