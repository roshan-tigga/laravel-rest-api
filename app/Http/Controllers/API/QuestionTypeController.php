<?php

namespace App\Http\Controllers\API;

use App\Models\QuestionType;
use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;

class QuestionTypeController extends BaseController
{
    public function getQuestionTypeList(){
        $questionTypes = QuestionType::where('is_active',1)->select('question_type_id as questionTypeId','question_type as questionType','description')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$questionTypes],200);
    }
}
