<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicField extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'dynamic_field_id';

    protected $table = 'lms_dynamic_fields';
}
