<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleMaster extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'role_id'; 
    
    protected $table = 'lms_roles';

    protected $fillable = [
        'role_id'
    ];


}
