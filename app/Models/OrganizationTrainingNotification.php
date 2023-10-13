<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationTrainingNotification extends Model
{
    use HasFactory;

    public $timestamps = false;
    protected $primaryKey = 'training_notification_id'; 
    protected $table = 'lms_org_training_notifications';
}
