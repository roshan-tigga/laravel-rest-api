<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationDynamicField extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'dynamic_field_id';

    protected $table = 'lms_notification_dynamic_fields';
}
