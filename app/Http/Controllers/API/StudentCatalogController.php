<?php

namespace App\Http\Controllers\API;

use App\Models\TrainingLibrary;
use App\Models\UserCatalogInprogress;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Illuminate\Support\Facades\Storage;


class StudentCatalogController extends BaseController
{
    public function getStudentCatalogList(Request $request)
    {
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $courseArray = [];
        $coursesArray = [];
        $trainingMedias = [];
        $imageUrl = '';

        $catalogs = UserCatalogInprogress::join('lms_org_training_library as trainingLibrary','lms_user_catalog_inprogress.course_id','=','trainingLibrary.training_id')
        ->leftJoin('lms_image as image','trainingLibrary.image_id','=','image.image_id')
        ->leftJoin('lms_content_types as contentTypes','trainingLibrary.content_type','=','contentTypes.content_types_id')
        ->leftJoin('lms_org_assessment_settings as assessmentSettings','trainingLibrary.training_id','=','assessmentSettings.training_id')
        ->where('lms_user_catalog_inprogress.org_id',$organizationId)
        ->select('lms_user_catalog_inprogress.id','trainingLibrary.training_id as courseLibraryId','trainingLibrary.training_type_id as trainingTypeId','trainingLibrary.content_type as contentTypeId','trainingLibrary.credits_visible as creditsVisible','assessmentSettings.require_passing_score as passingScore','trainingLibrary.quiz_type as quizType','trainingLibrary.training_name as courseTitle','trainingLibrary.description','image.image_url as imageUrl','trainingLibrary.content_type as contentTypesId','contentTypes.content_type as contentType','lms_user_catalog_inprogress.progress')
        ->get();
        if($catalogs->count() > 0){
            foreach($catalogs as $catalog){

                $imageUrl = '';
                if($catalog->imageUrl != ''){
                    $imageUrl = getFileS3Bucket(getPathS3Bucket().'/courses/'.$catalog->imageUrl); 
                }

                if($catalog->trainingTypeId == 1){

                    $trainingMedias = DB::table('lms_org_training_media as trainingMedia')
                    ->leftjoin('lms_org_media as media','trainingMedia.media_id','=','media.media_id')
                    ->leftJoin('lms_org_sco_menisfest_reader as scorm','media.media_id','=','scorm.media_id')
                    ->leftJoin('lms_org_sco_details as scormDetails','scorm.id','=','scormDetails.scorm_id')
                    //->where('scorm.course_id',$course->courseLibraryId)
                    ->where('trainingMedia.training_id',$catalog->courseLibraryId)
                    ->where('trainingMedia.org_id',$organizationId)
                    ->orderBy('trainingMedia.training_media_id','DESC')
                    ->groupBy('scormDetails.scorm_id')
                    ->select('media.media_id as mediaId','media.media_url as mediaUrl','media.media_type as mediaType','media.media_name as mediaName','scorm.id as scormId','scormDetails.launch','scorm.version as scormVersion')
                    ->get();

                    if($trainingMedias->count() > 0){
                        foreach($trainingMedias as $trainingMedia){

                            if($catalog->contentTypeId == 3){

                                $mediaName = $trainingMedia->mediaName;
                                $mediaUrl = $trainingMedia->mediaUrl; 

                                if($trainingMedia->launch){
                                    $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket()).'/media/'.$mediaUrl.'/'.$mediaName.'/'.$trainingMedia->launch;
                                }
                            }
                            else if($catalog->contentTypesId == 5 || $catalog->contentTypesId == 8){
                                $trainingMedia->mediaUrl = $trainingMedia->mediaUrl;
                            }
                            else{
                                $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$trainingMedia->mediaUrl);
                            }
                        }
                    }
                    $catalog->trainingMedia = $trainingMedias;
                }

                $courseArray['id'] = $catalog->id;
                $courseArray['courseLibraryId'] = $catalog->courseLibraryId;
                $courseArray['courseTitle'] = $catalog->courseTitle;
                $courseArray['description'] = $catalog->description;
                $courseArray['imageUrl'] = $imageUrl;
                $courseArray['contentType'] = $catalog->contentType;
                $courseArray['trainingMedias'] = $trainingMedias;
                $courseArray['progress'] = $catalog->progress;
                $courseArray['isEnrollment'] = DB::table('lms_enrollment')->where('org_id',$organizationId)->where('user_id',$authId)
                ->where('training_id',$catalog->courseLibraryId)->count();
                $coursesArray[] = $courseArray;

            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$coursesArray],200);

        exit;


        $catalogs = DB::table('lms_org_assignment_user_course')
        //->whereRaw('FIND_IN_SET("'.$authId.'",users)')
        //->where('users',$authId)
        ->where('org_id',$organizationId)
        ->get();
        if($catalogs->count() > 0){
            foreach($catalogs as $catalog){
                $courses = DB::table('lms_org_training_library as trainingLibrary')
                ->leftJoin('lms_image as image','trainingLibrary.image_id','=','image.image_id')
                ->leftJoin('lms_content_types as contentTypes','trainingLibrary.content_type','=','contentTypes.content_types_id')
                ->leftJoin('lms_org_assessment_settings as assessmentSettings','trainingLibrary.training_id','=','assessmentSettings.training_id')
                ->whereIn('trainingLibrary.training_id',explode(',',$catalog->courses))
                ->where('trainingLibrary.org_id',$organizationId)
                ->where('trainingLibrary.is_active','=','1')
                ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary.training_type_id as trainingTypeId','trainingLibrary.content_type as contentTypeId','trainingLibrary.credits_visible as creditsVisible','assessmentSettings.require_passing_score as passingScore','trainingLibrary.quiz_type as quizType','trainingLibrary.training_name as courseTitle','trainingLibrary.description','image.image_url as imageUrl','trainingLibrary.content_type as contentTypesId','contentTypes.content_type as contentType')
                ->get();
                if($courses->count() > 0){
                    foreach($courses as $course){

                        $imageUrl = '';
                        if($course->imageUrl != ''){
                            $imageUrl = getFileS3Bucket(getPathS3Bucket().'/courses/'.$course->imageUrl); 
                        }

                        if($course->trainingTypeId == 1){

                            $trainingMedias = DB::table('lms_org_training_media as trainingMedia')
                            ->leftjoin('lms_org_media as media','trainingMedia.media_id','=','media.media_id')
                            ->leftJoin('lms_org_sco_menisfest_reader as scorm','media.media_id','=','scorm.media_id')
                            ->leftJoin('lms_org_sco_details as scormDetails','scorm.id','=','scormDetails.scorm_id')
                            //->where('scorm.course_id',$course->courseLibraryId)
                            ->where('trainingMedia.training_id',$course->courseLibraryId)
                            ->where('trainingMedia.org_id',$organizationId)
                            ->orderBy('trainingMedia.training_media_id','DESC')
                            ->groupBy('scormDetails.scorm_id')
                            ->select('media.media_id as mediaId','media.media_url as mediaUrl','media.media_type as mediaType','media.media_name as mediaName','scorm.id as scormId','scormDetails.launch')
                            ->get();
            
                            if($trainingMedias->count() > 0){
                                foreach($trainingMedias as $trainingMedia){

                                    if($course->contentTypeId == 3){

                                        $mediaName = $trainingMedia->mediaName;
                                        $mediaUrl = $trainingMedia->mediaUrl; 
            
                                        if($trainingMedia->launch){
                                            $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket()).'/media/'.$mediaUrl.'/'.$mediaName.'/'.$trainingMedia->launch;
                                        }
                                    }
                                    else if($course->contentTypesId == 5 || $course->contentTypesId == 8){
                                        $trainingMedia->mediaUrl = $trainingMedia->mediaUrl;
                                    }
                                    else{
                                        $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$trainingMedia->mediaUrl);
                                    }
                                }
                            }
                            $catalog->trainingMedia = $trainingMedias;
                        }


                        $courseArray['courseLibraryId'] = $course->courseLibraryId;
                        $courseArray['courseTitle'] = $course->courseTitle;
                        $courseArray['description'] = $course->description;
                        $courseArray['imageUrl'] = $imageUrl;
                        $courseArray['contentType'] = $course->contentType;
                        $courseArray['trainingMedias'] = $trainingMedias;
                        $courseArray['progress'] = $catalog->progress;
                        $courseArray['isEnrollment'] = DB::table('lms_enrollment')->where('org_id',$organizationId)->where('user_id',$authId)
                        ->where('training_id',$course->courseLibraryId)->count();
                        $coursesArray[] = $courseArray;

                    }
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$coursesArray],200);
    }

    public function saveQuiz(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'courseLibraryId' => 'required',
            'questionAnswers' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $courseLibraryId = $request->courseLibraryId;
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $trainingLibrary = TrainingLibrary::where('training_id',$courseLibraryId)->where('is_active','!=','0');
        if($trainingLibrary->count() > 0){
            $quizType = $trainingLibrary->first()->quiz_type;

            if(!empty($request->questionAnswers)){
                foreach(json_decode($request->questionAnswers) as $questionAnswer){

                    $trainingQuestionAnswer = DB::table('lms_training_library_question_answer')::where('user_id',$authId)
                    ->where('org_id',$organizationId)
                    ->where('training_id',$courseLibraryId)
                    ->where('question_id',$questionAnswer->questionId);

                    if($trainingQuestionAnswer->count() > 0){
                        if($quizType == 'Servey'){
                            $trainingQuestionAnswer->update([
                                'answer_id' => $questionAnswer->answerId,
                                'date_modified' => date('Y-m-d H:i:s')
                            ]);
                        }
                    }else{
                        DB::table('lms_training_library_question_answer')->insert([
                            'user_id' => $authId,
                            'org_id' => $organizationId,
                            'training_id' => $courseLibraryId,
                            'question_id' => $questionAnswer->questionId,
                            'answer_id' => $questionAnswer->answerId,
                            'date_created' => date('Y-m-d H:i:s'),
                            'date_modified' => date('Y-m-d H:i:s')
                        ]);
                    }
                }
            }

            return response()->json(['status'=>true,'code'=>200,'message'=>'Quiz submited successfully.'],200);
        }

    }

    public function saveSuspendVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'courseLibraryId' => 'required',
            'mediaId' => 'required',
            'time' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }
        $courseLibraryId = $request->courseLibraryId;
        $mediaId = $request->mediaId;
        $time = $request->time;
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        
        $suspendVideo = DB::table('lms_suspend_video')->where('training_id',$courseLibraryId)
        ->where('media_id',$mediaId)
        ->where('org_id',$organizationId)
        ->where('user_id',$authId);
        if($suspendVideo->count() > 0){
            $suspendVideo->update([
                'time' => $time
            ]);
        }else{
            DB::table('lms_suspend_video')->insert([
                'media_id' => $mediaId,
                'org_id' => $organizationId,
                'user_id' => $authId,
                'training_id' => $courseLibraryId,
                'time' => $time,
            ]);
        }
    }

    public function getStudentCatalogById($id){
        
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $courseArray = [];
        $coursesArray = [];
        $trainingMedias = [];
        $imageUrl = ''; 

        $catalog = UserCatalogInprogress::join('lms_org_training_library as trainingLibrary','lms_user_catalog_inprogress.course_id','=','trainingLibrary.training_id')
        ->leftJoin('lms_image as image','trainingLibrary.image_id','=','image.image_id')
        ->leftJoin('lms_content_types as contentTypes','trainingLibrary.content_type','=','contentTypes.content_types_id')
        ->leftJoin('lms_training_types as trainingTypes','trainingLibrary.training_type_id','=','trainingTypes.training_type_id')
        ->leftJoin('lms_org_assessment_settings as assessmentSettings','trainingLibrary.training_id','=','assessmentSettings.training_id')
        ->where('lms_user_catalog_inprogress.org_id',$organizationId)
        ->where('lms_user_catalog_inprogress.id',$id)
        ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary.training_type_id as trainingTypeId','trainingTypes.training_type as trainingType','trainingLibrary.content_type as contentTypeId','trainingLibrary.credits_visible as creditsVisible','assessmentSettings.require_passing_score as passingScore','trainingLibrary.quiz_type as quizType','trainingLibrary.training_name as courseTitle','trainingLibrary.description','image.image_url as imageUrl','trainingLibrary.content_type as contentTypesId','contentTypes.content_type as contentType','lms_user_catalog_inprogress.progress')
        ->first();
        if($catalog){

            $imageUrl = '';
            if($catalog->imageUrl != ''){
                $imageUrl = getFileS3Bucket(getPathS3Bucket().'/courses/'.$catalog->imageUrl); 
            }

            if($catalog->trainingTypeId == 1){

                $trainingMedias = DB::table('lms_org_training_media as trainingMedia')
                ->leftjoin('lms_org_media as media','trainingMedia.media_id','=','media.media_id')
                ->leftJoin('lms_org_sco_menisfest_reader as scorm','media.media_id','=','scorm.media_id')
                ->leftJoin('lms_org_sco_details as scormDetails','scorm.id','=','scormDetails.scorm_id')
                //->where('scorm.course_id',$course->courseLibraryId)
                ->where('trainingMedia.training_id',$catalog->courseLibraryId)
                ->where('trainingMedia.org_id',$organizationId)
                ->orderBy('trainingMedia.training_media_id','DESC')
                ->groupBy('scormDetails.scorm_id')
                ->select('media.media_id as mediaId','media.media_url as mediaUrl','media.media_type as mediaType','media.media_name as mediaName','scorm.id as scormId','scormDetails.launch','scorm.version as scormVersion')
                ->get();

                if($trainingMedias->count() > 0){
                    foreach($trainingMedias as $trainingMedia){

                        if($catalog->contentTypeId == 3){

                            $mediaName = $trainingMedia->mediaName;
                            $mediaUrl = $trainingMedia->mediaUrl; 

                            if($trainingMedia->launch){
                                $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket()).'/media/'.$mediaUrl.'/'.$mediaName.'/'.$trainingMedia->launch;
                            }
                        }
                        else if($catalog->contentTypesId == 5 || $catalog->contentTypesId == 8){
                            $trainingMedia->mediaUrl = $trainingMedia->mediaUrl;
                        }
                        else{
                            $trainingMedia->mediaUrl = getFileS3Bucket(getPathS3Bucket().'/media/'.$trainingMedia->mediaUrl);
                        }
                    }
                }
                $catalog->trainingMedia = $trainingMedias;
            }

            $courseArray['courseLibraryId'] = $catalog->courseLibraryId;
            $courseArray['courseTitle'] = $catalog->courseTitle;
            $courseArray['description'] = $catalog->description;
            $courseArray['trainingTypeId'] = $catalog->trainingTypeId;
            $courseArray['trainingType'] = $catalog->trainingType;
            $courseArray['imageUrl'] = $imageUrl;
            $courseArray['contentType'] = $catalog->contentType;
            $courseArray['trainingMedias'] = $trainingMedias;
            $courseArray['progress'] = $catalog->progress;
            $courseArray['isEnrollment'] = DB::table('lms_enrollment')->where('org_id',$organizationId)->where('user_id',$authId)
            ->where('training_id',$catalog->courseLibraryId)->count();
            $coursesArray = $courseArray;

        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$coursesArray],200);

        exit;

        $catalogs = DB::table('lms_training_library as trainingLibrary')
        ->join('lms_image as image','trainingLibrary.image_id','=','image.image_id')
        ->where('trainingLibrary.is_active','=','1')
        ->select('trainingLibrary.training_id as courseLibraryId','image.image_url as imageUrl')
        ->first();


        $data = $dataAll = []; 

        $trainingMedias = DB::table('lms_training_media')->where('is_active','1')->where('training_id',$id);
        if($trainingMedias->count() > 0){
            $trainingMedias = $trainingMedias->select('media_id')->get();
            foreach($trainingMedias as $trainingMedia){

                $medias = DB::table('lms_media')->where('is_active','1')->where('media_id',$trainingMedia->media_id);
                if($medias->count() > 0){
                    $medias = $medias->select('media_url as mediaUrl','media_type as mediaType')->get();
                    foreach($medias as $media){

                        $data['mediaType'] = $media->mediaType;

                        if($media->mediaType == 'zip'){
                            $files = Storage::disk('s3')->allFiles(getPathS3Bucket().'/media/'.$media->mediaUrl);
                
                            foreach($files as $file){
                                $fileName = substr($file, strrpos($file, "/") + 1);
                                $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                                //$fileUrl = getFileS3Bucket($file);
                                if($fileExtension == 'xml'){
                                    $fileUrl = getFileS3Bucket($file);
                                    $xmlString = file_get_contents($fileUrl);
                                    $xmlObject = simplexml_load_string($xmlString);
                                    $xmlFile = json_encode($xmlObject);
                                    
                                }
                                if (strpos($file, 'shared/launchpage.html') !== false){
                                    $fileUrl = getFileS3Bucket($file).'?content=playing';
                                    $data['mediaUrl'] = $fileUrl;
                                }
                            }
                        }else{
                            $data['mediaUrl'] = getFileS3Bucket(getPathS3Bucket().'/media/'.$media->mediaUrl);
                        }

                        $dataAll[] = $data;
                    }
                }
            
            }
        }

        if($catalogs->imageUrl != ''){
            $catalogs->imageUrl = getFileS3Bucket(getPathS3Bucket().'/courses/'.$catalogs->imageUrl); 
        }

        $catalogs->media = $dataAll;

        return response()->json(['status'=>true,'code'=>200,'data'=>$catalogs],200);
    }
}
