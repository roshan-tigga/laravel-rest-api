<?php

namespace App\Http\Controllers\API;
use App\Models\CertificateMaster;
use App\Models\OrganizationCertificate;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class OrganizationCertificateController extends BaseController
{
    public function getOrgCertificateList(Request $request){

        $organizationId = Auth::user()->org_id;
        $sort = $request->has('sort') ? $request->get('sort') : 'certificate_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $suAssigned = $request->has('suAssigned') ? $request->get('suAssigned') : '0';

        $sortColumn = $sort;
        $certificates = OrganizationCertificate::where('is_active','!=','0')->where('org_id',$organizationId)
        ->where('su_assigned',$suAssigned)
        ->select('certificate_id as certificateId', 'certificate_code as certificateCode', 'certificate_name as certificateName', 'description', 'base_language as baseLanguage', 'cert_structure as certStructure', 'orientation', 'bgimage', 'meta', 'user_release as userRelease', 'su_assigned as suAssigned','is_modified as isModified', 'is_active as isActive')
        ->orderBy($sortColumn,$order)
        ->get();
        foreach($certificates as $certificate){ 
            if($certificate->bgimage != ''){
                $certificate->bgimage = getFileS3Bucket(getPathS3Bucket().'/certificate/'.$certificate->bgimage);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificates],200);
    }

    public function addNewOrgCertificate(Request $request){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'certificateCode' => 'required|max:255|unique:lms_certificate_master,certificate_code,null,null,is_active,!0',
            'certificateName' => 'required|max:255',
            'description' => 'required',
            //'baseLanguage' => 'required|max:255',
            'certStructure' => 'required',
            'orientation' => 'required|in:P,L',
            'bgImage' => 'required|mimes:jpeg,jpg,png,pdf',
            'meta' => 'required',
            'userRelease' => 'numeric',
            'isActive' => 'integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $bgImage = '';
        if($request->file('bgImage') != ''){
            $path = getPathS3Bucket().'/certificate';
            $s3BgImage = Storage::disk('s3')->put($path, $request->bgImage);
            $bgImage = substr($s3BgImage, strrpos($s3BgImage, '/') + 1);
        }

        $certificate = new OrganizationCertificate;
        $certificate->certificate_code = $request->certificateCode;
        $certificate->certificate_name = $request->certificateName;
        $certificate->description = $request->description;
        $certificate->base_language = $request->baseLanguage;
        $certificate->cert_structure = $request->certStructure;
        $certificate->orientation = $request->orientation;
        $certificate->bgimage = $bgImage;
        $certificate->meta = $request->meta;
        $certificate->user_release = $request->userRelease;
        $certificate->is_active = $request->isActive == '' ? '1' : $request->isActive;
        $certificate->org_id = $organizationId;
        $certificate->created_id = $authId;
        $certificate->modified_id = $authId;
        $certificate->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been created successfully.'],200);
    }

    public function getOrgCertificateById($certificateId){
        $organizationId = Auth::user()->org_id;
        $certificate = OrganizationCertificate::where('is_active','!=','0')->where('org_id',$organizationId)->where('certificate_id',$certificateId);
        if ($certificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }
        $certificate = $certificate->select('certificate_id as certificateId', 'certificate_code as certificateCode', 'certificate_name as certificateName', 'description', 'base_language as baseLanguage', 'cert_structure as certStructure', 'orientation', 'bgimage', 'meta', 'user_release as userRelease', 'is_active as isActive')->first();
         
        if($certificate->bgimage != ''){
            $certificate->bgimage = getFileS3Bucket(getPathS3Bucket().'/certificate/'.$certificate->bgimage);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificate],200);
    }

    public function updateOrgCertificateById(Request $request){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'certificateId'=>'required|integer', 
            //'certificateCode' => 'required|max:255|unique:lms_certificate_master,certificate_code,'.$request->certificateId.',certificate_id,is_active,!=0',
            'certificateName' => 'required|max:255',
            'description' => 'required',
            //'baseLanguage' => 'required|max:255',
            'certStructure' => 'required',
            'orientation' => 'required|in:P,L',
            'bgImage' => 'nullable|mimes:jpeg,jpg,png,pdf',
            'meta' => 'required',
            'userRelease' => 'numeric',
            'isActive' => 'integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        
        $certificate = OrganizationCertificate::where('is_active','!=','0')->where('org_id',$organizationId)->where('certificate_id',$request->certificateId);
        if ($certificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }

        $certificate->update([
            //'certificate_code' => $request->certificateCode,
            'certificate_name' => $request->certificateName,
            'description' => $request->description,
            'base_language' => $request->baseLanguage,
            'cert_structure' => $request->certStructure,
            'orientation' => $request->orientation,
            'meta' => $request->meta,
            'is_modified' => ($certificate->first()->su_assigned == 1) ? 1 : 0,
            'user_release' => $request->userRelease,
            'is_active' => $request->isActive == '' ? $certificate->first()->is_active ? $certificate->first()->is_active : '1' : $request->isActive,
            'modified_id' => $authId
        ]);

        if($request->file('bgImage') != ''){
            $path = getPathS3Bucket().'/certificate';
            $s3BgImage = Storage::disk('s3')->put($path, $request->bgImage);
            $bgImage = substr($s3BgImage, strrpos($s3BgImage, '/') + 1);
            $certificate->update([
                'bgimage' => $bgImage
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been updated successfully.'],200);
    }

    public function deleteOrgCertificate(Request $request){
        $organizationId = Auth::user()->org_id;
        $validator = Validator::make($request->all(), [
            'certificateId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        
        $certificate = OrganizationCertificate::where('is_active','!=','0')->where('org_id',$organizationId)->where('certificate_id',$request->certificateId);
        if ($certificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }

        $certificate->update([ 
            'is_active' => '0'
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been deleted successfully.'],200);
    }

    public function getOrgCertificateOptionList(){
        $certificates = OrganizationCertificate::where('is_active','1')->select('certificate_id as certificateId', 'certificate_name as certificateName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificates],200);
    }

    public function resetOrganizationCertificate(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id; 
        $validator = Validator::make($request->all(), [
            'certificateId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $organizationCertificate = OrganizationCertificate::where('is_active','!=','0')->where('assigned','=','1')->where('certificate_id',$request->certificateId)->where('org_id',$organizationId)->whereNotNull('certificate_master_id');
        if ($organizationCertificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }

        $certificate_id = $organizationCertificate->first()->certificate_id;
        $certificateMaster = CertificateMaster::where('certificate_id',$certificate_id);
        if($certificateMaster->count() > 0){

            $certificateMaster = $certificateMaster->first();

            $organizationCertificate->update([

                'certificate_code' => $certificateMaster->certificate_code,
                'certificate_name' => $certificateMaster->certificate_name,
                'description' => $certificateMaster->description,
                'base_language' => $certificateMaster->base_language,
                'cert_structure' => $certificateMaster->cert_structure,
                'orientation' => $certificateMaster->orientation,
                'bgimage' => $certificateMaster->bgimage,
                'meta' => $certificateMaster->meta,
                'user_release' => $certificateMaster->user_release,
                'is_modified' => 0,
                'modified_id' => $authId,
                'date_modified' => date('Y-m-d H:i:s')
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been reset successfully.'],200);
    } 

}
