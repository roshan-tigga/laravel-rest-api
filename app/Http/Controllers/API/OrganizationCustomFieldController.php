<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationCustomField;
use App\Models\OrganizationCustomNumberOfField;
use App\Models\CustomFieldFor;
use App\Models\CustomFieldType;
use Illuminate\Http\Request;
use Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;


class OrganizationCustomFieldController extends BaseController
{
    public function getCustomFieldForList(Request $request){

        $CustomFieldFor = CustomFieldFor::where('is_active','1')
        ->select('custom_field_for_id as id','custom_field_for as name')
        ->get();
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$CustomFieldFor],200);
    }

    public function getCustomFieldTypeList(Request $request){

        $CustomFieldTypes = CustomFieldType::where('is_active','1')
        ->select('custom_field_type_id as id','custom_field_type as name')
        ->get();
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$CustomFieldTypes],200);
    }

    public function getOrgCustomFieldList(Request $request){
        $authId = Auth::user()->user_id;
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationCustomFields = OrganizationCustomField::
        //leftJoin('lms_org_custom_number_of_fields','lms_org_custom_number_of_fields.custom_field_id','=','lms_org_custom_fields.custom_field_id')
        leftJoin('lms_custom_field_for_master','lms_custom_field_for_master.custom_field_for_id','=','lms_org_custom_fields.custom_field_for_id')
        ->leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->leftJoin('lms_training_types','lms_training_types.training_type_id','=','lms_org_custom_fields.training_type_id')
        ->where('lms_org_custom_fields.is_active','!=','0')
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_custom_field_type_master.custom_field_type as customFieldType','lms_custom_field_for_master.custom_field_for as customFieldFor','lms_org_custom_fields.is_active as isActive')
        ->get();
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationCustomFields],200);
    }

    public function addOrgCustomField(Request $request){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'fieldName' => 'required',
            'customFieldTypeId' => 'required',
            'customFieldForId' => 'required',
            'labelName' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $OrganizationCustomField = new OrganizationCustomField;
        $OrganizationCustomField->field_name = $request->fieldName; 
        $OrganizationCustomField->label_name = $request->labelName; 
        $OrganizationCustomField->custom_field_type_id = $request->customFieldTypeId; 
        $OrganizationCustomField->custom_field_for_id = $request->customFieldForId; 
        $OrganizationCustomField->training_type_id = $request->trainingTypeId;
        $OrganizationCustomField->number_of_fields = $request->numberOfFields;
        $OrganizationCustomField->org_id = $organizationId;
        $OrganizationCustomField->is_active = $request->isActive;
        $OrganizationCustomField->created_id = $authId;
        $OrganizationCustomField->modified_id = $authId;
        $OrganizationCustomField->save(); 

        if(!empty($request->customNumberOfFields)){
            foreach($request->customNumberOfFields as $customNumberOfFields){
                $OrganizationCustomNumberOfField = new OrganizationCustomNumberOfField;
                $OrganizationCustomNumberOfField->custom_field_id = $OrganizationCustomField->custom_field_id; 
                $OrganizationCustomNumberOfField->label_name = @$customNumberOfFields['labelName']; 
                $OrganizationCustomNumberOfField->org_id = $organizationId;
                $OrganizationCustomNumberOfField->created_id = $authId;
                $OrganizationCustomNumberOfField->modified_id = $authId;
                $OrganizationCustomNumberOfField->save(); 
            }
        }

        return response()->json(['status'=>true,'code'=>201,'message'=>'Custom fields has been created successfully.'],201);
    }

    public function getOrgCustomFieldById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationCustomField = OrganizationCustomField::
        leftJoin('lms_custom_field_for_master','lms_custom_field_for_master.custom_field_for_id','=','lms_org_custom_fields.custom_field_for_id')
        ->leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->leftJoin('lms_training_types','lms_training_types.training_type_id','=','lms_org_custom_fields.training_type_id')
        ->where('lms_org_custom_fields.is_active','!=','0')
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName',
        'lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType',
        'lms_org_custom_fields.custom_field_for_id as customFieldForId','lms_custom_field_for_master.custom_field_for as customFieldFor',
        'lms_org_custom_fields.training_type_id as trainingTypeId','lms_training_types.training_type as trainingType',
        'lms_org_custom_fields.number_of_fields as numberOfFields','lms_org_custom_fields.is_active as isActive')
        ->find($id);
        if(is_null($OrganizationCustomField)){
            return response()->json(['status'=>false,'code'=>400,'error'=>'Custom fields not found.'], 400);
        }

        $OrganizationCustomNumberOfField = OrganizationCustomNumberOfField::where('is_active','!=','0')->where('custom_field_id',$OrganizationCustomField->id);
        if($OrganizationCustomNumberOfField->count()){
            $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfField
            ->select('custom_number_of_field_id as id','label_name as labelName','is_active as isActive')
            ->get();
        }else{
            $OrganizationCustomField->customNumberOfFields = Null;
        }


        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationCustomField],200);
    }

    public function updateOrgCustomFieldById(Request $request,$id){

        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'fieldName' => 'required',
            'customFieldTypeId' => 'required',
            'customFieldForId' => 'required',
            'labelName' => 'required',
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }


        $OrganizationCustomField = OrganizationCustomField::where('is_active','!=','0')->where('org_id',$organizationId)->find($id);
        if(is_null($OrganizationCustomField)){
            return response()->json(['status'=>false,'code'=>400,'error'=>'Custom fields not found.'], 400);
        }
        $OrganizationCustomField->field_name = $request->fieldName; 
        $OrganizationCustomField->label_name = $request->labelName; 
        $OrganizationCustomField->custom_field_type_id = $request->customFieldTypeId; 
        $OrganizationCustomField->custom_field_for_id = $request->customFieldForId; 
        $OrganizationCustomField->training_type_id = $request->trainingTypeId;
        $OrganizationCustomField->number_of_fields = $request->numberOfFields;
        $OrganizationCustomField->is_active = $request->isActive;
        $OrganizationCustomField->created_id = $authId;
        $OrganizationCustomField->modified_id = $authId;
        $OrganizationCustomField->save(); 

        if(!empty($request->customNumberOfFields)){
            foreach($request->customNumberOfFields as $customNumberOfFields){
                if(!empty($customNumberOfFields['id'])){
                    $OrganizationCustomNumberOfField = OrganizationCustomNumberOfField::find($customNumberOfFields['id']);
                }else{
                    $OrganizationCustomNumberOfField = new OrganizationCustomNumberOfField;
                    $OrganizationCustomNumberOfField->custom_field_id = $OrganizationCustomField->custom_field_id;
                    $OrganizationCustomNumberOfField->org_id = $organizationId;
                }
                $OrganizationCustomNumberOfField->label_name = @$customNumberOfFields['labelName']; 
                $OrganizationCustomNumberOfField->created_id = $authId;
                $OrganizationCustomNumberOfField->modified_id = $authId;
                $OrganizationCustomNumberOfField->save(); 
            }
        }


        return response()->json(['status'=>true,'code'=>200,'message'=>'Custom fields has been updated successfully.'],200);
    }

    public function deleteOrgCustomFieldById($id){
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $OrganizationCustomField = OrganizationCustomField::where('is_active','!=','0')->where('org_id',$organizationId)->find($id);
        if(is_null($OrganizationCustomField)){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Custom fields not found.'],400);
        }

        $OrganizationCustomField->is_active = 0; 
        $OrganizationCustomField->modified_id = $authId;
        $OrganizationCustomField->save(); 

        return response()->json(['status'=>true,'code'=>200,'message'=>'Custom fields has been deleted successfully.'],200);
    }

    public function getUsersCustomFieldList(){
        $organizationId = Auth::user()->org_id;
        $OrganizationCustomFields = OrganizationCustomField::
        leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->where('lms_org_custom_fields.is_active','1')
        ->where('lms_org_custom_fields.custom_field_for_id',1)
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
        ->get();
        if($OrganizationCustomFields->count() > 0){
            foreach($OrganizationCustomFields as $OrganizationCustomField){
                
                $OrganizationCustomNumberOfField = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)->where('org_id',$organizationId)
                ->select('custom_number_of_field_id as id','label_name as labelName')
                ->get();
                if($OrganizationCustomNumberOfField->count() > 0){
                    $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfField;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationCustomFields],200);
    }

    public function getTrainingCustomFieldList($trainingTypeId){
        $organizationId = Auth::user()->org_id;
        $OrganizationCustomFields = OrganizationCustomField::
        leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->where('lms_org_custom_fields.is_active','1')
        ->where('lms_org_custom_fields.custom_field_for_id',2)
        ->where('lms_org_custom_fields.training_type_id',$trainingTypeId)
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
        ->get();
        if($OrganizationCustomFields->count() > 0){
            foreach($OrganizationCustomFields as $OrganizationCustomField){
                
                $OrganizationCustomNumberOfField = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)->where('org_id',$organizationId)
                ->select('custom_number_of_field_id as id','label_name as labelName')
                ->get();
                if($OrganizationCustomNumberOfField->count() > 0){
                    $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfField;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationCustomFields],200);
    }

    public function getCredentialsCustomFieldList(){
        $organizationId = Auth::user()->org_id;
        $OrganizationCustomFields = OrganizationCustomField::
        leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
        ->where('lms_org_custom_fields.is_active','1')
        ->where('lms_org_custom_fields.custom_field_for_id',3)
        ->where('lms_org_custom_fields.org_id',$organizationId)
        ->select('lms_org_custom_fields.custom_field_id as id','lms_org_custom_fields.field_name as fieldName','lms_org_custom_fields.label_name as labelName','lms_org_custom_fields.custom_field_type_id as customFieldTypeId','lms_custom_field_type_master.custom_field_type as customFieldType')
        ->get();
        if($OrganizationCustomFields->count() > 0){
            foreach($OrganizationCustomFields as $OrganizationCustomField){
                
                $OrganizationCustomNumberOfField = OrganizationCustomNumberOfField::where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)->where('org_id',$organizationId)
                ->select('custom_number_of_field_id as id','label_name as labelName')
                ->get();
                if($OrganizationCustomNumberOfField->count() > 0){
                    $OrganizationCustomField->customNumberOfFields = $OrganizationCustomNumberOfField;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$OrganizationCustomFields],200);
    }
}
