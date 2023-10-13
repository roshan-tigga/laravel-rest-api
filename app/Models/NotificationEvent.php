<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'notification_event_id';
    protected $table = 'lms_notification_events';
    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
