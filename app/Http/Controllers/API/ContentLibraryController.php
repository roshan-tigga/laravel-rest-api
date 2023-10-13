<?php

namespace App\Http\Controllers\API;

use App\Models\ContentLibrary;
use App\Models\ContentType;
use App\Models\Media;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Illuminate\Support\Facades\Storage;

class ContentLibraryController extends BaseController
{
    public function getContentLibraryList(Request $request){
        $sort = $request->has('sort') ? $request->get('sort') : 'content_library.content_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'contentName'){
            $sortColumn = 'content_library.content_name';
        }elseif($sort == 'contentVersion'){
            $sortColumn = 'content_library.content_version';
        }elseif($sort == 'contentType'){
            $sortColumn = 'content_type.content_type';
        }elseif($sort == 'mediaName'){
            $sortColumn = 'media.media_name';
        }elseif($sort == 'parentContentName'){
            $sortColumn = 'parent_content_library.content_name';
        }elseif($sort == 'organizationName'){
            //$sortColumn = 'org_master.organization_name';
        }elseif($sort == 'isActive'){
            $sortColumn = 'content_library.is_active';
        }

        

        $contentLibraries = DB::table('lms_content_library as content_library')
        //->leftJoin('lms_org_master as org_master','content_library.org_id','=','org_master.org_id')
        ->leftJoin('lms_media as media','content_library.media_id','=','media.media_id')
        ->leftJoin('lms_content_types as content_type','content_library.content_types_id','=','content_type.content_types_id')
        ->leftJoin('lms_content_library as parent_content_library','content_library.parent_content_id','=','parent_content_library.content_id')
        ->where('content_library.is_active','1')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('content_library.content_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('content_library.content_version', 'LIKE', '%'.$search.'%');
                $query->orWhere('content_type.content_type', 'LIKE', '%'.$search.'%');
                $query->orWhere('media.media_name', 'LIKE', '%'.$search.'%');
                //$query->orWhere('parent_content_library.content_name', 'LIKE', '%'.$search.'%');
                //$query->orWhere('org_master.organization_name', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('content_library.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('content_library.is_active','2');
                }
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('content_library.content_id as contentId', 'content_library.content_name as contentName', 'content_library.content_version as contentVersion', 'content_type.content_type as contentType', 'media.media_name as mediaName', 'parent_content_library.content_name as parentContentName', 'content_library.is_active as isActive')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$contentLibraries],200);
    }

    public function getContentLibraryOptionList(){ 
        $contentLibraries = ContentLibrary::where('is_active','1')->orderBy('content_name','ASC')->whereNull('parent_content_id')
        ->select('content_id as contentId', 'content_name as contentName')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$contentLibraries],200);
    }

    public function addContentLibrary(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'contentName' => 'required|max:64',
            'contentType' => 'required|integer',
            'parentContent' => 'nullable|integer',
            //'organization' => 'required|integer',
            'mediaName' => 'nullable||max:64',
            'mediaType' => 'required|integer',
            'mediaUrl' => 'nullable',
            'media' => 'nullable|integer',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $mediaUrl = $mediaSize = $mediaType = '';
        if($request->file('mediaUrl') != ''){
            $path = getPathS3Bucket().'/media';
            $s3MediaUrl = Storage::disk('s3')->put($path, $request->mediaUrl);
            $mediaUrl = substr($s3MediaUrl, strrpos($s3MediaUrl, '/') + 1);
            $mediaSize = $request->file('mediaUrl')->getSize();
            $mediaType = $request->file('mediaUrl')->extension();
        }


        if($request->mediaType == 1){
            $media = new Media;
            $media->media_name = $request->mediaName;
            $media->media_url = $mediaUrl;
            $media->media_size = $mediaSize;
            $media->media_type = $mediaType;
            //$media->org_id = $request->organization;
            $media->created_id = $authId;
            $media->modified_id = $authId;
            $media->save();

            $mediaId = $media->media_id;
        }else{
            $mediaId = $request->media;
        }

        if($request->parentContent == ''){
            $parentContent = ContentLibrary::where('is_active','!=','0')->whereNull('parent_content_id');
            if($parentContent->count() > 0){
                $contentVersion = $parentContent->max('content_version') + 1.0;
            }else{
               $contentVersion = 1.0;
            }
        }else{
            $parentContent = ContentLibrary::where('is_active','!=','0')->where('parent_content_id',$request->parentContent);
            if($parentContent->count() > 0){
                $contentVersion = $parentContent->max('content_version') + 0.1;
            }else{
                $parentContent = ContentLibrary::where('is_active','!=','0')->where('content_id',$request->parentContent);
                if($parentContent->count() > 0){
                    $contentVersion = $parentContent->max('content_version') + 0.1;
                }else{
                    $contentVersion = 1.0;
                }
            }
        }

        $contentLibrary = new ContentLibrary;
        $contentLibrary->content_name = $request->contentName;
        $contentLibrary->content_version = $contentVersion;
        $contentLibrary->content_types_id = $request->contentType;
        $contentLibrary->media_id = $mediaId;
        $contentLibrary->parent_content_id = $request->parentContent;
        //$contentLibrary->org_id = $request->organization;
        $contentLibrary->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $contentLibrary->created_id = $authId;
        $contentLibrary->modified_id = $authId;
        $contentLibrary->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Media has been created successfully.'],200);
    }

    public function getContentLibraryById($contentId){

        $contentLibraryRedis = Redis::get('contentLibraryRedis' . $contentId);
        if(isset($contentLibraryRedis)){
            $contentLibraryRedis = json_decode($contentLibraryRedis,false);
            if($contentLibraryRedis->mediaUrl != ''){
                $contentLibraryRedis->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$contentLibraryRedis->mediaUrl);
            }
           return response()->json(['status'=>true,'code'=>200,'data'=>$contentLibraryRedis],200);
        }else{
            $contentLibrary = DB::table('lms_content_library as content_library')
            //->leftJoin('lms_org_master as org_master','content_library.org_id','=','org_master.org_id')
            ->leftJoin('lms_media as media','content_library.media_id','=','media.media_id')
            ->leftJoin('lms_content_types as content_type','content_library.content_types_id','=','content_type.content_types_id')
            ->leftJoin('lms_content_library as parent_content_library','content_library.parent_content_id','=','parent_content_library.content_id')
            ->where(['content_library.is_active'=>'1','content_library.content_id'=>$contentId]);
            
            if ($contentLibrary->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Content library is not found.'], 404);
            }
            $contentLibrary = $contentLibrary->select('content_library.content_id as contentId', 'content_library.content_name as contentName', 'content_library.content_version as contentVersion', 'content_library.content_types_id as contentTypeId', 'content_type.content_type as contentType', 'content_library.media_id as mediaId', 'media.media_name as mediaName', 'media.media_url as mediaUrl', 'content_library.parent_content_id as parentContentId', 'parent_content_library.content_name as parentContentName')->first();
            Redis::set('contentLibraryRedis' . $contentId, json_encode($contentLibrary,false));

            if($contentLibrary->mediaUrl != ''){
                $contentLibrary->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$contentLibrary->mediaUrl);
            }
            
            return response()->json(['status'=>true,'code'=>200,'data'=>$contentLibrary],200);
        }
    }

    public function updateContentLibrary(Request $request){
        $authId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'contentName' => 'required|max:64',
            'contentType' => 'required|integer',
            'parentContent' => 'nullable|integer',
            //'organization' => 'required|integer',
            'mediaName' => 'nullable||max:64',
            'mediaType' => 'required|integer',
            'mediaUrl' => 'nullable',
            'media' => 'nullable|integer',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        
        $contentLibrary = ContentLibrary::where(['is_active'=>'1','content_id'=>$request->contentId]);
        if ($contentLibrary->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Content library is not found.'], 404);
        }

        
        $mediaId = $contentLibrary->first()->media_id;

        if($request->mediaType == 1){
            $mediaUrl = $mediaSize = $mediaType = '';

            if($request->file('mediaUrl') != ''){
                $path = getPathS3Bucket().'/media';
                $s3MediaUrl = Storage::disk('s3')->put($path, $request->mediaUrl);
                $mediaUrl = substr($s3MediaUrl, strrpos($s3MediaUrl, '/') + 1);
                $mediaSize = $request->file('mediaUrl')->getSize();
                $mediaType = $request->file('mediaUrl')->extension();

                Media::where('media_id',$mediaId)->update([
                    'media_name' => $request->mediaName,
                    'media_url' => $mediaUrl,
                    'media_size' => $mediaSize,
                    'media_type' => $mediaType,
                    //'org_id' => $request->organization,
                    'modified_id' => $authId
                ]);
            }
        }else{
            $mediaId = $request->media;
        }


        if($request->parentContent == ''){
            $parentContent = ContentLibrary::where('is_active','!=','0')->where('content_id','!=',$request->contentId)->whereNull('parent_content_id');
            if($parentContent->count() > 0){
                $contentVersion = $parentContent->max('content_version') + 1.0;
            }else{
               $contentVersion = 1.0;
            }

            $contentLibrary->update([
                'content_name' => $request->contentName,
                'content_version' => $contentVersion,
                'content_types_id' => $request->contentType,
                'media_id' => $mediaId,
                'parent_content_id' => $request->parentContent,
                //'org_id' => $request->organization,
                'is_active' => $request->isActive == '' ? $contentLibrary->first()->is_active ? $contentLibrary->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);

        }else{
            $parentContent = ContentLibrary::where('is_active','!=','0')->where('parent_content_id',$request->parentContent);
            if($parentContent->count() > 0){
                $contentVersion = $parentContent->max('content_version') + 0.1;
            }else{
                $parentContent = ContentLibrary::where('is_active','!=','0')->where('content_id',$request->parentContent);
                if($parentContent->count() > 0){
                    $contentVersion = $parentContent->max('content_version') + 0.1;
                }else{
                    $contentVersion = 1.0;
                }
            }

            $contentLibrary->update([
                'content_name' => $request->contentName,
                //'content_version' => $contentVersion,
                'content_types_id' => $request->contentType,
                'media_id' => $mediaId,
                'parent_content_id' => $request->parentContent,
                //'org_id' => $request->organization,
                'is_active' => $request->isActive == '' ? $contentLibrary->first()->is_active ? $contentLibrary->first()->is_active : '1' : $request->isActive,
                'modified_id' => $authId
            ]);
        }


    
        $contentLibrary = DB::table('lms_content_library as content_library')
        //->leftJoin('lms_org_master as org_master','content_library.org_id','=','org_master.org_id')
        ->leftJoin('lms_media as media','content_library.media_id','=','media.media_id')
        ->leftJoin('lms_content_types as content_type','content_library.content_types_id','=','content_type.content_types_id')
        ->leftJoin('lms_content_library as parent_content_library','content_library.parent_content_id','=','parent_content_library.content_id')
        ->where(['content_library.is_active'=>'1','content_library.content_id'=>$request->contentId])
        ->select('content_library.content_id as contentId', 'content_library.content_name as contentName', 'content_library.content_version as contentVersion', 'content_library.content_types_id as contentTypeId', 'content_type.content_type as contentType', 'content_library.media_id as mediaId', 'media.media_name as mediaName', 'media.media_url as mediaUrl', 'content_library.parent_content_id as parentContentId', 'parent_content_library.content_name as parentContentName')
        ->first();

        Redis::del('contentLibraryRedis' . $request->contentId);
        Redis::set('contentLibraryRedis' . $request->contentId, json_encode($contentLibrary,false));

        return response()->json(['status'=>true,'code'=>200,'message'=>'Content library has been updated successfully.'],200);
    }

    public function deleteContentLibrary(Request $request){
        $contentLibrary = ContentLibrary::where(['is_active'=>'1','content_id'=>$request->contentId]);
        if ($contentLibrary->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Content library is not found.'], 404);
        }
        $contentLibrary->update([
            'is_active' => 0
        ]);
        Redis::del('contentLibraryRedis' . $request->contentId);
        return response()->json(['status'=>true,'code'=>200,'message'=>'Content library has been deleted successfully.'],200);
    }
}
