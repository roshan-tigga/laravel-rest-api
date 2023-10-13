<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationTrainingLibrary;
use App\Models\OrganizationTrainingMedia;
use App\Models\OrganizationTrainingHandout;
use App\Models\OrganizationTrainingNotificationSetting;
use App\Models\OrganizationAssessmentSetting;
use App\Models\OrganizationAssessmentQuestion;
use App\Models\OrganizationQuestionAnswer;

use App\Models\Image;
use App\Models\OrganizationResource;
use App\Models\OrganizationMedia;
use App\Models\OrganizationTrainingNotification;
use App\Models\OrganizationCategory;
use App\Models\OrganizationContentLibrary;
use App\Models\ContentType;
use App\Models\Requirement;

use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\OrganizationScoMenisfestReader;
use App\Models\OrganizationScoDetails;
use App\Models\OrganizationScoTrack;
use DOMDocument;

use App\Models\OrganizationTrainingCustomField;
use App\Models\OrganizationCustomField;
use App\Models\OrganizationCustomNumberOfField;
use App\Models\UserCatalogInprogress;
use App\Models\Enrollment;

class CourseCatalogController extends BaseController
{
    public function getCourseCatalogList(Request $request){
        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'trainingLibrary.training_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $trainingType = $request->has('trainingType') ? $request->get('trainingType') : '';
        $category = $request->has('category') ? $request->get('category') : '';
        $status = $request->has('status') ? $request->get('status') : '';


        $sortColumn = $sort;
        if($sort == 'courseCatalogId'){
            $sortColumn = 'trainingLibrary.training_id';
        }elseif($sort == 'courseTitle'){
            $sortColumn = 'trainingLibrary.training_name';
        }elseif($sort == 'trainingType'){
            $sortColumn = 'trainingType.training_type';
        }elseif($sort == 'categoryName'){
            $sortColumn = 'category.category_name';
        }elseif($sort == 'trainingCode'){
            $sortColumn = 'trainingLibrary.training_code';
        }elseif($sort == 'status'){
            $sortColumn = 'trainingStatus.training_status';
        }elseif($sort == 'isActive'){
            $sortColumn = 'trainingLibrary.is_active';
        }

        $courseCatalogs = DB::table('lms_org_training_library as trainingLibrary')
        ->join('lms_training_types as trainingType','trainingLibrary.training_type_id','=','trainingType.training_type_id')
        ->join('lms_training_status as trainingStatus','trainingLibrary.training_status_id','=','trainingStatus.training_status_id')
        //->leftJoin('lms_org_training_media as trainingMedia','trainingLibrary.training_id','=','trainingMedia.training_id')
        //->leftJoin('lms_category_master as category','trainingLibrary.category_id','=','category.category_id')
        ->where('trainingLibrary.is_active','!=','0')
        ->where('trainingLibrary.org_id',$organizationId)
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('trainingLibrary.training_id', 'LIKE', '%'.$search.'%');
                $query->orWhere('trainingLibrary.training_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('trainingType.training_type', 'LIKE', '%'.$search.'%');
                //$query->orWhere('category.category_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('trainingLibrary.training_code', 'LIKE', '%'.$search.'%');
                $query->orWhere('trainingStatus.training_status', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('trainingLibrary.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('trainingLibrary.is_active','2');
                }
            }
        })
        ->where(function($query) use ($trainingType,$category,$status){
            if($trainingType != ''){
                $query->where('trainingLibrary.training_type_id','=',$trainingType);
            }
            if($category != ''){
                //$query->where('trainingLibrary.category_id', '=', $category);
            }
            if($status != ''){
                $query->where('trainingLibrary.training_status_id', '=', $status);
            }
        })
        ->orderBy($sortColumn,$order)
        ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary.content_type as contentTypesId','trainingLibrary.training_name as courseTitle','trainingLibrary.training_code as trainingCode','trainingLibrary.reference_code as referenceCode','trainingType.training_type_id as trainingTypeId','trainingType.training_type as trainingType','trainingStatus.training_status as status', 'trainingLibrary.category_id as categoryName', 'trainingLibrary.is_active as isActive', 'trainingLibrary.is_modified as isModified', 'trainingLibrary.su_assigned as suAssigned','trainingLibrary.student_rating as studentRating','trainingLibrary.date_modified as dateModified')
        ->get()->toArray();
        foreach($courseCatalogs as $courseCatalog){
            if($courseCatalog->trainingTypeId == 1){
                $trainingMedia = DB::table('lms_org_training_media')
                ->join('lms_org_media','lms_org_media.media_id','=','lms_org_training_media.media_id')
                ->leftJoin('lms_sco_menisfest_reader as scorm','lms_org_media.media_id','=','scorm.media_id')
                ->leftJoin('lms_sco_details as scormDetails','scorm.id','=','scormDetails.scorm_id')
                ->where('lms_org_training_media.training_id',$courseCatalog->courseLibraryId)->where('lms_org_training_media.org_id',$organizationId)
                ->orderBy('lms_org_training_media.training_media_id','DESC');
                if($trainingMedia->count() > 0){
                    $trainingMedia = $trainingMedia->select('lms_org_media.media_id as mediaId','lms_org_media.media_name as mediaName','lms_org_media.media_size as mediaSize','lms_org_media.media_type as mediaType','lms_org_media.media_url as mediaUrl','scormDetails.launch')->first();
                    $mediaName = $trainingMedia->mediaName;
                    if($trainingMedia->mediaType == 'zip' || $trainingMedia->mediaType == 'rar'){
                        if($trainingMedia->launch){
                            $mediaUrl = $trainingMedia->mediaUrl; 
                            $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket()).'/media/'.$mediaUrl.'/'.$mediaName.'/'.$trainingMedia->launch;
                        }else{
                            $mediaHref = '';
                            $mediaUrl = $trainingMedia->mediaUrl; 
                            $files = Storage::disk('s3')->allFiles(getPathS3Bucket().'/media/'.$mediaUrl.'/'.$mediaName);
                            foreach($files as $file){
                                $fileName = substr($file, strrpos($file, "/") + 1);
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                if($fileName == 'imsmanifest.xml'){
                                    $fileUrl = getFileS3Bucket($file);
                                    $xmlString = file_get_contents($fileUrl);
                                    $xmlObject = simplexml_load_string($xmlString);
                                    $mediaHref = $xmlObject->resources->resource[0]->attributes()->href;
                                }
                            }
                            $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/').$mediaUrl.'/'.$mediaName.'/'.$mediaHref;
                        }
                     }else if($courseCatalog->contentTypesId == 5 || $courseCatalog->contentTypesId == 8){
                        $trainingMedia->mediaUrl = $trainingMedia->mediaUrl;
                    }else{
                         $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$trainingMedia->mediaUrl);
                    }
                    $courseCatalog->trainingMedia = $trainingMedia;
                }
            }


            if(!empty($courseCatalog->categoryName)){
                $categoryId = explode(',',$courseCatalog->categoryName);
                $courseCatalog->categoryName = OrganizationCategory::whereIn('category_id',$categoryId)->pluck('category_name');
            }else{
                $courseCatalog->categoryName = [];
            }
            $courseCatalog->assignId = "";
        }

        return response()->json(['status'=>true,'code'=>200,'data'=>$courseCatalogs],200);
    }

    function trainingCode(){
        $organization = OrganizationTrainingLibrary::count();
        if($organization > 0){
            $course_code = OrganizationTrainingLibrary::orderBy('training_code','DESC')->select('training_code')->first()->training_code;
            $code = $course_code+1;
        }else{
            $code = '110001';
        }
        return $code;
    }

    public function addCourseCatalog(Request $request){
        
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $trainingType = $request->trainingType;
        $roleId = Auth::user()->user->role_id;
        
        if($trainingType == 1 || $trainingType == 2 || $trainingType == ''){
            $validator = Validator::make($request->all(), [
                'trainingType' => 'required|integer',
                'courseTitle' => 'required|max:150',
                'trainingContent' => 'nullable',
                'courseImage' => 'nullable|mimes:jpeg,jpg,png',
                'handout' => 'nullable|mimes:jpeg,jpg,png,pdf,zip',
                'video' => 'nullable',
                'credit' => 'nullable',
                'creditVisibility' => 'nullable|integer',
                'point' => 'nullable',
                'pointVisibility' => 'nullable|integer',
                'category' => 'nullable',
                'certificate' => 'nullable|integer',
                'passingScore' => 'nullable',
                'sslForAicc' => 'nullable|integer',
                'isActive' => 'nullable|integer',
                'status' => 'required|integer'
            ]);
        }
        if($trainingType == 3){
            $validator = Validator::make($request->all(), [
                'trainingType' => 'required|integer',
                'courseTitle' => 'required|max:150',
                'trainingContent' => 'nullable',
                'courseImage' => 'nullable|mimes:jpeg,jpg,png',
                'handout' => 'nullable|mimes:jpeg,jpg,png,pdf,zip',
                'credit' => 'nullable',
                'creditVisibility' => 'nullable|integer',
                'point' => 'nullable',
                'pointVisibility' => 'nullable|integer',
                'category' => 'nullable',
                'certificate' => 'nullable|integer',
                'isActive' => 'nullable|integer',
                'status' => 'required|integer',

                'requirePassingcore' => 'nullable|integer',
                'passingPercentage' => 'required|integer',
                'randomizeQuestion' => 'nullable|integer',
                'displayQuestion' => 'nullable',
                'hideAfterCompleted' => 'nullable|integer',
                'attempt' => 'nullable',
                'learnerCanViewResult' => 'nullable|integer',
                'postQuizAction' => 'nullable|integer',
                'timerOn' => 'nullable|integer',
                'hours' => 'required',
                'minutes' => 'required'
            ]);
        }


        if($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            $imageId = Null;
            $courseImage = $imageName = $imageType = $imageSize = '';
            if($request->file('courseImage') != ''){
                $path = getPathS3Bucket().'/courses';
                $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                $imageSize = $request->file('courseImage')->getSize();
                $imageType = $request->file('courseImage')->extension();
                $imageName = $request->file('courseImage')->getClientOriginalName();

                $image = new Image;
                $image->image_name = $imageName;
                $image->image_size = $imageSize;
                $image->image_type = $imageType;
                $image->image_url = $courseImage;
                $image->org_id = $organizationId;
                $image->is_active = 1;
                $image->user_id = $authId;
                $image->created_id = $authId;
                $image->date_created = Carbon::now();
                $image->save();
                $imageId = $image->image_id;
            }

            

            $categoryId = '';
            if(!empty($request->category)){
                $explodeCategory = explode(',',$request->category);
                $categoryId = implode(',',$explodeCategory);
            }

            $trainingLibrary = new OrganizationTrainingLibrary;
            $trainingLibrary->training_type_id = $request->trainingType;
            $trainingLibrary->training_name = $request->courseTitle;
            $trainingLibrary->training_code = $this->trainingCode();
            $trainingLibrary->reference_code = $request->referenceCode;
            
            $trainingLibrary->description = $request->description;
            $trainingLibrary->content_type = $request->trainingContent;

            $trainingLibrary->image_id = $imageId;

            $trainingLibrary->credits = $request->credit;
            $trainingLibrary->credits_visible = $request->creditVisibility;
            $trainingLibrary->points = $request->point;
            $trainingLibrary->points_visible = $request->pointVisibility;
            $trainingLibrary->category_id = $categoryId;
            $trainingLibrary->certificate_id = $request->certificate;
            $trainingLibrary->training_status_id = $request->status;

            if($trainingType == 1){
                $trainingLibrary->passing_score = $request->passingScore;
                $trainingLibrary->ssl_on_off = $request->sslForAicc;

                $trainingLibrary->hours = $request->hours;
                $trainingLibrary->minutes = $request->minutes;
                $trainingLibrary->expiration_length = $request->expirationLength;
                $trainingLibrary->expiration_time = $request->expirationTime;
            }

            if($trainingType == 2){
                $trainingLibrary->ilt_enrollment_id = $request->iltAssessment;
                $trainingLibrary->unenrollment = $request->unenrollment;
                $trainingLibrary->activity_reviews = $request->activityReviews;

                $trainingLibrary->hours = $request->hours;
                $trainingLibrary->minutes = $request->minutes;
                $trainingLibrary->expiration_length = $request->expirationLength;
                $trainingLibrary->expiration_time = $request->expirationTime;
            }
            if($trainingType == 3){
                $trainingLibrary->quiz_type = $request->quizType;
            }

            $trainingLibrary->is_enrolled = $request->isEnrolled;
            $trainingLibrary->course_type = $request->courseType;

            $trainingLibrary->is_active = $request->isActive == '' ? '1' : $request->isActive;
            $trainingLibrary->org_id = $organizationId;
            $trainingLibrary->created_id = $authId;
            $trainingLibrary->modified_id = $authId;
            $trainingLibrary->save();
            

            if($trainingType == 1){
                if($trainingLibrary->training_id != ''){
                    if($request->trainingContent == 5 || $request->trainingContent == 8){
                        $mediaUrl = $request->video;
                        $mediaName = $mediaUrl;
                        $mediaType = '';
                        if($request->trainingContent == 5){
                            $mediaType = 'Embedded Code';
                        }
                        if($request->trainingContent == 8){
                            $mediaType = 'Link(URL)';
                        }
    
                        $media = new OrganizationMedia;
                        $media->media_name = $mediaName;
                        $media->media_url = $mediaUrl;
                        $media->media_type = $mediaType;
                        $media->org_id = $organizationId;
                        $media->created_id = $authId;
                        $media->modified_id = $authId;
                        $media->save();
    
                        if($media->media_id != ''){
    
                            $contentLibrary = new OrganizationContentLibrary;
                            $contentLibrary->content_name = $request->courseTitle;
                            $contentLibrary->content_version = '1.0';
                            $contentLibrary->content_types_id = $request->trainingContent;
                            $contentLibrary->media_id = $media->media_id;
                            $contentLibrary->org_id = $organizationId; 
                            $contentLibrary->created_id = $authId;
                            $contentLibrary->modified_id = $authId;
                            $contentLibrary->save();
    
                            $trainingMedia = new OrganizationTrainingMedia;
                            $trainingMedia->training_id = $trainingLibrary->training_id;
                            $trainingMedia->media_id = $media->media_id;
                            $trainingMedia->org_id = $organizationId;
                            $trainingMedia->is_active = 1;
                            $trainingMedia->save();
                        }
                    }else{
                        if($request->file('video') != ''){
                            $mediaSize = $request->file('video')->getSize();
                            $mediaType = $request->file('video')->extension();
                            $mediaName = $request->file('video')->getClientOriginalName();

                            $mediaFileName = substr($mediaName, 0, strrpos($mediaName, '.')); 
                            $mediaFileName = str_replace(' ','_',$mediaFileName);

                            if($request->trainingContent == 3){
                                $zipFileName = time().Str::random(16);
                                $zipFileNameWithExtension = $zipFileName.'.'.$mediaType;
                                $mediaUrl = $zipFileName;
                                $mediaName = $mediaFileName;
                            }else{
                                $mediaUrl = fileUploadS3Bucket($request->video,'media');
                            }

                            $media = new OrganizationMedia;
                            $media->media_name = $mediaName;
                            $media->media_url = $mediaUrl;
                            $media->media_size = $mediaSize;
                            $media->media_type = $mediaType;
                            $media->org_id = $organizationId;
                            $media->modified_id = $authId;
                            $media->save();

                            if($media->media_id != ''){

                                if($request->file('video') && $request->trainingContent == 3){
                                    fileUploadS3Bucket($request->file('video'),'media','s3',$request,$zipFileName);
                                    $zip = new \ZipArchive();
                                    if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
                                        //$zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));
                
                                        $stream = $zip->getStream('imsmanifest.xml');
                                        $contents = '';
                                        while (!feof($stream)) {
                                            $contents .= fread($stream, 2);
                                        }
                                        fclose($stream);
                                        $dom = new \DOMDocument();
                
                                        if($dom->loadXML($contents)) {
                
                                            $manifest = $dom->getElementsByTagName('manifest')->item(0);
                                            $version = @$manifest->attributes->getNamedItem('version')->nodeValue;
                                            $manifestIdentifier = @$manifest->attributes->getNamedItem('identifier')->nodeValue;
                
                                            $organization = $dom->getElementsByTagName('organization')->item(0);
                                            $title = @$organization->getElementsByTagName('title')->item(0)->textContent;
                
                                            $resource = $dom->getElementsByTagName('resource')->item(0);
                                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
                
                
                                            $scoMenisfestReader = new OrganizationScoMenisfestReader;
                                            $scoMenisfestReader->course_id = '';
                                            $scoMenisfestReader->media_id = $media->media_id;
                                            $scoMenisfestReader->name = $title;
                                            $scoMenisfestReader->scormtype = $scormType;
                                            $scoMenisfestReader->reference = $identifier;
                                            $scoMenisfestReader->version = $version;
                                            $scoMenisfestReader->created_id = $authId;
                                            $scoMenisfestReader->modified_id = $authId;
                                            $scoMenisfestReader->save();
                
                                            
                
                                            $items = $dom->getElementsByTagName('item');
                                            $resources = $dom->getElementsByTagName('resource');
                                            if ($items->length > 0) {
                                                foreach ($items as $item) {
                
                                                    $identifierref = @$item->attributes->getNamedItem('identifierref')->nodeValue;
                                                    $title = @$item->getElementsByTagName('title')->item(0)->textContent;
                
                                                    if ($resources->length > 0) {
                                                        foreach ($resources as $k => $resource) {
                                                            $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                                            $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
                                                            $launch = @$resource->attributes->getNamedItem('href')->nodeValue;
                
                                                            if ($identifierref == $identifier) {
                                                                $scoDetails = new OrganizationScoDetails;
                                                                $scoDetails->scorm_id = $scoMenisfestReader->id;
                                                                $scoDetails->manifest = $manifestIdentifier;
                                                                $scoDetails->identifier = $identifier;
                                                                $scoDetails->launch = $launch;
                                                                $scoDetails->scormtype = $scormType;
                                                                $scoDetails->title = $title;
                                                                $scoDetails->organization_id = $organizationId;
                                                                //$scoDetails->parent_organization_id = '';
                                                                $scoDetails->sortorder = $k;
                                                                $scoDetails->created_id = $authId;
                                                                $scoDetails->modified_id = $authId;
                                                                $scoDetails->save();
                                                            }
                
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                        $zip->close();
                
                                        // $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
                                        // foreach ($files as $k => $file) {
                                        //     $dirname = pathinfo($file)['dirname'];
                                        //     $basename = pathinfo($file)['basename'];
                                        //     $explode = explode($zipFileName, $dirname);
                                        //     scormFileUpload(file_get_contents($dirname . '/' . $basename),'/media/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename);
                                        // }
                
                                        \File::deleteDirectory(Storage::disk('public')->path('media/'.$zipFileName));
                                        \File::delete(Storage::disk('public')->path('media/'.$zipFileNameWithExtension));
                                    }
                                }

                                $contentLibrary = new OrganizationContentLibrary;
                                $contentLibrary->content_name = $request->courseTitle;
                                $contentLibrary->content_version = '1.0';
                                $contentLibrary->content_types_id = $request->trainingContent;
                                $contentLibrary->media_id = $media->media_id;
                                $contentLibrary->org_id = $organizationId; 
                                $contentLibrary->created_id = $authId;
                                $contentLibrary->modified_id = $authId;
                                $contentLibrary->save();

                                $trainingMedia = new OrganizationTrainingMedia;
                                $trainingMedia->training_id = $trainingLibrary->training_id;
                                $trainingMedia->media_id = $media->media_id;
                                $trainingMedia->org_id = $organizationId;
                                $trainingMedia->is_active = 1;
                                $trainingMedia->save();
                            }

                        }else{
                            if(!empty($request->media)){
                                //foreach(json_decode($request->media) as $mediaId){
                                    $trainingMedia = new OrganizationTrainingMedia;
                                    $trainingMedia->training_id = $trainingLibrary->training_id;
                                    $trainingMedia->media_id = $request->media;
                                    $trainingMedia->org_id = $organizationId;
                                    $trainingMedia->is_active = 1;
                                    $trainingMedia->save();

                                    if($request->trainingContent == 3){
                                        $scoMenisfestReaders = OrganizationScoMenisfestReader::where('is_active','1')->where('course_id','0')->where('media_id',$request->media)->where('org_id',$organizationId);
                                        if($scoMenisfestReaders->count() > 0){
                                            foreach($scoMenisfestReaders->get() as $scoMenisfestReader){

                                                $OrganizationScoMenisfestReader = new OrganizationScoMenisfestReader;
                                                $OrganizationScoMenisfestReader->course_id = $trainingLibrary->training_id;
                                                $OrganizationScoMenisfestReader->media_id = $request->media;
                                                $OrganizationScoMenisfestReader->name = $scoMenisfestReader->name;
                                                $OrganizationScoMenisfestReader->scormtype = $scoMenisfestReader->scormType;
                                                $OrganizationScoMenisfestReader->reference = $scoMenisfestReader->reference;
                                                $OrganizationScoMenisfestReader->version = $scoMenisfestReader->version;
                                                $OrganizationScoMenisfestReader->org_id = $organizationId;
                                                $OrganizationScoMenisfestReader->created_id = $authId;
                                                $OrganizationScoMenisfestReader->modified_id = $authId;
                                                $OrganizationScoMenisfestReader->save();

                                                $scoDetails = OrganizationScoDetails::where('is_active','1')->where('scorm_id',$OrganizationScoMenisfestReader->id)->where('organization_id',$organizationId);
                                                if($scoDetails->count() > 0){
                                                    foreach($scoDetails->get() as $scoDetail){
                                                        $OrganizationScoDetails = new OrganizationScoDetails;
                                                        $OrganizationScoDetails->scorm_id = $OrganizationScoMenisfestReader->id;
                                                        $OrganizationScoDetails->manifest = $scoDetail->manifest;
                                                        $OrganizationScoDetails->identifier = $scoDetail->identifier;
                                                        $OrganizationScoDetails->launch = $scoDetail->launch;
                                                        $OrganizationScoDetails->scormtype = $scoDetail->scormType;
                                                        $OrganizationScoDetails->title = $scoDetail->title;
                                                        $OrganizationScoDetails->organization_id = $organizationId;
                                                        //$OrganizationScoDetails->parent_organization_id = '';
                                                        $OrganizationScoDetails->sortorder = $scoDetail->sortorder;;
                                                        $OrganizationScoDetails->created_id = $authId;
                                                        $OrganizationScoDetails->modified_id = $authId;
                                                        $OrganizationScoDetails->save();
                                                    }
                                                }
                                            }
                                        }
                                    }
                                //}
                            }
                        }
                    }
                }
            }
            elseif($trainingType == 2){
                if($trainingLibrary->training_id != ''){
                    if(!empty($request->notifications)){
                        $notificationsData = [];
                        foreach(json_decode($request->notifications) as $notification){
                            $notificationsData[] = [
                                'training_notification_id'=> $notification->trainingNotificationId,
                                'training_id'=> $trainingLibrary->training_id,
                                'notification_on'=> $notification->notificationOn,
                                'org_id' => $organizationId,
                                'is_active'=> 1
                            ];
                        } 

                        if(!empty($notificationsData)){
                            OrganizationTrainingNotificationSetting::insert($notificationsData);
                        }
                    }
                }
            }
            elseif($trainingType == 3){
                if($trainingLibrary->training_id != ''){
                    $assessmentSetting = new OrganizationAssessmentSetting;
                    $assessmentSetting->training_id = $trainingLibrary->training_id;
                    $assessmentSetting->require_passing_score = $request->requirePassingcore == 1 ? 1 : 0;
                    $assessmentSetting->passing_percentage = $request->passingPercentage;
                    $assessmentSetting->randomize_questions = $request->randomizeQuestion == 1 ? 1 : 0;
                    $assessmentSetting->display_type = $request->displayQuestion;

                    $assessmentSetting->hide_after_completed = $request->hideAfterCompleted == 1 ? 1 : 0;
                    $assessmentSetting->attempt_count = $request->attempt;
                    $assessmentSetting->learner_can_view_result = $request->learnerCanViewResult == 1 ? 1 : 0;
                    
                    if($request->postQuizAction == 1){
                        $assessmentSetting->pass_fail_status = true;
                        $assessmentSetting->total_score = true;
                        $assessmentSetting->correct_incorrect_marked = false;
                        $assessmentSetting->correct_incorrect_ans_marked = false;
                    }else{
                        $assessmentSetting->pass_fail_status = false;
                        $assessmentSetting->total_score = false;
                        $assessmentSetting->correct_incorrect_marked = true;
                        $assessmentSetting->correct_incorrect_ans_marked = true;
                    }
                    
                    $assessmentSetting->timer_on = $request->timerOn;
                    $assessmentSetting->hrs = $request->hours;
                    $assessmentSetting->mins = $request->minutes;
                    $assessmentSetting->org_id = $organizationId;
                    $assessmentSetting->is_active = 1;
                    $assessmentSetting->save();


                    if(!empty($request->questionAnswer)){
                        foreach(json_decode($request->questionAnswer) as $questionAnswer){
                            $assessmentQuestion = new OrganizationAssessmentQuestion;
                            $assessmentQuestion->training_id = $trainingLibrary->training_id;
                            $assessmentQuestion->question_type_id = $questionAnswer->questionType;
                            $assessmentQuestion->question = $questionAnswer->questionText;
                            $assessmentQuestion->question_score = $questionAnswer->questionScore;
                            $assessmentQuestion->org_id = $organizationId;
                            if($request->questionType == 1 || $request->questionType == 2){
                                $assessmentQuestion->show_ans_random = isset($questionAnswer->randomizeAnswer) ? $questionAnswer->randomizeAnswer == 1 ? 1 : 0 : 0;
                            }else{
                                $assessmentQuestion->show_ans_random = 0;
                            }
                            $assessmentQuestion->is_active = 1;
                            $assessmentQuestion->save();

                            if($assessmentQuestion->question_id != ''){
                                if(!empty($questionAnswer->answer)){
                                    foreach($questionAnswer->answer as $optionsAnswer){
                                        $answer = new OrganizationQuestionAnswer;
                                        $answer->question_id = $assessmentQuestion->question_id;
                                        $answer->org_id = $organizationId;

                                        if($questionAnswer->questionType == 1 || $questionAnswer->questionType == 2 || $questionAnswer->questionType == 3 || $questionAnswer->questionType == 4){
                                            $answer->options = $optionsAnswer->label;
                                            $answer->is_correct = $optionsAnswer->value;
                                        }else{
                                            if($questionAnswer->questionType == 5){
                                                $answer->text_box = $optionsAnswer->value;
                                            }
                                            if($questionAnswer->questionType == 6){
                                                $answer->numberic_ans = $optionsAnswer->value;
                                            }
                                            if($questionAnswer->questionType == 7){
                                                $answer->text_ans = $optionsAnswer->value;
                                            }
                                        }

                                        $answer->is_active = 1;
                                        $answer->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
            else{
                return response()->json(['status'=>false,'code'=>400,'error'=>'Course not create.'],400);
            }

            $handoutUrl = $handoutSize = $handoutType = $handoutName = '';
            $handouts = $request->file('handouts');
            if(!empty($handouts)){
                foreach($handouts as $handout){
                    $path = getPathS3Bucket().'/handouts';
                    $s3Handout = Storage::disk('s3')->put($path, $handout);
                    $handoutUrl = substr($s3Handout, strrpos($s3Handout, '/') + 1);
                    $handoutSize = $handout->getSize();
                    $handoutType = $handout->extension();
                    $handoutName = $handout->getClientOriginalName();

                    $resource = new OrganizationResource;
                    $resource->resource_name = $handoutName;
                    $resource->resource_size = $handoutSize;
                    $resource->resource_type = $handoutType;
                    $resource->resource_url = $handoutUrl;
                    $resource->org_id = $organizationId;
                    $resource->is_active = 1;
                    $resource->user_id = $authId;
                    $resource->created_id = $authId;
                    $resource->date_created = Carbon::now();
                    $resource->save();

                    if($resource->resource_id != ''){
                        $trainingHandout = new OrganizationTrainingHandout;
                        $trainingHandout->training_id = $trainingLibrary->training_id;
                        $trainingHandout->resource_id = $resource->resource_id;
                        $trainingHandout->is_active = 1;
                        $trainingHandout->save();
                    }
                }
            }

            if($request->isEnrolled == 1){
                DB::table('lms_org_assignment_user_course')->insert([
                    'users' => $authId,
                    'category' => $categoryId,
                    'courses' => $trainingLibrary->training_id,
                    'date_created' => date('Y-m-d H:i:s'),
                    'date_modified' => date('Y-m-d H:i:s'),
                    'created_id' => $authId,
                    'modified_id' => $authId,
                    'org_id' => $organizationId
                ]);
            }
            if($request->courseType == 1){
                $Requirement = new Requirement;
                $Requirement->training_id = $trainingLibrary->training_id;
                $Requirement->user_id = $authId;
                $Requirement->org_id = $organizationId;
                $Requirement->role_id = $roleId;
                $Requirement->save();
            }

            if(!empty($request->customFields)){
                    
                $customFields = json_decode($request->customFields);

                if(!empty($customFields->text)){
                    foreach($customFields->text as $text){
                        $id = $text->id;
                        $value = isset($text->value) ? $text->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationTrainingCustomField = new OrganizationTrainingCustomField;
                        $OrganizationTrainingCustomField->training_id = $trainingLibrary->training_id;
                        $OrganizationTrainingCustomField->custom_field_id = $id;
                        //$OrganizationTrainingCustomField->custom_number_of_field_id = '';
                        $OrganizationTrainingCustomField->custom_field_value = $value;
                        $OrganizationTrainingCustomField->org_id = $organizationId;
                        $OrganizationTrainingCustomField->created_id = $authId;
                        $OrganizationTrainingCustomField->modified_id = $authId;
                        $OrganizationTrainingCustomField->save();
                    }
                }
                if(!empty($customFields->radio)){
                    foreach($customFields->radio as $radio){
                        $id = $radio->id;
                        $value = isset($radio->value) ? $radio->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationTrainingCustomField = new OrganizationTrainingCustomField;
                        $OrganizationTrainingCustomField->training_id = $trainingLibrary->training_id;
                        $OrganizationTrainingCustomField->custom_field_id = $id;
                        $OrganizationTrainingCustomField->custom_number_of_field_id = $value;
                        $OrganizationTrainingCustomField->custom_field_value = 1;
                        $OrganizationTrainingCustomField->org_id = $organizationId;
                        $OrganizationTrainingCustomField->created_id = $authId;
                        $OrganizationTrainingCustomField->modified_id = $authId;
                        $OrganizationTrainingCustomField->save();
                    }
                }
            }

            //if($trainingType == 1){
                $userCatalogInprogress = new UserCatalogInprogress;
                $userCatalogInprogress->user_id = $authId;
                $userCatalogInprogress->org_id = $organizationId;
                $userCatalogInprogress->created_id = $authId;
                $userCatalogInprogress->modified_id = $authId;
                $userCatalogInprogress->course_id = $trainingLibrary->training_id;
                $userCatalogInprogress->save();
            //}
            if($trainingType == 2){
                $enrollment = new Enrollment;
                $enrollment->training_id = $trainingLibrary->training_id;
                $enrollment->org_id = $organizationId;
                $enrollment->user_id = $authId;
                $enrollment->created_id = $authId;
                $enrollment->modified_id = $authId;
                $enrollment->save();
            }

            return response()->json(['status'=>true,'code'=>201,'message'=>'Course has been created successfully.'],201);
        
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    } 
    
    public function getCourseCatalogById($courseLibraryId){

         $organizationId = Auth::user()->org_id;
         $courseLibrary = DB::table('lms_org_training_library as trainingLibrary')
         ->leftJoin('lms_training_types as trainingTypes','trainingLibrary.training_type_id','=','trainingTypes.training_type_id')
         ->leftJoin('lms_image as image','trainingLibrary.image_id','=','image.image_id')
         ->leftJoin('lms_certificate_master as certificate','trainingLibrary.certificate_id','=','certificate.certificate_id')
         ->leftJoin('lms_ilt_enrollment as iltEnrollment','trainingLibrary.ilt_enrollment_id','=','iltEnrollment.ilt_enrollment_id')
         ->leftJoin('lms_training_status as trainingStatus','trainingLibrary.training_status_id','=','trainingStatus.training_status_id')
         ->leftJoin('lms_content_types as contentType','trainingLibrary.content_type','=','contentType.content_types_id')
         
         //->leftJoin('lms_org_training_handouts as handouts','trainingLibrary.training_id','=','handouts.training_id')
         //->leftJoin('lms_org_resources as resources','handouts.resource_id','=','resources.resource_id')
 
         //->leftJoin('lms_category_master as category','trainingLibrary.category_id','=','category.category_id')
 
         //->leftJoin('lms_training_notifications_settings as trainingNotificationsSettings','trainingLibrary.training_id','=','trainingNotificationsSettings.training_id')
         //->leftJoin('lms_training_notifications as trainingNotifications','trainingNotificationsSettings.training_notification_id','=','trainingNotifications.training_notification_id')
 
         ->leftJoin('lms_org_assessment_settings as assessmentSettings','trainingLibrary.training_id','=','assessmentSettings.training_id')
         
         ->select('trainingLibrary.training_id as trainingId', 'trainingLibrary.training_type_id as trainingTypeId', 'trainingTypes.training_type as trainingType', 'trainingLibrary.training_name as courseTitle','trainingLibrary.training_code as trainingCode', 'trainingLibrary.reference_code as referenceCode','trainingLibrary.description', 'trainingLibrary.content_type as contentTypesId', 'contentType.content_type as contentType', 'trainingLibrary.image_id as imageId','image.image_url as imageUrl', 'trainingLibrary.credits', 'trainingLibrary.credits_visible as creditsVisible', 'trainingLibrary.points', 'trainingLibrary.points_visible as pointVisibility', 'trainingLibrary.certificate_id as certificateId', 'certificate.certificate_name as certificateName', 'trainingLibrary.ilt_enrollment_id as iltEnrollmentId', 'iltEnrollment.enrollment_type as enrollmentType','trainingLibrary.activity_reviews as activityReviews','trainingLibrary.unenrollment', 'trainingLibrary.training_status_id as trainingStatusId', 'trainingStatus.training_status as trainingStatus', 'trainingLibrary.is_active as isActive', 
         'trainingLibrary.category_id as category','trainingLibrary.passing_score as passingScore','trainingLibrary.ssl_on_off as sslOnOff','trainingLibrary.is_enrolled as isEnrolled','trainingLibrary.course_type as courseType','trainingLibrary.student_rating as studentRating','trainingLibrary.hours','trainingLibrary.minutes','trainingLibrary.expiration_length as expirationLength','trainingLibrary.expiration_time as expirationTime',
         //'resources.resource_url as handoutUrl',
         'trainingLibrary.quiz_type as quizType','trainingLibrary.org_id as organizationId',
         //'trainingNotificationsSettings.training_notification_id as trainingNotificationId', 'trainingNotifications.notification_name as notificationName', 'trainingNotificationsSettings.notification_on as notificationOn',
         'assessmentSettings.assessment_setting_id as assessmentSettingId', 'assessmentSettings.require_passing_score as requirePassingScore', 'assessmentSettings.passing_percentage as passingPercentage', 'assessmentSettings.randomize_questions as randomizeQuestion', 'assessmentSettings.display_type as displayType', 'assessmentSettings.hide_after_completed as hideAfterCompleted', 'assessmentSettings.attempt_count as attemptCount', 'assessmentSettings.learner_can_view_result as learnerCanViewResult','assessmentSettings.post_quiz_action as postQuizAction', 'assessmentSettings.pass_fail_status as passFailStatus', 'assessmentSettings.total_score as totalScore', 'assessmentSettings.correct_incorrect_marked as correctIncorrectMarked', 'assessmentSettings.correct_incorrect_ans_marked as correctIncorrectAnsMarked', 'assessmentSettings.timer_on as timerOn', 'assessmentSettings.hrs as hours', 'assessmentSettings.mins as minutes'
         )
         ->where('trainingLibrary.training_id',$courseLibraryId)
         ->first();
 
         if(isset($courseLibrary->category)){
             $categoryId = explode(',',$courseLibrary->category);
             $courseLibrary->category = OrganizationCategory::whereIn('category_id',$categoryId)->select('category_id as categoryId','category_name as categoryName')->get();
         }
 
         if(DB::table('lms_org_training_library')->where('training_id',$courseLibraryId)->count() > 0){
             if($courseLibrary->imageUrl != ''){
                 $courseLibrary->imageUrl = getFileS3Bucket(getPathS3Bucket().'/courses/'.$courseLibrary->imageUrl);
             }


            $handoutsUrl = [];
            $handouts = DB::table('lms_org_training_handouts as handouts')->join('lms_org_resources as resources','resources.resource_id','=','handouts.resource_id')
            ->where('handouts.is_active',1)
            ->where('resources.is_active',1)
            ->where('handouts.training_id',$courseLibraryId)
            ->select('resources.resource_name as resourceName','resources.resource_url as handoutUrl','resources.resource_type as resourceType')->get();
            if($handouts->count() > 0){
                foreach($handouts as $handout){
                    if($handout->handoutUrl != ''){
                        $handoutsUrl[] = [
                            'name' => $handout->resourceName,
                            'blob' => getFileS3Bucket(getPathS3Bucket().'/handouts/'.$handout->handoutUrl),
                            'format' => $handout->resourceType
                        ];
                    }
                }
            }

            $courseLibrary->handouts = $handoutsUrl;
 
             if($courseLibrary->trainingTypeId == 1)
             {
                 if($courseLibrary->trainingId != ''){ 
                     $data = $dataAll = []; 
 
                     $trainingMedias = DB::table('lms_org_training_media as trainingMedia')
                     ->leftjoin('lms_org_media as media','trainingMedia.media_id','=','media.media_id')
                     
                     ->leftJoin('lms_org_sco_menisfest_reader as scorm','media.media_id','=','scorm.media_id')
                     ->leftJoin('lms_org_sco_details as scormDetails','scorm.id','=','scormDetails.scorm_id')
 
                     ->where('trainingMedia.training_id',$courseLibrary->trainingId)
                     ->orderBy('trainingMedia.training_media_id','DESC')
                     ->select('trainingMedia.training_media_id as trainingMediaId','trainingMedia.media_id as mediaId','media.media_url as mediaUrl','media.media_type as mediaType','media.media_name as mediaName','media.media_size as mediaSize','trainingMedia.is_active as checked','scorm.id as scormId','scorm.version as scormVersion','scormDetails.launch')
                     ->get();
 
                     if($trainingMedias->count() > 0){
                         foreach($trainingMedias as $trainingMedia){

                            $mediaName = $trainingMedia->mediaName;

                            if($courseLibrary->contentTypesId == 3){
                                if($trainingMedia->launch){
                                    $mediaUrl = $trainingMedia->mediaUrl; 
                                    $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket()).'/media/'.$mediaUrl.'/'.$mediaName.'/'.$trainingMedia->launch;
                                }
                             }else if($courseLibrary->contentTypesId == 5 || $courseLibrary->contentTypesId == 8){
                                $trainingMedia->mediaUrl = $trainingMedia->mediaUrl;
                            }else{
                                 $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$trainingMedia->mediaUrl);
                             }
                         }
                     }
 
                     
 
                     $courseLibrary->trainingMedia = $trainingMedias;
                 }
             }
 
             if($courseLibrary->trainingTypeId == 2)
             {
                 $courseLibrary->zoomLink = 'https://us05web.zoom.us/j/82661750254?pwd=Z3BzTmg0TU9NWUVZZlJQWXhCcEdCZz09';
                 if($courseLibrary->trainingId != ''){
                     $notifications = DB::table('lms_org_training_notifications_settings as trainingNotificationsSettings')
                     ->where('training_id',$courseLibrary->trainingId)
                     ->where('is_active',1)
                     ->select('trainingNotificationsSettings.training_notification_id as trainingNotificationId', 'trainingNotificationsSettings.notification_on as notificationOn')
                     ->get();
                     $courseLibrary->notifications = $notifications;
                 }
             }
 
             if($courseLibrary->trainingTypeId == 3)
             {
                 if($courseLibrary->trainingId != ''){
                     $questions = DB::table('lms_org_assessment_question as assessmentQuestion')
                     //->Join('lms_question_answer as questionAnswer','assessmentQuestion.question_id','=','questionAnswer.question_id')
                     ->join('lms_question_types as questionTypes','assessmentQuestion.question_type_id','=','questionTypes.question_type_id')
                     ->where('assessmentQuestion.training_id',$courseLibrary->trainingId)
                     ->select('assessmentQuestion.question_id as questionId', 'assessmentQuestion.question_type_id as questionTypeId', 'questionTypes.question_type as questionType', 'assessmentQuestion.question as questionText','assessmentQuestion.question_score as questionScore', 'assessmentQuestion.show_ans_random as randomizeAnswer','assessmentQuestion.number_of_options as numberOfOption'
                     
                     //'questionAnswer.answer_id as answerId', 'questionAnswer.options', 'questionAnswer.is_correct as isCorrect', 'questionAnswer.text_ans as textAns', 'questionAnswer.numberic_ans as numbericAns', 'questionAnswer.text_box as textBox'
                     )
                     ->get();
                     if($questions->count() > 0){
                         foreach($questions as $question){
                            $answers = DB::table('lms_org_question_answer')->where('question_id',$question->questionId)->get();
                            $answerData = $answersData = [];
                            if($answers->count() > 0){
                                 foreach($answers as $answer){
                                     $answerData['answerId'] = $answer->answer_id;
                                     if($question->questionTypeId == 1 || $question->questionTypeId == 2 || $question->questionTypeId == 3 || $question->questionTypeId == 4){
                                         $answerData['label'] = $answer->options;
                                         $answerData['value'] = $answer->is_correct;
                                     }else{
                                         if($question->questionTypeId == 5){
                                             $answerData['label'] = null;
                                             $answerData['value'] = $answer->text_box;
                                         }
                                         if($question->questionTypeId == 6){
                                             $answerData['label'] = null;
                                             $answerData['value'] = $answer->numberic_ans;
                                         }
                                         if($question->questionTypeId == 7){
                                             $answerData['label'] = null;
                                             $answerData['value'] = $answer->text_ans;
                                         }
                                     }
                                     $answersData[] = $answerData;
                                 }
                             }
                            $question->answer = $answersData;
                         }
                     }
                     $courseLibrary->questionAnswer = $questions;
                 }
             }

             $OrganizationCustomFields = OrganizationCustomField::
            leftJoin('lms_custom_field_type_master','lms_custom_field_type_master.custom_field_type_id','=','lms_org_custom_fields.custom_field_type_id')
            ->where('lms_org_custom_fields.is_active','1')
            ->where('lms_org_custom_fields.custom_field_for_id',2)
            ->where('lms_org_custom_fields.training_type_id',$courseLibrary->trainingTypeId)
            ->where('lms_org_custom_fields.org_id',$courseLibrary->organizationId)
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
                            $OrganizationUserCustomFields = OrganizationTrainingCustomField::where('training_id',$courseLibraryId)->where('org_id',$courseLibrary->organizationId)->where('is_active','1')->where('custom_field_id',$OrganizationCustomField->id)->where('custom_number_of_field_id',$OrganizationCustomNumberOfField->id)->get();
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
                        $OrganizationUserCustomField = OrganizationTrainingCustomField::where('training_id',$courseLibraryId)->where('is_active','1')->where('org_id',$courseLibrary->organizationId)->where('custom_field_id',$OrganizationCustomField->id);
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
            $courseLibrary->customFields = $OrganizationCustomFields;
             return response()->json(['status'=>true,'code'=>200,'data'=>$courseLibrary],200);
         }else{
             return response()->json(['status'=>false,'code'=>404,'error'=>'Course is not found.'],404);
         }
    }


    public function updateCourseCatalog(Request $request){
        $roleId = Auth::user()->user->role_id;
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $trainingType = $request->trainingType;
        if($trainingType == 1 || $trainingType == 2  || $trainingType == ''){
            $validator = Validator::make($request->all(), [
                'trainingType' => 'required|integer',
                'courseTitle' => 'required|max:150',
                'trainingContent' => 'nullable',
                'courseImage' => 'nullable|mimes:jpeg,jpg,png',
                'handout' => 'nullable|mimes:jpeg,jpg,png,pdf,zip',
                'video' => 'nullable',
                'credit' => 'nullable',
                'creditVisibility' => 'nullable|integer',
                'point' => 'nullable',
                'pointVisibility' => 'nullable|integer',
                'category' => 'nullable',
                'certificate' => 'nullable|integer',
                'passingScore' => 'nullable',
                'sslForAicc' => 'nullable|integer',
                'isActive' => 'nullable|integer',
                'status' => 'required|integer'
            ]);
            if ($validator->fails())
            {
                return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
            }
        }
        if($trainingType == 3){
            $validator = Validator::make($request->all(), [
                'trainingType' => 'required|integer',
                'courseTitle' => 'required|max:150',
                'trainingContent' => 'nullable',
                'courseImage' => 'nullable|mimes:jpeg,jpg,png',
                'handout' => 'nullable|mimes:jpeg,jpg,png,pdf,zip',
                'credit' => 'nullable',
                'creditVisibility' => 'nullable|integer',
                'point' => 'nullable',
                'pointVisibility' => 'nullable|integer',
                'category' => 'nullable',
                'certificate' => 'nullable|integer',
                'isActive' => 'nullable|integer',
                'status' => 'required|integer',

                'requirePassingcore' => 'nullable|integer',
                'passingPercentage' => 'required|integer',
                'randomizeQuestion' => 'nullable|integer',
                'displayQuestion' => 'nullable',
                'hideAfterCompleted' => 'nullable|integer',
                'attempt' => 'nullable',
                'learnerCanViewResult' => 'nullable|integer',
                'postQuizAction' => 'nullable|integer',
                'timerOn' => 'nullable|integer',
                'hours' => 'required',
                'minutes' => 'required'
            ]);

            if ($validator->fails())
            {
                return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
            }
        }

        try{
            $trainingLibrary = OrganizationTrainingLibrary::where('is_active','!=','0')->where('org_id',$organizationId)->find($request->trainingId);
            if (is_null($trainingLibrary)) {
                return response()->json(['status'=>false,'code'=>400,'error'=>'Course is not found.'], 400);        
            }

            $imageId = Null;
            $courseImage = $imageName = $imageType = $imageSize = '';
            if($request->file('courseImage') != ''){
                $path = getPathS3Bucket().'/courses';
                $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                $imageSize = $request->file('courseImage')->getSize();
                $imageType = $request->file('courseImage')->extension();
                $imageName = $request->file('courseImage')->getClientOriginalName();

                if($trainingLibrary->image_id == ''){
                    $image = new Image;
                    $image->image_name = $imageName;
                    $image->image_size = $imageSize;
                    $image->image_type = $imageType;
                    $image->image_url = $courseImage;
                    $image->org_id = $organizationId;
                    $image->is_active = 1;
                    $image->user_id = $authId;
                    $image->created_id = $authId;
                    $image->date_created = Carbon::now();
                    $image->save();
                    $imageId = $image->image_id;
                }else{
                    $image = Image::find($trainingLibrary->image_id);
                    $image->image_name = $imageName;
                    $image->image_size = $imageSize;
                    $image->image_type = $imageType;
                    $image->image_url = $courseImage;
                    $image->save();
                    $imageId = $trainingLibrary->image_id;
                }
            }

            $categoryId = '';
            if(!empty($request->category)){
                $explodeCategory = explode(',',$request->category);
                $categoryId = implode(',',$explodeCategory);
            }

            $organizationTrainingLibrary = OrganizationTrainingLibrary::where('training_id',$request->trainingId);
            if($organizationTrainingLibrary->count() > 0){

                $training_library_id = $organizationTrainingLibrary->first()->training_library_id;
                if(!empty($training_library_id)){

                    $organizationTrainingLibrary->update([
                        'is_modified' => 1
                    ]);
                }

            }


            $trainingLibrary->training_name = $request->courseTitle;
            $trainingLibrary->description = $request->description;
            $trainingLibrary->content_type = $request->trainingContent;
            $trainingLibrary->reference_code = $request->referenceCode;
            $trainingLibrary->credits = $request->credit;
            $trainingLibrary->credits_visible = $request->creditVisibility;
            $trainingLibrary->points = $request->point;
            $trainingLibrary->points_visible = $request->pointVisibility;
            $trainingLibrary->category_id = $categoryId;
            $trainingLibrary->passing_score = $request->passingScore;
            $trainingLibrary->ssl_on_off = $request->sslForAicc;
            $trainingLibrary->certificate_id = $request->certificate;
            $trainingLibrary->training_status_id = $request->status;
            $trainingLibrary->is_enrolled = $request->isEnrolled;
            $trainingLibrary->course_type = $request->courseType;

            $trainingLibrary->hours = $request->hours;
            $trainingLibrary->minutes = $request->minutes;
            $trainingLibrary->expiration_length = $request->expirationLength;
            $trainingLibrary->expiration_time = $request->expirationTime;

            if($imageId != ''){
                $trainingLibrary->image_id = $imageId;
            }
            $trainingLibrary->modified_id = $authId;
            $trainingLibrary->save();
           
            if($trainingType == 1){
                if($request->trainingContent == 5 || $request->trainingContent == 8){
                    $mediaUrl = $request->mediaUrl;
                    $mediaName = $mediaUrl;
                    $mediaType = '';
                    if($request->trainingContent == 5){
                        $mediaType = 'Embedded Code';
                    }
                    if($request->trainingContent == 8){
                        $mediaType = 'Link(URL)';
                    }

                    $media = new OrganizationMedia;
                    $media->media_name = $mediaName;
                    $media->media_url = $mediaUrl;
                    $media->media_type = $mediaType;
                    $media->org_id = $organizationId;
                    $media->created_id = $authId;
                    $media->modified_id = $authId;
                    $media->save();

                    if($media->media_id != ''){

                        $contentVersion = '1.0';
                        $contentId = '';
                        $trainingMedia = OrganizationTrainingMedia::where('training_id',$request->trainingId)->where('org_id',$organizationId);
                        if($trainingMedia->count() > 0){
                            $mediaId = $trainingMedia->first()->media_id;
                            $contentLibrary = OrganizationContentLibrary::where('media_id',$mediaId)->where('org_id',$organizationId);
                            if($contentLibrary->count() > 0){

                                $contentLibrary = $contentLibrary->first();
                                $contentId = $contentLibrary->content_id;
                                $contentVersion = $contentLibrary->content_version + 0.1;

                                $contentLibrary = OrganizationContentLibrary::where('parent_content_id',$contentId)->where('org_id',$organizationId)->orderBy('content_version','DESC');
                                if($contentLibrary->count() > 0){
                                    $contentVersion = $contentLibrary->first()->content_version + 0.1;
                                }
                            }

                            $contentLibrary = new OrganizationContentLibrary;
                            $contentLibrary->content_name = $request->courseTitle;
                            $contentLibrary->content_version = $contentVersion;
                            $contentLibrary->content_types_id = $request->trainingContent;
                            $contentLibrary->parent_content_id = $contentId;
                            $contentLibrary->media_id = $media->media_id;
                            $contentLibrary->org_id = $organizationId; 
                            $contentLibrary->created_id = $authId;
                            $contentLibrary->modified_id = $authId;
                            $contentLibrary->save();

                            $trainingMedia = new OrganizationTrainingMedia;
                            $trainingMedia->training_id = $request->trainingId;
                            $trainingMedia->media_id = $media->media_id;
                            $trainingMedia->org_id = $organizationId;
                            $trainingMedia->is_active = 1;
                            $trainingMedia->save();

                        }else{
                            $contentLibrary = new OrganizationContentLibrary;
                            $contentLibrary->content_name = $request->courseTitle;
                            $contentLibrary->content_version = '1.0';
                            $contentLibrary->content_types_id = $request->trainingContent;
                            $contentLibrary->media_id = $media->media_id;
                            $contentLibrary->org_id = $organizationId; 
                            $contentLibrary->created_id = $authId;
                            $contentLibrary->modified_id = $authId;
                            $contentLibrary->save();

                            $trainingMedia = new OrganizationTrainingMedia;
                            $trainingMedia->training_id = $request->training_id;
                            $trainingMedia->media_id = $media->media_id;
                            $trainingMedia->org_id = $organizationId;
                            $trainingMedia->is_active = 1;
                            $trainingMedia->save();
                        }

                        // $contentLibrary = new OrganizationContentLibrary;
                        // $contentLibrary->content_name = $request->courseTitle;
                        // $contentLibrary->content_version = '1.0';
                        // $contentLibrary->content_types_id = $request->trainingContent;
                        // $contentLibrary->media_id = $media->media_id;
                        // $contentLibrary->org_id = $organizationId; 
                        // $contentLibrary->created_id = $authId;
                        // $contentLibrary->modified_id = $authId;
                        // $contentLibrary->save();

                        // $trainingMedia = new OrganizationTrainingMedia;
                        // $trainingMedia->training_id = $request->trainingId;
                        // $trainingMedia->media_id = $media->media_id;
                        // $trainingMedia->org_id = $organizationId;
                        // $trainingMedia->is_active = 1;
                        // $trainingMedia->save();
                    }
                }else{
                    if($request->file('video') != ''){
                        $mediaSize = $request->file('video')->getSize();
                        $mediaType = $request->file('video')->extension();
                        $mediaName = $request->file('video')->getClientOriginalName();

                        $mediaFileName = substr($mediaName, 0, strrpos($mediaName, '.')); 
                        $mediaFileName = str_replace(' ','_',$mediaFileName);

                        if($request->trainingContent == 3){
                            $zipFileName = time().Str::random(16);
                            $zipFileNameWithExtension = $zipFileName.'.'.$mediaType;
                            $mediaUrl = $zipFileName;
                            $mediaName = $mediaFileName;
                        }else{
                            $mediaUrl = fileUploadS3Bucket($request->video,'media');
                        }

                        $media = new OrganizationMedia;
                        $media->media_name = $mediaName;
                        $media->media_url = $mediaUrl;
                        $media->media_size = $mediaSize;
                        $media->media_type = $mediaType;
                        $media->org_id = $organizationId;
                        $media->modified_id = $authId;
                        $media->save();

                        if($media->media_id != ''){

                            if($request->file('video') && $request->trainingContent == 3){
                                fileUploadS3Bucket($request->file('video'),'media','s3',$request,$zipFileName);
                                $zip = new \ZipArchive();
                                if ($zip->open(Storage::disk('public')->path('media/'.$zipFileNameWithExtension), \ZipArchive::CREATE) === TRUE) {
                                    //$zip->extractTo(Storage::disk('public')->path('media/'.$zipFileName));
            
                                    $stream = $zip->getStream('imsmanifest.xml');
                                    $contents = '';
                                    while (!feof($stream)) {
                                        $contents .= fread($stream, 2);
                                    }
                                    fclose($stream);
                                    $dom = new \DOMDocument();
            
                                    if($dom->loadXML($contents)) {
            
                                        $manifest = $dom->getElementsByTagName('manifest')->item(0);
                                        $version = @$manifest->attributes->getNamedItem('version')->nodeValue;
                                        $manifestIdentifier = @$manifest->attributes->getNamedItem('identifier')->nodeValue;
            
                                        $organization = $dom->getElementsByTagName('organization')->item(0);
                                        $title = @$organization->getElementsByTagName('title')->item(0)->textContent;
            
                                        $resource = $dom->getElementsByTagName('resource')->item(0);
                                        $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                        $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
            
            
                                        $scoMenisfestReader = new OrganizationScoMenisfestReader;
                                        $scoMenisfestReader->course_id = '';
                                        $scoMenisfestReader->media_id = $media->media_id;
                                        $scoMenisfestReader->name = $title;
                                        $scoMenisfestReader->scormtype = $scormType;
                                        $scoMenisfestReader->reference = $identifier;
                                        $scoMenisfestReader->version = $version;
                                        $scoMenisfestReader->created_id = $authId;
                                        $scoMenisfestReader->modified_id = $authId;
                                        $scoMenisfestReader->save();
            
                                        
            
                                        $items = $dom->getElementsByTagName('item');
                                        $resources = $dom->getElementsByTagName('resource');
                                        if ($items->length > 0) {
                                            foreach ($items as $item) {
            
                                                $identifierref = @$item->attributes->getNamedItem('identifierref')->nodeValue;
                                                $title = @$item->getElementsByTagName('title')->item(0)->textContent;
            
                                                if ($resources->length > 0) {
                                                    foreach ($resources as $k => $resource) {
                                                        $identifier = @$resource->attributes->getNamedItem('identifier')->nodeValue;
                                                        $scormType = @$resource->attributes->getNamedItem('scormType')->nodeValue;
                                                        $launch = @$resource->attributes->getNamedItem('href')->nodeValue;
            
                                                        if ($identifierref == $identifier) {
                                                            $scoDetails = new OrganizationScoDetails;
                                                            $scoDetails->scorm_id = $scoMenisfestReader->id;
                                                            $scoDetails->manifest = $manifestIdentifier;
                                                            $scoDetails->identifier = $identifier;
                                                            $scoDetails->launch = $launch;
                                                            $scoDetails->scormtype = $scormType;
                                                            $scoDetails->title = $title;
                                                            $scoDetails->organization_id = $organizationId;
                                                            //$scoDetails->parent_organization_id = '';
                                                            $scoDetails->sortorder = $k;
                                                            $scoDetails->created_id = $authId;
                                                            $scoDetails->modified_id = $authId;
                                                            $scoDetails->save();
                                                        }
            
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    $zip->close();
            
                                    // $files = \File::allFiles(Storage::disk('public')->path('/media/'.$zipFileName));
                                    // foreach ($files as $k => $file) {
                                    //     $dirname = pathinfo($file)['dirname'];
                                    //     $basename = pathinfo($file)['basename'];
                                    //     $explode = explode($zipFileName, $dirname);
                                    //     scormFileUpload(file_get_contents($dirname . '/' . $basename),'/media/' . $zipFileName . '/' . $mediaFileName . $explode[1] . '/' . $basename);
                                    // }
            
                                    \File::deleteDirectory(Storage::disk('public')->path('media/'.$zipFileName));
                                    \File::delete(Storage::disk('public')->path('media/'.$zipFileNameWithExtension));
                                }
                            }

                            $contentVersion = '1.0';
                            $contentId = '';
                            $trainingMedia = OrganizationTrainingMedia::where('training_id',$request->trainingId)->where('org_id',$organizationId);
                            if($trainingMedia->count() > 0){
                                $mediaId = $trainingMedia->first()->media_id;
                                $contentLibrary = OrganizationContentLibrary::where('media_id',$mediaId)->where('org_id',$organizationId);
                                if($contentLibrary->count() > 0){

                                    $contentLibrary = $contentLibrary->first();
                                    $contentId = $contentLibrary->content_id;
                                    $contentVersion = $contentLibrary->content_version + 0.1;

                                    $contentLibrary = OrganizationContentLibrary::where('parent_content_id',$contentId)->where('org_id',$organizationId)->orderBy('content_version','DESC');
                                    if($contentLibrary->count() > 0){
                                        $contentVersion = $contentLibrary->first()->content_version + 0.1;
                                    }
                                }

                                $contentLibrary = new OrganizationContentLibrary;
                                $contentLibrary->content_name = $request->courseTitle;
                                $contentLibrary->content_version = $contentVersion;
                                $contentLibrary->content_types_id = $request->trainingContent;
                                $contentLibrary->parent_content_id = $contentId;
                                $contentLibrary->media_id = $media->media_id;
                                $contentLibrary->org_id = $organizationId; 
                                $contentLibrary->created_id = $authId;
                                $contentLibrary->modified_id = $authId;
                                $contentLibrary->save();

                                $trainingMedia = new OrganizationTrainingMedia;
                                $trainingMedia->training_id = $request->trainingId;
                                $trainingMedia->media_id = $media->media_id;
                                $trainingMedia->org_id = $organizationId;
                                $trainingMedia->is_active = 1;
                                $trainingMedia->save();

                            }else{
                                $contentLibrary = new OrganizationContentLibrary;
                                $contentLibrary->content_name = $request->courseTitle;
                                $contentLibrary->content_version = '1.0';
                                $contentLibrary->content_types_id = $request->trainingContent;
                                $contentLibrary->media_id = $media->media_id;
                                $contentLibrary->org_id = $organizationId; 
                                $contentLibrary->created_id = $authId;
                                $contentLibrary->modified_id = $authId;
                                $contentLibrary->save();

                                $trainingMedia = new OrganizationTrainingMedia;
                                $trainingMedia->training_id = $request->training_id;
                                $trainingMedia->media_id = $media->media_id;
                                $trainingMedia->org_id = $organizationId;
                                $trainingMedia->is_active = 1;
                                $trainingMedia->save();
                            }

                            // $contentLibrary = new OrganizationContentLibrary;
                            // $contentLibrary->content_name = $request->courseTitle;
                            // $contentLibrary->content_version = '1.0';
                            // $contentLibrary->content_types_id = $request->trainingContent;
                            // $contentLibrary->media_id = $media->media_id;
                            // $contentLibrary->org_id = $organizationId; 
                            // $contentLibrary->created_id = $authId;
                            // $contentLibrary->modified_id = $authId;
                            // $contentLibrary->save();

                            // $trainingMedia = new OrganizationTrainingMedia;
                            // $trainingMedia->training_id = $request->trainingId;
                            // $trainingMedia->media_id = $media->media_id;
                            // $trainingMedia->org_id = $organizationId;
                            // $trainingMedia->is_active = 1;
                            // $trainingMedia->save();
                        }

                    }else{
                        // if(!empty($request->media)){
                        //     foreach(json_decode($request->media) as $media){

                        //         $trainingMedia = OrganizationTrainingMedia::where('media_id',$media->mediaId)->where('training_id',$request->trainingId);
                        //         if($trainingMedia->count() > 0){
                        //             $trainingMedia->update([
                        //                 'is_active'=>$media->checked ? $media->checked : 0,
                        //                 'passing_score'=>$request->passingScore,
                        //                 'ssl_on_off'=>$request->sslForAicc,
                        //             ]);
                        //         }else{
                        //             $trainingMedia = new OrganizationTrainingMedia;
                        //             $trainingMedia->training_id = $request->trainingId;
                        //             $trainingMedia->media_id = $media->mediaId;
                        //             $trainingMedia->is_active = $media->checked ? $media->checked : 0;
                        //             $trainingMedia->org_id = $organizationId;
                        //             $trainingMedia->save();
                        //         }
                        //     }
                        // }
                    }
                }
            }
            else if($trainingType == 2){
                $trainingLibrary = OrganizationTrainingLibrary::where('training_id',$request->trainingId)->update([
                    'ilt_enrollment_id' => $request->iltAssessment,
                    'unenrollment' => $request->unenrollment,
                    'activity_reviews' => $request->activityReviews
                ]);

                if(!empty($request->notifications)){
                    $notificationsData = [];
                    foreach(json_decode($request->notifications) as $notification){
                        OrganizationTrainingNotificationSetting::where('training_id',$request->trainingId)->where('training_notification_id',$notification->trainingNotificationId)->update([
                            'notification_on'=> $notification->notificationOn
                        ]);
                    } 
                    
                }
            }
            else if($trainingType == 3){

                $trainingLibrary = OrganizationTrainingLibrary::where('training_id',$request->trainingId)->update([
                    'quiz_type' => $request->quizType
                ]);

                if($request->postQuizAction == 1){
                    $pass_fail_status = true;
                    $total_score = true;
                    $correct_incorrect_marked = false;
                    $correct_incorrect_ans_marked = false;
                }else{
                    $pass_fail_status = false;
                    $total_score = false;
                    $correct_incorrect_marked = true;
                    $correct_incorrect_ans_marked = true;
                }
                OrganizationAssessmentSetting::where('training_id',$request->trainingId)->where('assessment_setting_id',$request->assessmentSettingId)->update([
                    'require_passing_score' => $request->requirePassingcore == 1 ? 1 : 0,
                    'passing_percentage' => $request->passingPercentage,
                    'randomize_questions' => $request->randomizeQuestion == 1 ? 1 : 0,
                    'display_type' => $request->displayQuestion,

                    'hide_after_completed' => $request->hideAfterCompleted == 1 ? 1 : 0,
                    'attempt_count' => $request->attempt,
                    'learner_can_view_result' => $request->learnerCanViewResult == 1 ? 1 : 0,

                    'timer_on' => $request->timerOn,
                    'hrs' => $request->hours,
                    'mins' => $request->minutes,

                    
                    'post_quiz_action' => $request->postQuizAction,

                    'pass_fail_status' => $pass_fail_status,
                    'total_score' => $total_score,
                    'correct_incorrect_marked' => $correct_incorrect_marked,
                    'correct_incorrect_ans_marked' => $correct_incorrect_ans_marked,
                ]);
            


                if(!empty($request->questionAnswer)){
                    foreach(json_decode($request->questionAnswer) as $questionAnswer){ 

                        if(isset($questionAnswer->questionId)){

                            if($questionAnswer->questionType == 1 || $questionAnswer->questionType == 2){
                                $show_ans_random = isset($questionAnswer->randomizeAnswer) ? $questionAnswer->randomizeAnswer == 1 ? 1 : 0 : 0;
                            }else{
                                $show_ans_random = 0;
                            }

                            OrganizationAssessmentQuestion::where('question_id',$questionAnswer->questionId)
                            ->where('training_id',$request->trainingId)
                            ->update([
                                'question_type_id' => $questionAnswer->questionType,
                                'question' => $questionAnswer->questionText,
                                'show_ans_random' => $show_ans_random,
                                'question_score' => $questionAnswer->questionScore
                            ]);

                            if(!empty($questionAnswer->answer)){
                                foreach($questionAnswer->answer as $optionsAnswer){

                                    if($questionAnswer->questionType == 1 || $questionAnswer->questionType == 2 || $questionAnswer->questionType == 3 || $questionAnswer->questionType == 4){
                                        OrganizationQuestionAnswer::where('answer_id',$optionsAnswer->answerId)->where('question_id',$questionAnswer->questionId)->update([
                                            'options' => $optionsAnswer->label,
                                            'is_correct' => $optionsAnswer->value
                                        ]);
                                    }else{
                                        if($questionAnswer->questionType == 5){
                                            OrganizationQuestionAnswer::where('answer_id',$optionsAnswer->answerId)->where('question_id',$questionAnswer->questionId)->update([
                                                'text_box' => $optionsAnswer->value
                                            ]);
                                        }
                                        if($questionAnswer->questionType == 6){
                                            OrganizationQuestionAnswer::where('answer_id',$optionsAnswer->answerId)->where('question_id',$questionAnswer->questionId)->update([
                                                'numberic_ans' => $optionsAnswer->value
                                            ]);
                                        }
                                        if($questionAnswer->questionType == 7){
                                            OrganizationQuestionAnswer::where('answer_id',$optionsAnswer->answerId)->where('question_id',$questionAnswer->questionId)->update([
                                                'text_ans' => $optionsAnswer->value
                                            ]);
                                        }
                                    }
                                }
                            }
                        }else{
                            $assessmentQuestion = new OrganizationAssessmentQuestion;
                            $assessmentQuestion->training_id = $request->trainingId;
                            $assessmentQuestion->question_type_id = $questionAnswer->questionType;
                            $assessmentQuestion->question = $questionAnswer->questionText;
                            $assessmentQuestion->org_id = $organizationId;
                            $assessmentQuestion->question_score = $questionAnswer->questionScore;

                            if($questionAnswer->questionType == 1 || $questionAnswer->questionType == 2){
                                $assessmentQuestion->show_ans_random = isset($questionAnswer->randomizeAnswer) ? $questionAnswer->randomizeAnswer == 1 ? 1 : 0 : 0;
                            }else{
                                $assessmentQuestion->show_ans_random = 0;
                            } 

                            $assessmentQuestion->is_active = 1;
                            $assessmentQuestion->save();

                            if($assessmentQuestion->question_id != ''){
                                if(!empty($questionAnswer->answer)){
                                    foreach($questionAnswer->answer as $optionsAnswer){
                                        $answer = new OrganizationQuestionAnswer;
                                        $answer->question_id = $assessmentQuestion->question_id;

                                        if($questionAnswer->questionType == 1 || $questionAnswer->questionType == 2 || $questionAnswer->questionType == 3 || $questionAnswer->questionType == 4){
                                            $answer->options = $optionsAnswer->label;
                                            $answer->is_correct = $optionsAnswer->value;
                                        }else{
                                            if($questionAnswer->questionType == 5){
                                                $answer->text_box = $optionsAnswer->value;
                                            }
                                            if($questionAnswer->questionType == 6){
                                                $answer->numberic_ans = $optionsAnswer->value;
                                            }
                                            if($questionAnswer->questionType == 7){
                                                $answer->text_ans = $optionsAnswer->value;
                                            }
                                        }

                                        $answer->is_active = 1;
                                        $answer->save();
                                    }
                                }
                            }
                        }
                    }
                }                    
                
            }else{
                return response()->json(['status'=>false,'code'=>400,'error'=>'Course not update.'],400);
            }

            $handoutUrl = $handoutSize = $handoutType = $handoutName = '';
            $handouts = $request->file('handouts');
            if(!empty($handouts)){
                foreach($handouts as $handout){
                    $path = getPathS3Bucket().'/handouts';
                    $s3Handout = Storage::disk('s3')->put($path, $handout);
                    $handoutUrl = substr($s3Handout, strrpos($s3Handout, '/') + 1);
                    $handoutSize = $handout->getSize();
                    $handoutType = $handout->extension();
                    $handoutName = $handout->getClientOriginalName();

                    $resource = new OrganizationResource;
                    $resource->resource_name = $handoutName;
                    $resource->resource_size = $handoutSize;
                    $resource->resource_type = $handoutType;
                    $resource->resource_url = $handoutUrl;
                    $resource->org_id = $organizationId;
                    $resource->is_active = 1;
                    $resource->user_id = $authId;
                    $resource->created_id = $authId;
                    $resource->date_created = Carbon::now();
                    $resource->save();

                    if($resource->resource_id != ''){
                        $trainingHandout = new OrganizationTrainingHandout;
                        $trainingHandout->training_id = $request->trainingId;
                        $trainingHandout->resource_id = $resource->resource_id;
                        $trainingHandout->is_active = 1;
                        $trainingHandout->save();
                    }
                }
            }

            if($request->isEnrolled == 1){

                $check = DB::table('lms_org_assignment_user_course')->where('users',$authId)->where('courses',$request->trainingId)->where('org_id',$organizationId);
                if($check->count() > 0){

                }else{
                    DB::table('lms_org_assignment_user_course')->insert([
                        'users' => $authId,
                        //'category' => $categoryId,
                        'courses' => $request->trainingId,
                        'date_created' => date('Y-m-d H:i:s'),
                        'date_modified' => date('Y-m-d H:i:s'),
                        'created_id' => $authId,
                        'modified_id' => $authId,
                        'org_id' => $organizationId
                    ]);
                }
            }

            if($request->courseType == 1){
                $check = Requirement::where('user_id',$authId)->where('training_id',$request->trainingId)->where('org_id',$organizationId);
                if($check->count() > 0){

                }else{
                    $Requirement = new Requirement;
                    $Requirement->training_id = $request->trainingId;
                    $Requirement->user_id = $authId;
                    $Requirement->org_id = $organizationId;
                    $Requirement->role_id = $roleId;
                    $Requirement->save();
                }
            }

            if(!empty($request->customFields)){
                $customFields = json_decode($request->customFields);
                if(!empty($customFields->text)){
                    foreach($customFields->text as $text){
                        $id = $text->id;
                        $value = isset($text->value) ? $text->value : '';
                        if (strtotime($value) !== false) {
                            $value = date('Y-m-d',strtotime($value));
                        }

                        $OrganizationTrainingCustomField = OrganizationTrainingCustomField::where('custom_field_id',$id)->where('training_id',$request->trainingId);
                        if($OrganizationTrainingCustomField->count() > 0){
                            $OrganizationTrainingCustomField->update([
                                'custom_number_of_field_id' => '',
                                'custom_field_value' => $value,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationTrainingCustomField = new OrganizationTrainingCustomField;
                            $OrganizationTrainingCustomField->training_id = $request->trainingId;
                            $OrganizationTrainingCustomField->custom_field_id = $id;
                            $OrganizationTrainingCustomField->custom_number_of_field_id = '';
                            $OrganizationTrainingCustomField->custom_field_value = $value;
                            $OrganizationTrainingCustomField->org_id = $organizationId;
                            $OrganizationTrainingCustomField->created_id = $authId;
                            $OrganizationTrainingCustomField->modified_id = $authId;
                            $OrganizationTrainingCustomField->save();
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

                        $OrganizationTrainingCustomField = OrganizationTrainingCustomField::where('custom_field_id',$id)->where('training_id',$request->trainingId);
                        if($OrganizationTrainingCustomField->count() > 0){
                            $OrganizationTrainingCustomField->update([
                                'custom_number_of_field_id' => $value,
                                'custom_field_value' => 1,
                                'modified_id' => $authId,
                            ]);
                        }else{
                            $OrganizationTrainingCustomField = new OrganizationTrainingCustomField;
                            $OrganizationTrainingCustomField->training_id = $request->trainingId;
                            $OrganizationTrainingCustomField->custom_field_id = $id;
                            $OrganizationTrainingCustomField->custom_number_of_field_id = $value;
                            $OrganizationTrainingCustomField->custom_field_value = 1;
                            $OrganizationTrainingCustomField->org_id = $organizationId;
                            $OrganizationTrainingCustomField->created_id = $authId;
                            $OrganizationTrainingCustomField->modified_id = $authId;
                            $OrganizationTrainingCustomField->save();
                        }
                    }
                }
            }

            // $handout = $handoutSize = $handoutType = $handoutName = '';
            // if($request->file('handout') != ''){

            //     $path = getPathS3Bucket().'/handouts';
            //     $s3Handout = Storage::disk('s3')->put($path, $request->handout);
            //     $handout = substr($s3Handout, strrpos($s3Handout, '/') + 1);
            //     $handoutSize = $request->file('handout')->getSize();
            //     $handoutType = $request->file('handout')->extension();
            //     $handoutName = $request->file('handout')->getClientOriginalName();

            //     $resource = new OrganizationResource;
            //     $resource->resource_name = $handoutName;
            //     $resource->resource_size = $handoutSize;
            //     $resource->resource_type = $handoutType;
            //     $resource->resource_url = $handout;
            //     $resource->org_id = $organizationId;
            //     $resource->is_active = 1;
            //     $resource->user_id = $authId;
            //     $resource->created_id = $authId;
            //     $resource->date_created = Carbon::now();
            //     $resource->save();

            //     $trainingHandout = OrganizationTrainingHandout::where('training_id',$request->trainingId);
            //     if($trainingHandout->count() > 0){
            //         $trainingHandout->update([
            //             'resource_id' => $resource->resource_id
            //         ]);
            //     }else{
            //         if($resource->resource_id != ''){
            //             $trainingHandout = new OrganizationTrainingHandout;
            //             $trainingHandout->training_id = $trainingLibrary->training_id;
            //             $trainingHandout->resource_id = $resource->resource_id;
            //             $trainingHandout->org_id = $organizationId;
            //             $trainingHandout->is_active = 1;
            //             $trainingHandout->save();
            //         }
            //     }
            // }

            return response()->json(['status'=>true,'code'=>200,'message'=>'Course has been updated successfully.'],200);
        
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteCourseCatalog(Request $request){
        $organizationId = Auth::user()->org_id;

        $validator = Validator::make($request->all(), [
            'courseLibraryId'=>'required|integer'
        ]);
        
        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{
            OrganizationTrainingLibrary::where('training_id',$request->courseLibraryId)->where('org_id',$organizationId)->update([
                'is_active' => 0
            ]);

            OrganizationTrainingMedia::where('training_id',$request->courseLibraryId)->where('org_id',$organizationId)->update([
                'is_active' => 0
            ]);

            OrganizationTrainingHandout::where('training_id',$request->courseLibraryId)->where('org_id',$organizationId)->update([
                'is_active' => 0
            ]);

            return response()->json(['status'=>true,'code'=>200,'message'=>'Course has been deleted successfully.'],200);
       
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }


    public function getCourseCatalogReferenceCodeOptionList(){
        $trainingLibrary = OrganizationTrainingLibrary::where('is_active','!=','0')->select('training_code as trainingCode',DB::raw('CONCAT(training_code," (",training_name,")") AS name'))->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$trainingLibrary],200);
    }

}