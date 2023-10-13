<?php

namespace App\Http\Controllers\API;

use App\Models\TrainingStatus;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;

class TrainingStatusController extends BaseController
{
    public function getTrainingStatusList(){
        $trainingStatus = TrainingStatus::select('training_status_id as trainingStatusId','training_status as trainingStatus')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$trainingStatus],200);
    }
}
