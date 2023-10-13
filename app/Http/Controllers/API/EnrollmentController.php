<?php

namespace App\Http\Controllers\API;

use App\Models\TrainingLibrary;
use App\Models\OrganizationTrainingLibrary;
use App\Models\OrganizationCertificate;
use App\Models\DynamicField;
use App\Models\Enrollment;
use App\Models\Transcript;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use Aws\S3\S3Client;
use Illuminate\Support\Str;


class EnrollmentController extends BaseController
{
    public function getEnrollmentList(Request $request){
        
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $courseArray = [];
        $coursesArray = [];
        $trainingMedias = [];
        $progress = '';
        $imageUrl = '';

        $courses = DB::table('lms_enrollment as enrollment')
        ->leftJoin('lms_org_training_library as trainingLibrary','enrollment.training_id','=','trainingLibrary.training_id')
        ->leftJoin('lms_image as image','trainingLibrary.image_id','=','image.image_id')
        ->leftJoin('lms_content_types as contentTypes','trainingLibrary.content_type','=','contentTypes.content_types_id')
        ->leftJoin('lms_org_assessment_settings as assessmentSettings','trainingLibrary.training_id','=','assessmentSettings.training_id')
        ->where('enrollment.org_id',$organizationId)
        ->where('enrollment.user_id',$authId)
        ->where('trainingLibrary.org_id',$organizationId)
        ->where('enrollment.is_active','=','1')
        ->where('trainingLibrary.is_active','=','1')
        ->select('trainingLibrary.training_id as courseLibraryId','trainingLibrary.training_type_id as trainingTypeId','trainingLibrary.content_type as contentTypeId','trainingLibrary.credits_visible as creditsVisible','assessmentSettings.require_passing_score as passingScore','trainingLibrary.quiz_type as quizType','trainingLibrary.training_name as courseTitle','trainingLibrary.description','image.image_url as imageUrl','trainingLibrary.content_type as contentTypesId','contentTypes.content_type as contentType')
        ->get();
        if($courses->count() > 0){
            foreach($courses as $course){

                $progress = '';
                $check = DB::table('lms_org_assignment_user_course')->where('users',$authId)->where('courses',$course->courseLibraryId)->where('org_id',$organizationId);
                if($check->count() > 0){
                    $progress = $check->first()->progress;
                }

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
                    ->select('media.media_id as mediaId','media.media_url as mediaUrl','media.media_type as mediaType','media.media_name as mediaName','scorm.id as scormId','scormDetails.launch','scorm.version')
                    ->get();
    
                    if($trainingMedias->count() > 0){
                        foreach($trainingMedias as $trainingMedia){

                            //if($trainingMedia->mediaType == 'zip' || $trainingMedia->mediaType == 'rar'){
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
                }


                $courseArray['courseLibraryId'] = $course->courseLibraryId;
                $courseArray['courseTitle'] = $course->courseTitle;
                $courseArray['description'] = $course->description;
                $courseArray['imageUrl'] = $imageUrl;
                $courseArray['contentType'] = $course->contentType;
                $courseArray['trainingMedias'] = $trainingMedias;
                $courseArray['progress'] = $progress;
                $coursesArray[] = $courseArray;

            }
        }
        
        return response()->json(['status'=>true,'code'=>200,'data'=>$coursesArray],200);
    }

    public function addEnrollment(Request $request){

        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'courseLibraryId' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $enrollment = Enrollment::where('org_id',$organizationId)->where('user_id',$authId)
        ->where('training_id',$request->courseLibraryId);
        if($enrollment->count() > 0){
            return response()->json(['status'=>true,'code'=>400,'error'=>'Enrollment is already exist.'],400);
        }

        $enrollment = new Enrollment;
        $enrollment->training_id = $request->courseLibraryId;
        $enrollment->org_id = $organizationId;
        $enrollment->user_id = $authId;
        $enrollment->created_id = $authId;
        $enrollment->modified_id = $authId;
        $enrollment->save();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Enrollment has been created successfully.'],200);
    }

    public function studentInprogressCourse(Request $request){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $validator = Validator::make($request->all(), [
            'courseId' => 'required',
            'progress' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $catalogs = DB::table('lms_org_assignment_user_course')->where('courses',$request->courseId)->where('users',$authId)->where('org_id',$organizationId);
        if($catalogs->count() > 0){
            $catalogs->update([
                'progress' => $request->progress,
                'date_modified' => date('Y-m-d H:i:s'),
                'modified_id' => $authId,
            ]);
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Updated successfully.'],200);
    } 

    public function studentCompletedCourse(Request $request){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $objectURL = '';
        $fileName = '';

        $validator = Validator::make($request->all(), [
            'courseId' => 'required',
            'progress' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        $catalogs = DB::table('lms_org_assignment_user_course')->where('courses',$request->courseId)->where('users',$authId)->where('org_id',$organizationId);
        if($catalogs->count() > 0){
            $catalogs->update([
                'progress' => $request->progress,
                'comment' => $request->comment,
                'rating' => $request->rating,
                'date_modified' => date('Y-m-d H:i:s'),
                'modified_id' => $authId,
            ]);
            

            if($request->progress == 1){

                $studentRating = 0;
                $studentCourse = DB::table('lms_org_assignment_user_course')->where('courses',$request->courseId)->where('org_id',$organizationId);
                if($studentCourse->count() > 0){

                    $noOfStudent = $studentCourse->count();
                    $totalSum = $studentCourse->sum('rating');

                    $studentRating = $totalSum/$noOfStudent;
                }

                $OrganizationTrainingLibrary = OrganizationTrainingLibrary::where('training_id',$request->courseId);
                if($OrganizationTrainingLibrary->count() > 0){
                    $OrganizationTrainingLibrary->update([
                        'student_rating'=>$studentRating
                    ]);

                    $certificateId = $OrganizationTrainingLibrary->first()->certificate_id;
                    if($certificateId != ''){
                        $OrganizationCertificate = OrganizationCertificate::where('certificate_id',$certificateId);
                        if($OrganizationCertificate->count() > 0){

                            $OrganizationCertificate = $OrganizationCertificate->first();

                            $dynamicFieldForCertificate = dynamicFieldForCertificate($OrganizationCertificate->cert_structure,$authId);

                            $certificateData = [
                                'cert_structure' => $dynamicFieldForCertificate,
                                'base_language' => $OrganizationCertificate->base_language,
                                'bgimage' => $OrganizationCertificate->bgimage ? getFileS3Bucket(getPathS3Bucket().'/certificate/'.$OrganizationCertificate->bgimage) : '',
                                'meta' => $OrganizationCertificate->meta,
                                'description' => $OrganizationCertificate->description
                            ];

                            $orientation = $OrganizationCertificate->orientation == 'P' ? 'portrait' : 'landscape';
                            $pdf = PDF::loadView('pdf.pdf', $certificateData)->setPaper('a4', $orientation);
                            $pdfContents = $pdf->output();

                            $s3 = new S3Client([
                                'credentials' => [
                                    'key'    => env('AWS_ACCESS_KEY_ID'),
                                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                                ],
                                'region' => env('AWS_DEFAULT_REGION'),
                                'version' => 'latest',
                            ]);

                            $fileName = $authId.time().Str::random(16).".pdf";
                            $pdfFileName = getPathS3Bucket()."/user/certificate/$fileName"; // Replace with the desired filename for the PDF
                            $result = $s3->putObject([
                                'Bucket' => env('AWS_BUCKET'),
                                'Key'    => $pdfFileName,
                                'Body'   => $pdfContents,
                                'ACL'    => 'public-read', // Set the desired ACL for the uploaded file
                            ]);

                            // $result_arr = $result->toArray(); 
                            // if(!empty($result_arr['ObjectURL'])) { 
                            //     $objectURL =  $s3_file_link = $result_arr['ObjectURL']; 
                            // } 
                        }
                    }
                }

                $transcript = new Transcript;
                $transcript->user_id = $authId;
                $transcript->org_id = $organizationId;
                $transcript->training_id = $request->courseId;
                $transcript->notes = $request->comment;
                $transcript->status = $request->progress;
                $transcript->certificate_link = $fileName;
                $transcript->created_id = $authId;
                $transcript->modified_id = $authId;
                $transcript->save();
            }
        }

        return response()->json(['status'=>true,'code'=>200,'message'=>'Updated successfully.'],200);
    } 
}
