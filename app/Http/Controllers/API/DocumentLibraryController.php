<?php

namespace App\Http\Controllers\API;

use App\Models\DocumentLibrary;
use App\Models\CategoryMaster; 
use App\Models\DocumentLibraryCategory;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;


class DocumentLibraryController extends BaseController
{
    public function getDocumentLibraryList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        if($roleId == 1){
            $userDocuments = DocumentLibrary::where('is_active','!=','0')
            ->orderBy('order','ASC')
            ->select('document_library_id as documentLibraryId','document_library_title as documentLibraryTitle','document_library_type as documentLibraryType','document_library_description as documentLibraryDescription','document_library_link as documentLibraryLink','allow_download as allowDownload','is_publish as isPublish','is_active as isActive')
            ->get();
            if($userDocuments->count() > 0 ){
                foreach($userDocuments as $userDocument){
                    //$userDocument->category = CategoryMaster::where('is_active','1')->whereIn('category_id',explode(',',$userDocument->category))->pluck('category_name');
                    $userDocument->category = DocumentLibraryCategory::
                    join('lms_category_master','lms_category_master.category_id','=','lms_document_library_category.category_id')
                    ->whereIn('document_library_id',explode(',',$userDocument->documentLibraryId))
                    ->pluck('lms_category_master.category_name');

                    if($userDocument->documentLibraryType == 4){
                        $userDocument->documentLibraryLink = $userDocument->documentLibraryLink;
                    }else{
                        if ($userDocument->documentLibraryLink != '') {
                            $userDocument->documentLibraryLink = getFileS3Bucket(getPathS3Bucket() . '/document_library/' . $userDocument->documentLibraryLink);
                        }
                    }
                }
            }
        }else{
            $userDocuments = DocumentLibrary::where('is_active','!=','0')
            ->orderBy('order','ASC')
            ->select('document_library_id as documentLibraryId','document_library_title as documentLibraryTitle','is_active as isActive')
            ->where('is_publish','1')
            ->get();
            if($userDocuments->count() > 0 ){
                foreach($userDocuments as $userDocument){
                    
                    $userDocument->category = DocumentLibraryCategory::
                    join('lms_category_master','lms_category_master.category_id','=','lms_document_library_category.category_id')
                    ->whereIn('document_library_id',explode(',',$userDocument->documentLibraryId))
                    ->pluck('lms_category_master.category_name'); 
                }
            }
        }
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$userDocuments],200);
    }

    public function getOrgDocumentLibraryList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $userDocuments = DocumentLibrary::where('is_active','!=','0')
        ->orderBy('order','ASC')
        ->select('document_library_id as documentLibraryId','document_library_title as documentLibraryTitle','document_library_type as documentLibraryType','document_library_link as documentLibraryLink','allow_download as allowDownload','is_active as isActive')
        ->where('is_publish','1')
        ->get();
        if($userDocuments->count() > 0 ){
            foreach($userDocuments as $userDocument){
                
                $userDocument->category = DocumentLibraryCategory::
                join('lms_category_master','lms_category_master.category_id','=','lms_document_library_category.category_id')
                ->whereIn('document_library_id',explode(',',$userDocument->documentLibraryId))
                ->pluck('lms_category_master.category_name');

                if($userDocument->documentLibraryType == 4){
                    $userDocument->documentLibraryLink = $userDocument->documentLibraryLink;
                }else{
                    if ($userDocument->documentLibraryLink != '') {
                        $userDocument->documentLibraryLink = getFileS3Bucket(getPathS3Bucket() . '/document_library/' . $userDocument->documentLibraryLink);
                    }
                }
                
            }
        }
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$userDocuments],200);
    }

    public function addDocumentLibrary(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'documentLibraryTitle' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $documentLibraryLink = '';
        if($request->file('documentLibraryLink') != ''){
            $documentLibraryFilename = $request->file('documentLibraryLink')->getClientOriginalName();
            $path = getPathS3Bucket().'/document_library';
            $s3DocumentLink = Storage::disk('s3')->put($path, $request->documentLibraryLink);
            $documentLibraryLink = substr($s3DocumentLink, strrpos($s3DocumentLink, '/') + 1);
        }else{
            $documentLibraryFilename = $request->documentLibraryLink;
        }

    
        $userDocument = new DocumentLibrary;
        $userDocument->document_library_title = $request->documentLibraryTitle; 
        $userDocument->document_library_filename = $documentLibraryFilename; 
        $userDocument->document_library_description = $request->documentLibraryDescription; 
        $userDocument->document_library_type = $request->documentLibraryType; 
        if($request->documentLibraryType == 4){
            $userDocument->document_library_link = $request->documentLibraryLink;
        }else{
            $userDocument->document_library_link = $documentLibraryLink;
        }
        
        $userDocument->allow_download = $request->allowDownload ? $request->allowDownload : 0;
        $userDocument->is_publish = $request->isPublish ? $request->isPublish : 0; 
        $userDocument->created_id = $authId;
        $userDocument->modified_id = $authId;
        $userDocument->save(); 


        if(!empty($request->category)){
            $explodeCategorys = explode(',',$request->category);
            if(!empty($explodeCategorys)){
                foreach($explodeCategorys as $explodeCategory){
                    $DocumentLibraryCategory = new DocumentLibraryCategory;
                    $DocumentLibraryCategory->document_library_id = $userDocument->document_library_id;
                    $DocumentLibraryCategory->category_id = $explodeCategory;
                    $DocumentLibraryCategory->save();
                }
            }
        }

        return response()->json(['status'=>true,'code'=>201,'message'=>'Document library has been created successfully.'],201);
    }

    public function getDocumentLibraryById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $userDocument = DocumentLibrary::where('is_active','!=','0')
        ->select('document_library_id as documentLibraryId','document_library_title as documentLibraryTitle','document_library_type as documentLibraryType','document_library_description as documentLibraryDescription','document_library_link as documentLibraryLink','allow_download as allowDownload','document_library_filename as documentLibraryFilename','is_publish as isPublish','order','is_active as isActive')
        ->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Document library not found.'],400);
        }

        if($userDocument->count() > 0 ){

            $userDocument->category = DocumentLibraryCategory::
            join('lms_category_master','lms_category_master.category_id','=','lms_document_library_category.category_id')
            ->whereIn('document_library_id',explode(',',$id))
            ->select('lms_category_master.category_id as categoryId','lms_category_master.category_name as categoryName')
            ->get();
            

            if($userDocument->documentLibraryType == 4){
                $userDocument->documentLibraryLink = $userDocument->documentLibraryLink;
            }else{
                if ($userDocument->documentLibraryLink != '') {
                    $userDocument->documentLibraryLink = getFileS3Bucket(getPathS3Bucket() . '/document_library/' . $userDocument->documentLibraryLink);
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$userDocument],200);
    }

    public function updateDocumentLibraryById(Request $request,$id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'documentLibraryTitle' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $userDocument = DocumentLibrary::where('is_active','!=','0')->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Document library not found.'],400);
        }
        $documentLibraryLink = '';
        if($request->file('documentLibraryLink') != ''){
            $documentLibraryFilename = $request->file('documentLibraryLink')->getClientOriginalName();
            $path = getPathS3Bucket().'/document_library';
            $s3DocumentLink = Storage::disk('s3')->put($path, $request->documentLibraryLink);
            $documentLibraryLink = substr($s3DocumentLink, strrpos($s3DocumentLink, '/') + 1);
        }else{
            $documentLibraryFilename = $request->documentLibraryLink;
        }


        $userDocument->document_library_title = $request->documentLibraryTitle; 
        $userDocument->document_library_filename = $documentLibraryFilename; 
        $userDocument->document_library_description = $request->documentLibraryDescription; 
        if($request->documentLibraryType == 4){
            $userDocument->document_library_link = $request->documentLibraryLink;
        }else{
            if($documentLibraryLink != ''){
                $userDocument->document_library_link = $documentLibraryLink;
            }
        }
        $userDocument->document_library_type = $request->documentLibraryType; 
        $userDocument->allow_download = $request->allowDownload ? $request->allowDownload : 0;
        $userDocument->is_publish = $request->isPublish ? $request->isPublish : 0; 
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        if(!empty($request->category)){
            $explodeCategorys = explode(',',$request->category);
            if(!empty($explodeCategorys)){
                DocumentLibraryCategory::where('document_library_id',$id)->delete();
                foreach($explodeCategorys as $explodeCategory){
                    $DocumentLibraryCategory = new DocumentLibraryCategory;
                    $DocumentLibraryCategory->document_library_id = $userDocument->document_library_id;
                    $DocumentLibraryCategory->category_id = $explodeCategory;
                    $DocumentLibraryCategory->save();
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Document library has been updated successfully.'],200);
    }

    public function deleteDocumentLibraryById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $userDocument = DocumentLibrary::where('is_active','!=','0')->find($id);
        if(is_null($userDocument)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Document library not found.'],400);
        }

        $userDocument->is_active = 0; 
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        return response()->json(['status'=>true,'code'=>200,'message'=>'Document library has been deleted successfully.'],200);
    }


    public function documentLibraryOrder(Request $request){

        $validator = Validator::make($request->all(), [
            'orders' => 'required|array'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

       foreach($request->orders as $k => $order){
            $documentLibrary = DocumentLibrary::where('document_library_id',$k);
            if($documentLibrary->count() > 0){
                $documentLibrary->update([
                    'order' => $order
                ]);
            }
       }

       return response()->json(['status'=>true,'code'=>200,'message'=>'Document library has been updated successfully.'],200);

    }

}
