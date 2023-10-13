<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CertificateMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'certificate_id';

    protected $table = 'lms_certificate_master';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
