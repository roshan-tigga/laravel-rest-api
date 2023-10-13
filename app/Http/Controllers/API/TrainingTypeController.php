<?php

namespace App\Http\Controllers\API;

use App\Models\TrainingType;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;

class TrainingTypeController extends BaseController
{
    public function getTrainingTypeList(){
        $trainingTypes = TrainingType::select('training_type_id as trainingTypeId','training_type as trainingType')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$trainingTypes],200);
    }
}
