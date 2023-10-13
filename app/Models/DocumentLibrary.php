<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentLibrary extends Model
{
    use HasFactory;

    protected $primaryKey = 'document_library_id';

    protected $table = 'lms_document_library';

    const CREATED_AT = 'date_created';
    const UPDATED_AT = 'date_modified';
}
