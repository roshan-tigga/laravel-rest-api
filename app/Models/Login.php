<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Login extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $primaryKey = 'login_id';

    public $timestamps = false;
    
    protected $rememberTokenName = false;

    protected $table = 'lms_user_login';

    protected $fillable = [
        'user_name', 'last_login_date','authentication_email'
    ];

    protected $hidden = [
        //'user_password',
        'previous_password_1',
        'previous_password_2',
        'previous_password_3',
        'previous_password_4',
    ];

    public function getAuthPassword()
    {
        return $this->user_password;
    }

    public function getEmailForPasswordReset()
    {
        return $this->authentication_email;
    }

    public function routeNotificationForMail($notification)
    {
        return $this->authentication_email;
    }


    public function user(){
        return $this->hasOne(User::class,'user_id','user_id');
    }

    public function domain(){
        return $this->hasOne(Domain::class,'domain_id','domain_id');
    }

    public function organization(){
        return $this->hasOne(Organization::class,'org_id','org_id');
    }
}


