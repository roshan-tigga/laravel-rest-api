<?php

namespace App\Http\Controllers\API;

use App\Models\Login;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class ActivityController extends BaseController
{
    public function activity(Request $request){
        Login::where('login_id',Auth::id())->update(['last_login_date'=>Carbon::now()]);
        return response()->json(['status'=>true,'code'=>200,'message'=>'Activity has been added successfully.'],200);
    }
}
