<?php

namespace App\Http\Controllers\API;

use App\Models\CertificateMaster;
use App\Models\OrganizationCertificate;
use App\Models\Organization;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use DB;

class CertificateMasterController extends BaseController
{
    public function getCertificateList(Request $request){

        $sort = $request->has('sort') ? $request->get('sort') : 'certificate_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'certificateCode'){
            $sortColumn = 'certificate_code';
        }elseif($sort == 'certificateName'){
            $sortColumn = 'certificate_name';
        }elseif($sort == 'description'){
            $sortColumn = 'description';
        }elseif($sort == 'baseLanguage'){
            $sortColumn = 'base_language';
        }elseif($sort == 'certStructure'){
            $sortColumn = 'cert_structure';
        }elseif($sort == 'orientation'){
            $sortColumn = 'orientation';
        }elseif($sort == 'meta'){
            $sortColumn = 'meta';
        }elseif($sort == 'user_release'){
            $sortColumn = 'user_release';
        }elseif($sort == 'isActive'){
            $sortColumn = 'is_active';
        }

        $certificates = CertificateMaster::where('is_active','!=','0')
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('certificate_code', 'LIKE', '%'.$search.'%');
                $query->orWhere('certificate_name', 'LIKE', '%'.$search.'%');
                //$query->orWhere('description', 'LIKE', '%'.$search.'%');
                //$query->where('base_language', 'LIKE', '%'.$search.'%');
                //$query->orWhere('cert_structure', 'LIKE', '%'.$search.'%');
                //$query->orWhere('orientation', 'LIKE', '%'.$search.'%');
                //$query->orWhere('meta', 'LIKE', '%'.$search.'%');
                //$query->orWhere('user_release', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('is_active','2');
                }
            }
        })
        ->select('certificate_id as certificateId', 'certificate_code as certificateCode', 'certificate_name as certificateName', 'description', 'base_language as baseLanguage', 'cert_structure as certStructure', 'orientation', 'bgimage', 'meta', 'user_release as userRelease', 'is_active as isActive')
        ->orderBy($sortColumn,$order)
        ->get();
        foreach($certificates as $certificate){ 
            if($certificate->bgimage != ''){
                $certificate->bgimage = getFileS3Bucket(getPathS3Bucket().'/certificate/'.$certificate->bgimage);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificates],200);
    }

    public function addNewCertificate(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'certificateCode' => 'required|max:255|unique:lms_certificate_master,certificate_code,null,null,is_active,!=0',
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

        $certificate = new CertificateMaster;
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
        $certificate->created_id = $authId;
        $certificate->modified_id = $authId;
        $certificate->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been created successfully.'],200);
    }

    public function getCertificateById($certificateId){
        $certificate = CertificateMaster::where('is_active','!=','0')->where('certificate_id',$certificateId);
        if ($certificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }
        $certificate = $certificate->select('certificate_id as certificateId', 'certificate_code as certificateCode', 'certificate_name as certificateName', 'description', 'base_language as baseLanguage', 'cert_structure as certStructure', 'orientation', 'bgimage', 'meta', 'user_release as userRelease', 'is_active as isActive')->first();
        if($certificate->bgimage != ''){
            $certificate->bgimage = getFileS3Bucket(getPathS3Bucket().'/certificate/'.$certificate->bgimage);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificate],200);

    }

    public function updateCertificateById(Request $request){
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
        
            $certificate = CertificateMaster::where('is_active','!=','0')->where('certificate_id',$request->certificateId);
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

    public function deleteCertificate(Request $request){
        $validator = Validator::make($request->all(), [
            'certificateId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        
        $certificate = CertificateMaster::where('is_active','!=','0')->where('certificate_id',$request->certificateId);
        if ($certificate->count() < 1) {
            return response()->json(['status'=>false,'code'=>404,'error'=>'Certificate is not found.'], 404);
        }


        $certificate->update([ 
            'is_active' => '0'
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been deleted successfully.'],200);
    }

    public function getCertificateOptionList(){
        $certificates = CertificateMaster::where('is_active','1')->select('certificate_id as certificateId', 'certificate_name as certificateName')->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$certificates],200);
    }

    public function certificateAssignToOrgCertificate(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'certificateId' => 'required',
            'organizationIds' => 'required|array',
            'organizationIds.*.organizationId' => 'required|integer',
            'organizationIds.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(is_array($request->organizationIds)){
            
            if(!empty($request->certificateId) && !empty($request->organizationIds)){
                foreach($request->organizationIds as $organization){

                    $organizationId = $organization['organizationId'];
                    $isChecked = $organization['isChecked'];

                    if($isChecked == 1){
                        $organizationCertificate = OrganizationCertificate::where('certificate_master_id',$request->certificateId)->where('org_id',$organizationId);
                        if($organizationCertificate->count() > 0){
                            $organizationCertificate->update([
                                'assigned' => $isChecked
                            ]);
                        }else{

                            $certificateMasters = CertificateMaster::where('certificate_id',$request->certificateId);
                            if($certificateMasters->count() > 0){
                                foreach($certificateMasters->get() as $certificateMaster){

                                    $certificate = new OrganizationCertificate;
                                    $certificate->certificate_master_id = $certificateMaster->certificate_id;
                                    $certificate->certificate_code = $certificateMaster->certificate_code;
                                    $certificate->certificate_name = $certificateMaster->certificate_name;
                                    $certificate->description = $certificateMaster->description;
                                    $certificate->base_language = $certificateMaster->base_language;
                                    $certificate->cert_structure = $certificateMaster->cert_structure;
                                    $certificate->orientation = $certificateMaster->orientation;
                                    $certificate->bgimage = $certificateMaster->bgimage;
                                    $certificate->meta = $certificateMaster->meta;
                                    $certificate->user_release = $certificateMaster->user_release;
                                    $certificate->su_assigned = 1;
                                    $certificate->assigned = $isChecked;
                                    $certificate->is_active = 1;
                                    $certificate->org_id = $organizationId;
                                    $certificate->created_id = $authId;
                                    $certificate->modified_id = $authId;
                                    $certificate->save();

                                }
                            }
                        } 
                    }
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate assigned to organization certificate successfully.'],200);
    }

    public function getCertificateAssignedToOrganizationList($certificateId){
        $organizations = DB::table('lms_org_master as organization')
                ->Join('lms_domain as domain', 'organization.domain_id', '=', 'domain.domain_id')
                ->where('organization.is_active', '1')
                ->orderBy('organization.organization_name', 'ASC')
                ->select('organization.org_id as organizationId', 'organization.organization_name as organizationName', 'domain.domain_name as domainName')
                ->get();
        if($organizations->count() > 0){
            foreach($organizations as $organization){
                $organizationCertificate = OrganizationCertificate::where('certificate_master_id',$certificateId)->where('org_id',$organization->organizationId);
                if($organizationCertificate->count() > 0){
                    $organization->isChecked = $organizationCertificate->first()->assigned;
                }else{
                    $organization->isChecked = 0;
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$organizations],200);
    }

    public function bulkCertificateAssignToOrgCertificate(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'certificateIds' => 'required|array',
            'organizationIds' => 'required|array',
            'organizationIds.*.organizationId' => 'required|integer',
            'organizationIds.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        if(is_array($request->certificateIds) && is_array($request->organizationIds)){
            
            if(!empty($request->certificateIds) && !empty($request->organizationIds)){

                foreach($request->certificateIds as $certificateId){
                    foreach($request->organizationIds as $organization){

                        $organizationId = $organization['organizationId'];
                        $isChecked = $organization['isChecked'];

                        if($isChecked == 1){
                            $organizationCertificate = OrganizationCertificate::where('certificate_master_id',$certificateId)->where('org_id',$organizationId);
                            if($organizationCertificate->count() > 0){
                                $organizationCertificate->update([
                                    'assigned' => $isChecked
                                ]);
                            }else{

                                $certificateMasters = CertificateMaster::where('certificate_id',$certificateId);
                                if($certificateMasters->count() > 0){
                                    foreach($certificateMasters->get() as $certificateMaster){

                                        $certificate = new OrganizationCertificate;
                                        $certificate->certificate_master_id = $certificateMaster->certificate_id;
                                        $certificate->certificate_code = $certificateMaster->certificate_code;
                                        $certificate->certificate_name = $certificateMaster->certificate_name;
                                        $certificate->description = $certificateMaster->description;
                                        $certificate->base_language = $certificateMaster->base_language;
                                        $certificate->cert_structure = $certificateMaster->cert_structure;
                                        $certificate->orientation = $certificateMaster->orientation;
                                        $certificate->bgimage = $certificateMaster->bgimage;
                                        $certificate->meta = $certificateMaster->meta;
                                        $certificate->user_release = $certificateMaster->user_release;
                                        $certificate->su_assigned = 1;
                                        $certificate->assigned = $isChecked;
                                        $certificate->is_active = 1;
                                        $certificate->org_id = $organizationId;
                                        $certificate->created_id = $authId;
                                        $certificate->modified_id = $authId;
                                        $certificate->save();

                                    }
                                }
                            } 
                        }
                    }
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate assigned to organization certificate successfully.'],200);
    }

    public function getCertificateListByOrgId(Request $request,$organizationId){

        $sort = $request->has('sort') ? $request->get('sort') : 'assigned';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $organizationId = ($organizationId == 0) ? $organizationId = Auth::user()->org_id  : $organizationId;

        $sortColumn = $sort;
        $organizationCertificate = OrganizationCertificate::where('is_active','!=','0')
        ->where('su_assigned','=','1')
        ->where(function($query) use ($organizationId){
            //if($organizationId != 0){
                $query->where('org_id',$organizationId);
            //}
        })
        ->orderBy($sortColumn,$order)
        ->select('certificate_id as certificateId', 'certificate_name as certificateName', 'certificate_code as certificateCode', 'assigned as isChecked')
        ->get();

        return response()->json(['status'=>true,'code'=>200,'data'=>$organizationCertificate],200);
    }

    public function bulkUpdateOrgCertificate(Request $request)
    {
        $authId = Auth::user()->user_id;
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'organizationId' => 'required|integer',
            'certificates' => 'required|array',
            'certificates.*.certificateId' => 'required|integer',
            'certificates.*.isChecked' => 'required|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        foreach($request->certificates as $certificate){
            $certificateId = $certificate['certificateId'];
            $isChecked = $certificate['isChecked'];

            $OrganizationCertificate = OrganizationCertificate::where('certificate_id',$certificateId)->where('org_id',$request->organizationId);
            if($OrganizationCertificate->count() > 0){
                $OrganizationCertificate->update([
                    'assigned' => $isChecked
                ]);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'message'=>'Certificate has been updated successfully.'],200);
    }

}
