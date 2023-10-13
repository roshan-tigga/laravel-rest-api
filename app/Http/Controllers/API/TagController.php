<?php

namespace App\Http\Controllers\API;

use App\Models\Tag;
use Illuminate\Http\Request;
use Validator;
use Auth;
use Carbon\Carbon;
use App\Http\Controllers\API\BaseController as BaseController;

class TagController extends BaseController
{
    public function getTagList(Request $request){
        $organizationId = Auth::user()->org_id;
        if(!empty($request->organizationId)){
            $organizationId = $request->organizationId;
        }
        $tags = Tag::where('is_active','1')->orderBy('tag_id','DESC')
        ->where('org_id',$organizationId)
        ->select('tag_id as tagId','tag_name as tagName','is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$tags],200);
    }

    public function addTag(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'tags'=>'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(!empty($request->tags)){
            $tagArray = [];
            $tagsArray = [];
            foreach($request->tags as $tag){
                if(isset($tag)) {
                    $tagArray = [
                        'tag_name'=>$tag,
                        'ref_table_name'=> 'lms_org_master',
                        'org_id' => $organizationId,
                        'date_created' => Carbon::now(),
                        'date_modified' => Carbon::now(),
                        'created_id' => $authId,
                        'modified_id' => $authId
                    ];

                    $tagsArray[] = $tagArray;
                }
            }
            if(isset($tagsArray)) {
                Tag::insert($tagsArray);
            }
            
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Tags has been created successfully.'],200);
    }

    public function deleteTag(Request $request){
        $validator = Validator::make($request->all(), [
            'tagId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $tag = Tag::where(['is_active'=>'1','tag_id'=>$request->tagId]);
        if($tag->count() > 0){

            $tag->update([
                'is_active' => '0',
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Tag has been deleted successfully.'],200);
        }else{
            return response()->json(['status'=>false,'code'=>404,'error'=>'Tag is not found.'], 404);
        }
    }
}
