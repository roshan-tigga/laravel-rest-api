<?php

namespace App\Http\Controllers\API;

use App\Models\Domain;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;

class DomainController extends BaseController
{

    public function getDomainList()
    {
        $domain = Domain::where('is_active','1')
        ->select('domain_id as domainId', 'domain_name as domainName','is_production as isProduction', 'is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$domain],200);
    }

   
    public function getDomainById($domainId)
    {
        $domain = Domain::where('domain_id',$domainId)->where('is_active','1');
        if ($domain->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Domain is not found.'], 404);
        }
        $domain = $domain ->select('domain_id as domainId', 'domain_name as domainName','is_production as isProduction')->first();
        return response()->json(['status'=>true,'code'=>200,'data'=>$domain],200);
    }

    public function updateDomainById(Request $request)
    {
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'domainId' => 'required|integer',
            'domainName' => 'required|string|max:50'
        ]);
        

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        $domain = Domain::where('domain_id',$request->domainId)->where('is_active','1');
        if ($domain->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Domain is not found.'], 404);
        }
        
        $domain->update([
            'domain_name' => $request->domainName
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Domain has been updated successfully.'],200);
    }

   
}
