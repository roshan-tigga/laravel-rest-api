<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentLibraryCategory extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'lms_document_library_category';

    public $timestamps = false;
    // const CREATED_AT = 'date_created';
    // const UPDATED_AT = 'date_modified';
}
