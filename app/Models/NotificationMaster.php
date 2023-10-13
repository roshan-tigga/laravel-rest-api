<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationMaster extends Model
{
    use HasFactory;

    //public $timestamps = false;

    protected $primaryKey = 'notification_id';

    protected $table = 'lms_notification_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
