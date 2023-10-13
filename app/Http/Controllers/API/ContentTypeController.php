<?php

namespace App\Http\Controllers\API;

use App\Models\ContentType;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class ContentTypeController extends BaseController
{
    public function getContentTypeList(){
        $contentType = ContentType::orderBy('content_types_id','DESC')
        ->select('content_types_id as contentTypesId', 'content_type as contentType', 'support_formats as supportFormats')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$contentType],200);
    }

    public function getContentTypeById($contentTypeId){
        $contentTypeRedis = Redis::get('contentTypeRedis' . $contentTypeId);
        if(isset($contentTypeRedis)){
            return response()->json(['status'=>true,'code'=>200,'data'=>json_decode($contentTypeRedis,false)],200);
        }else{
            $contentType = ContentType::where(['content_types_id'=>$contentTypeId]);
            if ($contentType->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Content type is not found.'], 404);
            }
            $contentType = $contentType->select('content_types_id as contentTypesId', 'content_type as contentType', 'support_formats as supportFormats')->first();
            Redis::set('contentTypeRedis' . $contentTypeId, $contentType);
            return response()->json(['status'=>true,'code'=>200,'data'=>$contentType],200);
        }
    }
}
