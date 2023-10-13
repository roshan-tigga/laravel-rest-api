<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleMaster extends Model 
{
    use HasFactory;

    protected $primaryKey = 'module_id';

    protected $table = 'lms_module_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';

}
