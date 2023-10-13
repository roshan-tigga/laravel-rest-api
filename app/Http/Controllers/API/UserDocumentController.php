<?php

namespace App\Http\Controllers\API;

use App\Models\UserDocument;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;


class UserDocumentController extends BaseController
{
    public function getDocumentList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $userDocuments = UserDocument::where('is_active','=','1')
        ->where(function($query) use ($roleId,$organizationId,$authId){
            if($roleId == 1){
                $query->where('org_id',$organizationId);
            }else{
                $query->where('org_id',$organizationId);
                $query->where('user_id',$authId);
            }
        })
        ->select('document_id as documentId','title', 'category', 'document_link as documentLink', 'is_active as isActive')
        ->get();
        if($userDocuments->count() > 0){
            foreach($userDocuments as $userDocument){

                if($userDocument->documentLink != ''){
                    $userDocument->documentLink = getFileS3Bucket(getPathS3Bucket().'/documents/'.$userDocument->documentLink);
                }

            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$userDocuments],200);
    }

    public function addDocument(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'title' => 'required',
            'documentLink' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $documentLink = '';
        if($request->file('documentLink') != ''){
            $path = getPathS3Bucket().'/documents';
            $s3DocumentLink = Storage::disk('s3')->put($path, $request->documentLink);
            $documentLink = substr($s3DocumentLink, strrpos($s3DocumentLink, '/') + 1);
        }

        $userDocument = new UserDocument;
        $userDocument->title = $request->title; 
        $userDocument->category = $request->category; 
        $userDocument->document_link = $documentLink;
        $userDocument->user_id = $authId; 
        $userDocument->org_id = $organizationId; 
        $userDocument->created_id = $authId;
        $userDocument->modified_id = $authId;
        $userDocument->save(); 

        return response()->json(['status'=>true,'code'=>201,'message'=>'Document has been created successfully.'],201);
    }

}
