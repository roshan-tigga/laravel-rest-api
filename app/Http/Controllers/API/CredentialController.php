<?php

namespace App\Http\Controllers\API;

use App\Models\Credential;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use App\Models\CategoryMaster;
use App\Models\OrganizationCredentialCustomField;
use App\Models\OrganizationCustomField;
use App\Models\OrganizationCustomNumberOfField;


class CredentialController extends BaseController
{

    public function getCredentialList(Request $request){

        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'credential.id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';
        $status = $request->has('status') ? $request->get('status') : '1';

        $sortColumn = $sort;

        if($status == 'deleted'){
            $status = 5;
        }
        if($status == 'archived'){
            $status = 4;
        }

        if($sort == 'credentialTitle'){
            $sortColumn = 'credential.credential_title';
        }elseif($sort == 'credentialCode'){
            $sortColumn = 'credential.credential_code';
        }elseif($sort == 'expirationTime'){
            $sortColumn = 'credential.expiration_time';
        }elseif($sort == 'status'){
            $sortColumn = 'status.training_status';
        }

        $credentials = DB::table('lms_org_credentials as credential')
        //->leftjoin('lms_category_master as category','credential.category_id','=','category.category_id')
        ->leftjoin('lms_training_status as status','credential.status','=','status.training_status_id')
        ->where(function($query) use ($status){
            if($status == 5 || $status == 4){
                $query->where('credential.status',$status);
            }else{
                $query->orWhere('credential.status',1);
                $query->orWhere('credential.status',2);
                $query->orWhere('credential.status',3);
            }
        })
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('credential.credential_code', 'LIKE', '%'.$search.'%');
                $query->where('credential.credential_title', 'LIKE', '%'.$search.'%');
                $query->orWhere('credential.expiration_time', 'LIKE', '%'.$search.'%');
                $query->orWhere('status.training_status', 'LIKE', '%'.$search.'%');
                //$query->orWhere('category.category_name', 'LIKE', '%'.$search.'%');
            }
        })
        ->where(function($query) use ($organizationId,$roleId){
            if($roleId != 1){
                $query->where('credential.org_id',$organizationId);
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('credential.id as credentialId','credential.credential_code as credentialCode','credential.credential_title as credentialTitle','credential.category_id as categoryName', 'credential.expiration_time as expirationTime','status.training_status as status')
        ->get();

        if($credentials->count() > 0){
            foreach($credentials as $credential){
                if(isset($credential->categoryName)){
                    $categoryId = explode(',',$credential->categoryName);
                    $credential->categoryName = CategoryMaster::whereIn('category_id',$categoryId)->pluck('category_name');
                }
            }
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$credentials],200);
    }

    public function addCredential(Request $request){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'credentialTitle' => 'required',
            'status' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $categoryId = '';
            if(!empty($request->category)){
                $explodeCategory = explode(',',$request->category);
                $categoryId = implode(',',$explodeCategory);
            }

            $credential = new Credential;
            $credential->credential_title = $request->credentialTitle;
            $credential->credential_code = $request->credentialCode;
            $credential->category_id = $categoryId;
            $credential->credential_note = $request->credentialNote;
            $credential->credential_description = $request->credentialDescription;
            $credential->expiration_time = $request->expirationTime;
            $credential->days_till_expiration = $request->daysTillExpiration;
            $credential->status = $request->status;
            $credential->org_id = $organizationId;
            $credential->created_id = $authId;
            $credential->modified_id = $authId;
            $credential->save();

            if(!empty($request->customFields)){
                    
                $customFields = json_decode($request->customFields);

                if(!empty($customFields->text)){
                    foreach($customFields->text as $text){
                        $id = $text->id;
                        $value = isset($text->value) ? $text->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationCredentialCustomField = new OrganizationCredentialCustomField;
                        $OrganizationCredentialCustomField->credential_id = $credential->id;
                        $OrganizationCredentialCustomField->custom_field_id = $id;
                        //$OrganizationCredentialCustomField->custom_number_of_field_id = '';
                        $OrganizationCredentialCustomField->custom_field_value = $value;
                        $OrganizationCredentialCustomField->org_id = $organizationId;
                        $OrganizationCredentialCustomField->created_id = $authId;
                        $OrganizationCredentialCustomField->modified_id = $authId;
                        $OrganizationCredentialCustomField->save();
                    }
                }
                if(!empty($customFields->radio)){
                    foreach($customFields->radio as $radio){
                        $id = $radio->id;
                        $value = isset($radio->value) ? $radio->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationCredentialCustomField = new OrganizationCredentialCustomField;
                        $OrganizationCredentialCustomField->credential_id = $credential->id;
                        $OrganizationCredentialCustomField->custom_field_id = $id;
                        $OrganizationCredentialCustomField->custom_number_of_field_id = $value;
                        $OrganizationCredentialCustomField->custom_field_value = 1;
                        $OrganizationCredentialCustomField->org_id = $organizationId;
                        $OrganizationCredentialCustomField->created_id = $authId;
                        $OrganizationCredentialCustomField->modified_id = $authId;
                        $OrganizationCredentialCustomField->save();
                    }
                }
            }

            return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been created successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getCredentialById($credentialId){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;

            $credential = DB::table('lms_org_credentials as credential')
            //->leftjoin('lms_category_master as category','credential.category_id','=','category.category_id')
            ->leftjoin('lms_training_status as status','credential.status','=','status.training_status_id')
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('credential.org_id',$organizationId);
                }
            })
            ->where('credential.id',$credentialId)
            ->select('credential.id as credentialId','credential.credential_title as credentialTitle','credential.credential_code as credentialCode','credential.credential_note as credentialNote','credential.credential_description as credentialDescription','credential.expiration_time as expirationTime','credential.days_till_expiration as daysTillExpiration',
            'credential.category_id as category',
            'credential.org_id as organizationId',
            'status.training_status_id as statusId', 
            'status.training_status as status')
            ->first();
            
            if(!empty($credential->category)){
                $categoryId = explode(',',$credential->category);
                $credential->category = CategoryMaster::whereIn('category_id',$categoryId)->select('category_id as categoryId','category_name as categoryName')->get();
            }

            $OrganizationCustomFields = OrganizationCustomField::
            leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
            ->where('lms_org_custom_fields.is_active','1')
            ->where('lms_org_custom_fields.custom_field_for_id',3)
            ->where('lms_org_custom_fields.org_id',$credential->organizationId)
            ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
            ->get();
            if($OrganizationCustomFields->count() > 0){
                foreach($OrganizationCustomFields as $OrganizationCustomField){

                    $customFieldTypeId = $OrganizationCustomField->customFieldTypeId;

                    $OrganizationCustomNumberOfFields = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)
                    ->select('custom_number_of_field_id as id','label_name as labelName')
                    ->get();
                    if($OrganizationCustomNumberOfFields->count() > 0){
                        $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfFields;

                        foreach($OrganizationCustomNumberOfFields as $OrganizationCustomNumberOfField){
                            $OrganizationUserCustomFields = OrganizationCredentialCustomField::where('credential_id',$credentialId)->where('is_active','1')->where('org_id',$credential->organizationId)->where('custom_field_id',$OrganizationCustomField->id)->where('custom_number_of_field_id',$OrganizationCustomNumberOfField->id)->get();
                            if($OrganizationUserCustomFields->count() > 0){
                                foreach($OrganizationUserCustomFields as $OrganizationUserCustomField){
                                    if($customFieldTypeId == 4){
                                        $OrganizationCustomNumberOfField->checked = $OrganizationUserCustomField->custom_field_value;
                                    }else if($customFieldTypeId == 5){
                                        $OrganizationCustomNumberOfField->selected = $OrganizationUserCustomField->custom_field_value;
                                    }else{
                                        $OrganizationCustomNumberOfField->customFieldValue = $OrganizationUserCustomField->custom_field_value;
                                    }
                                }
                            }else{
                                if($customFieldTypeId == 4){
                                    $OrganizationCustomNumberOfField->checked = '';
                                }else if($customFieldTypeId == 5){
                                    $OrganizationCustomNumberOfField->selected = '';
                                }else{
                                    $OrganizationCustomNumberOfField->customFieldValue = '';
                                }
                            }
                        }
                        
                    }else{
                        $OrganizationUserCustomField = OrganizationCredentialCustomField::where('credential_id',$credentialId)->where('is_active','1')->where('org_id',$credential->organizationId)->where('custom_field_id',$OrganizationCustomField->id);
                        if($OrganizationUserCustomField->count() > 0){
                        // $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                            if($customFieldTypeId == 4){
                                $OrganizationCustomField->checked = $OrganizationUserCustomField->first()->custom_field_value;
                            }else if($customFieldTypeId == 5){
                                $OrganizationCustomField->selected = $OrganizationUserCustomField->first()->custom_field_value;
                            }else{
                                $OrganizationCustomField->customFieldValue = $OrganizationUserCustomField->first()->custom_field_value;
                            }
                        }else{
                            if($customFieldTypeId == 4){
                                $OrganizationCustomField->checked = '';
                            }else if($customFieldTypeId == 5){
                                $OrganizationCustomField->selected = '';
                            }else{
                                $OrganizationCustomField->customFieldValue = '';
                            }
                        }
                    }
                }
            }
            $credential->customFields = $OrganizationCustomFields;
            
            return response()->json(['status'=>true,'code'=>200,'data'=>$credential],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function updateCredential(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'credentialTitle' => 'required',
            'status' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $credential = Credential::where('id',$request->credentialId)
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('credential.org_id',$organizationId);
                }
            });

            if ($credential->count() < 1) {
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }

            $categoryId = '';
            if(!empty($request->category)){
                $explodeCategory = explode(',',$request->category);
                $categoryId = implode(',',$explodeCategory);
            }
            
            $credential->update([
                'credential_title' => $request->credentialTitle,
                'credential_code' => $request->categoryCode,
                'category_id' => $categoryId,
                'credential_note' => $request->credentialNote,
                'credential_description' => $request->credentialDescription,
                'expiration_time' => $request->expirationTime,
                'days_till_expiration' => $request->daysTillExpiration,
                'status' => $request->status,
                'modified_id' => $authId
            ]);

            if(!empty($request->customFields)){
                $customFields = json_decode($request->customFields);
                if(!empty($customFields->text)){
                    foreach($customFields->text as $text){
                        $id = $text->id;
                        $value = isset($text->value) ? $text->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationCredentialCustomField = OrganizationCredentialCustomField::where('custom_field_id',$id)->where('credential_id',$request->credentialId);
                        if($OrganizationCredentialCustomField->count() > 0){
                            $OrganizationCredentialCustomField->update([
                                'custom_number_of_field_id' => '',
                                'custom_field_value' => $value,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationCredentialCustomField = new OrganizationCredentialCustomField;
                            $OrganizationCredentialCustomField->credential_id = $request->credentialId;
                            $OrganizationCredentialCustomField->custom_field_id = $id;
                            $OrganizationCredentialCustomField->custom_number_of_field_id = '';
                            $OrganizationCredentialCustomField->custom_field_value = $value;
                            $OrganizationCredentialCustomField->org_id = $organizationId;
                            $OrganizationCredentialCustomField->created_id = $authId;
                            $OrganizationCredentialCustomField->modified_id = $authId;
                            $OrganizationCredentialCustomField->save();
                        }
                    }
                }
                if(!empty($customFields->radio)){
                    foreach($customFields->radio as $radio){
                        $id = $radio->id;
                        $value = isset($radio->value) ? $radio->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationCredentialCustomField = OrganizationCredentialCustomField::where('custom_field_id',$id)->where('credential_id',$request->credentialId);
                        if($OrganizationCredentialCustomField->count() > 0){
                            $OrganizationCredentialCustomField->update([
                                'custom_number_of_field_id' => $value,
                                'custom_field_value' => 1,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationCredentialCustomField = new OrganizationCredentialCustomField;
                            $OrganizationCredentialCustomField->credential_id = $request->credentialId;
                            $OrganizationCredentialCustomField->custom_field_id = $id;
                            $OrganizationCredentialCustomField->custom_number_of_field_id = $value;
                            $OrganizationCredentialCustomField->custom_field_value = 1;
                            $OrganizationCredentialCustomField->org_id = $organizationId;
                            $OrganizationCredentialCustomField->created_id = $authId;
                            $OrganizationCredentialCustomField->modified_id = $authId;
                            $OrganizationCredentialCustomField->save();
                        }
                    }
                }
            }
            
            return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been updated successfully.'],200);
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function activeCredential(Request $request){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;

            $credential = Credential::where('id',$request->credentialId)
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('credential.org_id',$organizationId);
                }
            });

            if($credential->count() > 0){

                $credential->update([
                    'status' => '1',
                ]);
                return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been actived successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteCredential(Request $request){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;

            $credential = Credential::where('id',$request->credentialId)
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('credential.org_id',$organizationId);
                }
            });

            if($credential->count() > 0){

                $credential->update([
                    'status' => '5',
                ]);
                return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkDeleteCredential(Request $request){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;

            if(!empty($request->credentialIds)){
                $credential = Credential::whereIn('id',$request->credentialIds)
                ->where(function($query) use ($organizationId,$roleId){
                    if($roleId != 1){
                        $query->where('credential.org_id',$organizationId);
                    }
                });

                if($credential->count() > 0){

                    $credential->update([
                        'status' => '5',
                    ]);
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been deleted successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function archiveCredential(Request $request){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;

            $credential = Credential::where('id',$request->credentialId)
            ->where(function($query) use ($organizationId,$roleId){
                if($roleId != 1){
                    $query->where('credential.org_id',$organizationId);
                }
            });

            if($credential->count() > 0){

                $credential->update([
                    'status' => '4',
                ]);
                return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been archived successfully.'],200);
            }else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function bulkArchiveCredential(Request $request){
        try{
            $roleId = Auth::user()->user->role_id;
            $organizationId = Auth::user()->org_id;
            
            if(!empty($request->credentialIds)){
                $credential = Credential::whereIn('id',$request->credentialIds)
                ->where(function($query) use ($organizationId,$roleId){
                    if($roleId != 1){
                        $query->where('credential.org_id',$organizationId);
                    }
                });

                if($credential->count() > 0){

                    $credential->update([
                        'status' => '4',
                    ]);
                }
                return response()->json(['status'=>true,'code'=>200,'message'=>'Credential has been archived successfully.'],200);
            }
            else{
                return response()->json(['status'=>false,'code'=>404,'error'=>'Credential is not found.'], 404);
            }
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }
}
