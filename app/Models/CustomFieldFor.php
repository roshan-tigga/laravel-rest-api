<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomFieldFor extends Model
{
    use HasFactory;

    protected $primaryKey = 'custom_field_for_id';

    protected $table = 'lms_custom_field_for_master';

    public $timestamps = false;
    // const CREATED_AT = 'date_created';
    // const UPDATED_AT = 'date_modified';
}
