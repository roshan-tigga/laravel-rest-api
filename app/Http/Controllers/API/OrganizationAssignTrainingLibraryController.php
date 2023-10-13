<?php

namespace App\Http\Controllers\API;

use App\Models\OrganizationAssignTrainingLibrary;
use DB;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;


use App\Models\TrainingLibrary;
use App\Models\Media;
use App\Models\ContentLibrary;
use App\Models\TrainingMedia;

use App\Models\TrainingNotificationSetting;

use App\Models\AssessmentSetting;
use App\Models\AssessmentQuestion;
use App\Models\QuestionAnswer;


use App\Models\OrganizationTrainingLibrary;
use App\Models\OrganizationMedia;
use App\Models\OrganizationContentLibrary;
use App\Models\OrganizationTrainingMedia;

use App\Models\OrganizationTrainingNotificationSetting;

use App\Models\OrganizationAssessmentSetting;
use App\Models\OrganizationAssessmentQuestion;
use App\Models\OrganizationQuestionAnswer;

use App\Models\CategoryMaster;
use App\Models\OrganizationCategory;

use App\Models\ScoMenisfestReader;
use App\Models\ScoDetails;

use App\Models\OrganizationScoMenisfestReader;
use App\Models\OrganizationScoDetails;

use App\Models\TrainingHandout;
use App\Models\Resource;

use App\Models\OrganizationTrainingHandout;
use App\Models\OrganizationResource;

use App\Models\CertificateMaster;
use App\Models\OrganizationCertificate;
use App\Models\UserCatalogInprogress;
use App\Models\Enrollment;

class OrganizationAssignTrainingLibraryController extends BaseController
{
    public function getTrainingAssignedToOrganizationList($trainingId){

        $data = $dataAll = [];
        $organizations = DB::table('lms_org_master as org')
        ->join('lms_domain as domain','org.domain_id','=','domain.domain_id')
        ->where('org.is_active','=','1')
        ->select('org.org_id as organizationId','org.organization_name as organizationName','domain.domain_name as domainName')
        ->get();


        if(!empty($organizations)){
            foreach($organizations as $organization){
                $data['organizationId'] = $organization->organizationId;
                $data['organizationName'] = $organization->organizationName;
                $data['domainName'] = $organization->domainName;

                $training = OrganizationTrainingLibrary::where('training_library_id',$trainingId)->where('org_id',$organization->organizationId);
                if($training->count() > 0){
                    $data['isChecked'] = 1;
                }else{
                    $data['isChecked'] = 0;
                }
                $dataAll[] = $data;
            }
            $array = array_column($dataAll, 'isChecked');
            array_multisort($array, SORT_DESC, $dataAll);
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$dataAll],200);
    }

    public function trainingAssignmentToOrganization(Request $request){

        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'trainingIds' => 'required',
            'organizationIds' => 'required'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try {

            if(is_array($request->trainingIds) && is_array($request->organizationIds)){

                if(count($request->trainingIds) > 0 && count($request->organizationIds) > 0){
                    foreach($request->trainingIds as $trainingId){

                        foreach($request->organizationIds as $organization){
                            $organizationId = $organization['organizationId'];
                            $isChecked = $organization['isChecked'];

                            if($isChecked == 1){
                                $checkOrganizationTrainingLibrary = OrganizationTrainingLibrary::where('is_active','!=','0')
                                ->where('org_id',$organizationId)->where('training_library_id',$trainingId);
                                if($checkOrganizationTrainingLibrary->count() > 0){

                                }else{
                                    $trainingLibrary = TrainingLibrary::where('training_id',$trainingId);
                                    if($trainingLibrary->count() > 0){ 

                                        $trainingLibrary = $trainingLibrary->first();

                                        $categoryIds = [];
                                        if(!empty($trainingLibrary->category_id)){
                                            $categoryMasters = CategoryMaster::whereIn('category_id',explode(',',$trainingLibrary->category_id));
                                            if($categoryMasters->count() > 0){
                                                foreach($categoryMasters->get() as $categoryMaster){
                                                    
                                                    $organizationCategory = OrganizationCategory::where('is_active','!=','0')->where('category_name','LIKE','%'.$categoryMaster->category_name.'%')->where('org_id',$organizationId);
                                                    if($organizationCategory->count() > 0){
                                                        $categoryIds[] = $organizationCategory->first()->category_id;
                                                    }else{
                                                        $organizationCategory = OrganizationCategory::where('org_id','=',$organizationId)->orderBy('category_code','DESC');
                                                        if($organizationCategory->count() > 0){
                                                            $categoryCode = $organizationCategory->first()->category_code + 1;
                                                        }else{
                                                            $categoryCode = 100000;
                                                        }

                                                        $organizationCategoryPId = Null;
                                                        $organizationCategoryP = OrganizationCategory::where('org_id','=',$organizationId)->where('category_master_id',$categoryMaster->primary_category_id);
                                                        if($organizationCategoryP->count() > 0){
                                                            $organizationCategoryPId = $organizationCategoryP->first()->category_id;
                                                        }

                                                        $organizationCategoryInsert = new OrganizationCategory;
                                                        $organizationCategoryInsert->category_master_id = $categoryMaster->category_id;
                                                        $organizationCategoryInsert->category_name = $categoryMaster->category_name;
                                                        $organizationCategoryInsert->category_code = $categoryCode;
                                                        if($categoryMaster->primary_category_id != ''){
                                                            $organizationCategoryInsert->primary_category_id = $organizationCategoryPId;
                                                        }
                                                        $organizationCategoryInsert->description = $categoryMaster->description;;
                                                        $organizationCategoryInsert->org_id = $organizationId;
                                                        $organizationCategoryInsert->is_active = 1;
                                                        $organizationCategoryInsert->su_assigned = 1;
                                                        $organizationCategoryInsert->created_id = $authId;
                                                        $organizationCategoryInsert->modified_id = $authId;
                                                        $organizationCategoryInsert->save();

                                                        $categoryIds[] = $organizationCategoryInsert->category_id;
                                                    }
                                                }
                                            }
                                        }

                                        $organizationTrainingLibrary = new OrganizationTrainingLibrary;
                                        $organizationTrainingLibrary->training_library_id = $trainingId;
                                        $organizationTrainingLibrary->training_type_id = $trainingLibrary->training_type_id;
                                        $organizationTrainingLibrary->training_name = $trainingLibrary->training_name;
                                        $organizationTrainingLibrary->training_code = $trainingLibrary->training_code;
                                        $organizationTrainingLibrary->reference_code = $trainingLibrary->reference_code;
                                        $organizationTrainingLibrary->description = $trainingLibrary->description;
                                        $organizationTrainingLibrary->content_type = $trainingLibrary->content_type;
                                        
                                        $organizationTrainingLibrary->image_id = $trainingLibrary->image_id;

                                        $organizationTrainingLibrary->credits = $trainingLibrary->credits;
                                        $organizationTrainingLibrary->credits_visible = $trainingLibrary->credits_visible;
                                        $organizationTrainingLibrary->points = $trainingLibrary->points;
                                        $organizationTrainingLibrary->points_visible = $trainingLibrary->points_visible;

                                        $organizationTrainingLibrary->passing_score = $trainingLibrary->passing_score;
                                        $organizationTrainingLibrary->ssl_on_off = $trainingLibrary->ssl_on_off;
                                        $organizationTrainingLibrary->enrollment_type = $trainingLibrary->enrollment_type;
                                        $organizationTrainingLibrary->activity_reviews = $trainingLibrary->activity_reviews;

                                        $organizationTrainingLibrary->unenrollment = $trainingLibrary->unenrollment;
                                        $organizationTrainingLibrary->quiz_type = $trainingLibrary->quiz_type;
                                        $organizationTrainingLibrary->category_id = implode(',',$categoryIds);

                                        $organizationTrainingLibrary->hours = $trainingLibrary->hours;
                                        $organizationTrainingLibrary->minutes = $trainingLibrary->minutes;
                                        $organizationTrainingLibrary->expiration_length = $trainingLibrary->expiration_length;
                                        $organizationTrainingLibrary->expiration_time = $trainingLibrary->expiration_time;



                                        $certificateMaster = CertificateMaster::where('certificate_id',$trainingLibrary->certificate_id);
                                        if($certificateMaster->count() > 0){
                                            $certificateMaster = $certificateMaster->first();

                                            $organizationCertificate = new OrganizationCertificate;
                                            $organizationCertificate->certificate_master_id = $trainingLibrary->certificate_id;
                                            $organizationCertificate->certificate_code = $certificateMaster->certificate_code;
                                            $organizationCertificate->certificate_name = $certificateMaster->certificate_name;
                                            $organizationCertificate->description = $certificateMaster->description;
                                            $organizationCertificate->base_language = $certificateMaster->base_language;
                                            $organizationCertificate->cert_structure = $certificateMaster->cert_structure;
                                            $organizationCertificate->orientation = $certificateMaster->orientation;
                                            $organizationCertificate->bgimage = $certificateMaster->bgimage;
                                            $organizationCertificate->meta = $certificateMaster->meta;
                                            $organizationCertificate->user_release = $certificateMaster->user_release;
                                            $organizationCertificate->org_id = $organizationId;
                                            $organizationCertificate->assigned = 1;
                                            $organizationCertificate->su_assigned = 1;
                                            $organizationCertificate->is_active = 1;
                                            $organizationCertificate->created_id = $authId;
                                            $organizationCertificate->modified_id = $authId;
                                            $organizationCertificate->save();

                                            $organizationTrainingLibrary->certificate_id = $organizationCertificate->certificate_id;
                                        }

                                        $organizationTrainingLibrary->is_active = $trainingLibrary->is_active;
                                        $organizationTrainingLibrary->ilt_enrollment_id = $trainingLibrary->ilt_enrollment_id;
                                        $organizationTrainingLibrary->training_status_id = $trainingLibrary->training_status_id;
                                        $organizationTrainingLibrary->org_id = $organizationId;
                                        $organizationTrainingLibrary->su_assigned = 1;
                                        $organizationTrainingLibrary->is_modified = 0;

                                        $organizationTrainingLibrary->created_id = $authId;
                                        $organizationTrainingLibrary->modified_id = $authId;
                                        $organizationTrainingLibrary->save();

                                        if(!empty($organizationTrainingLibrary->training_id)){

                                            //if($trainingLibrary->training_type_id == 1){
                                                $userCatalogInprogress = new UserCatalogInprogress;
                                                $userCatalogInprogress->user_id = $authId;
                                                $userCatalogInprogress->org_id = $organizationId;
                                                $userCatalogInprogress->created_id = $authId;
                                                $userCatalogInprogress->modified_id = $authId;
                                                $userCatalogInprogress->course_id = $organizationTrainingLibrary->training_id;
                                                $userCatalogInprogress->save();
                                            //}

                                            if($trainingLibrary->training_type_id == 2){
                                                $enrollment = new Enrollment;
                                                $enrollment->training_id = $organizationTrainingLibrary->training_id;
                                                $enrollment->org_id = $organizationId;
                                                $enrollment->user_id = $authId;
                                                $enrollment->created_id = $authId;
                                                $enrollment->modified_id = $authId;
                                                $enrollment->save();
                                            }

                                            if($trainingLibrary->training_type_id == 1){

                                                $trainingMedias = TrainingMedia::where('training_id',$trainingId);
                                                if($trainingMedias->count() > 0){
                                                    foreach($trainingMedias->get() as $trainingMedia){

                                                        
                                                        $medias = Media::where('media_id',$trainingMedia->media_id);
                                                        if($medias->count() > 0){
                                                            foreach($medias->get() as $media){

                                                                $organizationMedia = new OrganizationMedia;
                                                                $organizationMedia->media_name = $media->media_name;
                                                                $organizationMedia->media_size = $media->media_size;
                                                                $organizationMedia->media_type = $media->media_type;
                                                                $organizationMedia->media_url = $media->media_url;
                                                                $organizationMedia->org_id = $organizationId;
                                                                $organizationMedia->su_assigned = 1;
                                                                $organizationMedia->created_id = $authId;
                                                                $organizationMedia->modified_id = $authId;
                                                                $organizationMedia->save();

                                                                $scoMenisfestReaders = ScoMenisfestReader::where('is_active','1') //->where('course_id',$trainingId)
                                                                ->where('media_id',$trainingMedia->media_id);
                                                                if($scoMenisfestReaders->count() > 0){
                                                                    foreach($scoMenisfestReaders->get() as $scoMenisfestReader){

                                                                        $organizationScoMenisfestReader = new OrganizationScoMenisfestReader;
                                                                        $organizationScoMenisfestReader->course_id = $organizationTrainingLibrary->training_id;
                                                                        $organizationScoMenisfestReader->media_id = $organizationMedia->media_id;
                                                                        $organizationScoMenisfestReader->name = $scoMenisfestReader->name;
                                                                        $organizationScoMenisfestReader->scormtype = $scoMenisfestReader->scormtype;
                                                                        $organizationScoMenisfestReader->reference = $scoMenisfestReader->reference;
                                                                        $organizationScoMenisfestReader->version = $scoMenisfestReader->version;
                                                                        $organizationScoMenisfestReader->completionstatusrequired = $scoMenisfestReader->completionstatusrequired;
                                                                        $organizationScoMenisfestReader->completionscorerequired = $scoMenisfestReader->completionscorerequired;
                                                                        $organizationScoMenisfestReader->completionstatusallscos = $scoMenisfestReader->completionstatusallscos;
                                                                        $organizationScoMenisfestReader->autocommit = $scoMenisfestReader->autocommit;
                                                                        $organizationScoMenisfestReader->su_assigned = 1;
                                                                        $organizationScoMenisfestReader->org_id = $organizationId;
                                                                        $organizationScoMenisfestReader->created_id = $authId;
                                                                        $organizationScoMenisfestReader->modified_id = $authId;
                                                                        $organizationScoMenisfestReader->save();

                                                                        $scoDetails = ScoDetails::where('is_active','1')->where('scorm_id',$scoMenisfestReader->id);
                                                                        if($scoDetails->count() > 0){
                                                                            foreach($scoDetails->get() as $scoDetail){

                                                                                $organizationScoDetails = new OrganizationScoDetails;
                                                                                $organizationScoDetails->scorm_id = $organizationScoMenisfestReader->id;
                                                                                $organizationScoDetails->manifest = $scoDetail->manifest;
                                                                                $organizationScoDetails->identifier = $scoDetail->identifier;
                                                                                $organizationScoDetails->launch = $scoDetail->launch;
                                                                                $organizationScoDetails->scormtype = $scoDetail->scormtype;
                                                                                $organizationScoDetails->title = $scoDetail->title;
                                                                                $organizationScoDetails->sortorder = $scoDetail->sortorder;
                                                                                $organizationScoDetails->organization_id = $organizationId;
                                                                                $organizationScoDetails->su_assigned = 1;
                                                                                $organizationScoDetails->created_id = $authId;
                                                                                $organizationScoDetails->modified_id = $authId;
                                                                                $organizationScoDetails->save();

                                                                            }
                                                                        }
                                                                    }
                                                                }

                                                                $organizationTrainingMedia = new OrganizationTrainingMedia;
                                                                $organizationTrainingMedia->training_id = $organizationTrainingLibrary->training_id;
                                                                $organizationTrainingMedia->media_id = $organizationMedia->media_id;
                                                                $organizationTrainingMedia->org_id = $organizationId;
                                                                $organizationTrainingMedia->is_active = 1;
                                                                $organizationTrainingMedia->save();

                                                                $contentLibrarys = ContentLibrary::where('media_id',$trainingMedia->media_id);
                                                                if($contentLibrarys->count() > 0){
                                                                    foreach($contentLibrarys->get() as $contentLibrary){
                                                                        
                                                                        $organizationContentLibrary = new OrganizationContentLibrary;
                                                                        $organizationContentLibrary->content_name = $contentLibrary->content_name;
                                                                        $organizationContentLibrary->content_version = $contentLibrary->content_version;
                                                                        $organizationContentLibrary->content_types_id = $contentLibrary->content_types_id;
                                                                        $organizationContentLibrary->media_id = $organizationMedia->media_id;
                                                                        $organizationContentLibrary->parent_content_id = $contentLibrary->parent_content_id;
                                                                        $organizationContentLibrary->org_id = $organizationId; 
                                                                        $organizationContentLibrary->su_assigned = 1;
                                                                        $organizationContentLibrary->created_id = $authId;
                                                                        $organizationContentLibrary->modified_id = $authId;
                                                                        $organizationContentLibrary->save();

                                                                    }
                                                                }

                                                                
                                                            }
                                                        }
                                                    }
                                                }  

                                            }
                                            if($trainingLibrary->training_type_id == 2){

                                                $trainingNotificationSettings = TrainingNotificationSetting::where('training_id',$trainingId);
                                                if($trainingNotificationSettings->count() > 0){
                                                    foreach($trainingNotificationSettings->get() as $trainingNotificationSetting){
                                                        $organizationTrainingNotificationSetting = new OrganizationTrainingNotificationSetting;
                                                        $organizationTrainingNotificationSetting->training_notification_id = $trainingNotificationSetting->training_notification_id;
                                                        $organizationTrainingNotificationSetting->training_id = $organizationTrainingLibrary->training_id;
                                                        $organizationTrainingNotificationSetting->notification_on = $trainingNotificationSetting->notification_on;
                                                        $organizationTrainingNotificationSetting->is_active = $trainingNotificationSetting->is_active;
                                                        $organizationTrainingNotificationSetting->org_id = $organizationId;
                                                        $organizationTrainingNotificationSetting->save();
                                                    }
                                                }

                                                

                                            }
                                            if($trainingLibrary->training_type_id == 3){

                                                $assessmentSetting = AssessmentSetting::where('training_id',$trainingId);
                                                if($assessmentSetting->count() > 0){
                                                    $assessmentSetting = $assessmentSetting->first();

                                                    $organizationAssessmentSetting = new OrganizationAssessmentSetting;
                                                    $organizationAssessmentSetting->training_id = $organizationTrainingLibrary->training_id;
                                                    $organizationAssessmentSetting->require_passing_score = $assessmentSetting->require_passing_score;
                                                    $organizationAssessmentSetting->passing_percentage = $assessmentSetting->passing_percentage;
                                                    $organizationAssessmentSetting->randomize_questions = $assessmentSetting->randomize_questions;
                                                    $organizationAssessmentSetting->display_type = $assessmentSetting->display_type;
                                                    $organizationAssessmentSetting->hide_after_completed = $assessmentSetting->hide_after_completed;
                                                    $organizationAssessmentSetting->attempt_count = $assessmentSetting->attempt_count;
                                                    $organizationAssessmentSetting->learner_can_view_result = $assessmentSetting->learner_can_view_result;
                                                    $organizationAssessmentSetting->post_quiz_action = $assessmentSetting->post_quiz_action;
                                                    $organizationAssessmentSetting->pass_fail_status = $assessmentSetting->pass_fail_status;
                                                    $organizationAssessmentSetting->total_score = $assessmentSetting->total_score;
                                                    $organizationAssessmentSetting->correct_incorrect_marked = $assessmentSetting->correct_incorrect_marked;
                                                    $organizationAssessmentSetting->correct_incorrect_ans_marked = $assessmentSetting->correct_incorrect_ans_marked;
                                                    $organizationAssessmentSetting->timer_on = $assessmentSetting->timer_on;
                                                    $organizationAssessmentSetting->hrs = $assessmentSetting->hrs;
                                                    $organizationAssessmentSetting->mins = $assessmentSetting->mins;
                                                    $organizationAssessmentSetting->org_id = $organizationId;
                                                    $organizationAssessmentSetting->save();
                                                }

                                                $assessmentQuestions = AssessmentQuestion::where('training_id',$trainingId);
                                                if($assessmentQuestions->count() > 0){
                                                    foreach($assessmentQuestions->get() as $assessmentQuestion){

                                                        $organizationAssessmentQuestion = new OrganizationAssessmentQuestion;
                                                        $organizationAssessmentQuestion->training_id = $organizationTrainingLibrary->training_id;
                                                        $organizationAssessmentQuestion->question_type_id = $assessmentQuestion->question_type_id;
                                                        $organizationAssessmentQuestion->question = $assessmentQuestion->question;
                                                        $organizationAssessmentQuestion->show_ans_random = $assessmentQuestion->show_ans_random;
                                                        $organizationAssessmentQuestion->number_of_options = $assessmentQuestion->number_of_options;
                                                        $organizationAssessmentQuestion->question_score = $assessmentQuestion->question_score;
                                                        $organizationAssessmentQuestion->org_id = $organizationId;
                                                        $organizationAssessmentQuestion->save();

                                                        $questionAnswers = QuestionAnswer::where('question_id',$assessmentQuestion->question_id);
                                                        if($questionAnswers->count() > 0){
                                                            foreach($questionAnswers->get() as $questionAnswer){

                                                                $organizationQuestionAnswer = new OrganizationQuestionAnswer;
                                                                $organizationQuestionAnswer->question_id = $organizationAssessmentQuestion->question_id;
                                                                $organizationQuestionAnswer->options = $questionAnswer->options;
                                                                $organizationQuestionAnswer->is_correct = $questionAnswer->is_correct;
                                                                $organizationQuestionAnswer->text_ans = $questionAnswer->text_ans;
                                                                $organizationQuestionAnswer->numberic_ans = $questionAnswer->numberic_ans;
                                                                $organizationQuestionAnswer->text_box = $questionAnswer->text_box;
                                                                $organizationQuestionAnswer->numberic_ans = $questionAnswer->numberic_ans;
                                                                $organizationQuestionAnswer->org_id = $organizationId;
                                                                $organizationQuestionAnswer->save();

                                                            }
                                                        }
                                                    }
                                                }
                                            }
                                        }

                                        $trainingHandouts = TrainingHandout::where('training_id',$trainingId);
                                        if($trainingHandouts->count() > 0){ 
                                            foreach($trainingHandouts as $trainingHandout){
                                                
                                                $resources = Resource::where('resource_id',$trainingHandout->resource_id);
                                                if($resources->count() > 0){ 
                                                    foreach($resources as $resource){

                                                        $organizationResource = new OrganizationResource;
                                                        $organizationResource->resource_name = $resource->resource_name;
                                                        $organizationResource->resource_size = $resource->resource_size;
                                                        $organizationResource->resource_type = $resource->resource_type;
                                                        $organizationResource->resource_url = $resource->resource_url;
                                                        $organizationResource->org_id = $organizationId;
                                                        $organizationResource->is_active = 1;
                                                        $organizationResource->user_id = $authId;
                                                        $organizationResource->created_id = $authId;
                                                        $organizationResource->date_created = Carbon::now();
                                                        $organizationResource->save();

                                                        if($organizationResource->resource_id != ''){
                                                            $organizationTrainingHandout = new OrganizationTrainingHandout;
                                                            $organizationTrainingHandout->training_id = $trainingId;
                                                            $organizationTrainingHandout->resource_id = $organizationResource->resource_id;
                                                            $organizationTrainingHandout->org_id = $organizationId;
                                                            $organizationTrainingHandout->is_active = 1;
                                                            $organizationTrainingHandout->save();
                                                        }
                                                    }
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Training assigned to organization successfully.'],200);
            
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function courseCatalogReset(Request $request){
        try{
            $authId = Auth::user()->user_id;
            $trainingId = $request->trainingId;
            $organizationTrainingLibrary = OrganizationTrainingLibrary::where('training_id',$trainingId);
            if($organizationTrainingLibrary->count() > 0){

                $organizationTrainingLibrary = $organizationTrainingLibrary->first();

                $trainingLibrary = TrainingLibrary::where('training_id',$organizationTrainingLibrary->training_library_id);
                if($trainingLibrary->count() > 0){

                    $trainingLibrary = $trainingLibrary->first();

                    $organizationTrainingLibrary = OrganizationTrainingLibrary::find($trainingId);
                    $organizationTrainingLibrary->training_library_id = $trainingId;
                    $organizationTrainingLibrary->training_type_id = $trainingLibrary->training_type_id;
                    $organizationTrainingLibrary->training_name = $trainingLibrary->training_name;
                    $organizationTrainingLibrary->training_code = $trainingLibrary->training_code;
                    $organizationTrainingLibrary->reference_code = $trainingLibrary->reference_code;
                    $organizationTrainingLibrary->description = $trainingLibrary->description;
                    $organizationTrainingLibrary->content_type = $trainingLibrary->content_type;
                    
                    $organizationTrainingLibrary->image_id = $trainingLibrary->image_id;

                    $organizationTrainingLibrary->credits = $trainingLibrary->credits;
                    $organizationTrainingLibrary->credits_visible = $trainingLibrary->credits_visible;
                    $organizationTrainingLibrary->points = $trainingLibrary->points;
                    $organizationTrainingLibrary->points_visible = $trainingLibrary->points_visible;

                    $organizationTrainingLibrary->passing_score = $trainingLibrary->passing_score;
                    $organizationTrainingLibrary->ssl_on_off = $trainingLibrary->ssl_on_off;
                    $organizationTrainingLibrary->enrollment_type = $trainingLibrary->enrollment_type;
                    $organizationTrainingLibrary->activity_reviews = $trainingLibrary->activity_reviews;

                    $organizationTrainingLibrary->unenrollment = $trainingLibrary->unenrollment;
                    $organizationTrainingLibrary->quiz_type = $trainingLibrary->quiz_type;
                    $organizationTrainingLibrary->category_id = $trainingLibrary->category_id;
                    $organizationTrainingLibrary->certificate_id = $trainingLibrary->certificate_id;

                    $organizationTrainingLibrary->is_active = $trainingLibrary->is_active;
                    $organizationTrainingLibrary->ilt_enrollment_id = $trainingLibrary->ilt_enrollment_id;
                    $organizationTrainingLibrary->training_status_id = $trainingLibrary->training_status_id;
                    $organizationTrainingLibrary->su_assigned = 1;
                    $organizationTrainingLibrary->is_modified = 0;

                    $organizationTrainingLibrary->created_id = $authId;
                    $trainingLibrary->modified_id = $authId;
                    $organizationTrainingLibrary->save();

                    // if(!empty($organizationTrainingLibrary->training_id)){
                    //     if($trainingLibrary->training_type_id == 1){

                    //         $trainingMedias = TrainingMedia::where('training_id',$trainingId);
                    //         if($trainingMedias->count() > 0){
                    //             foreach($trainingMedias->get() as $trainingMedia){

                    //                 $medias = Media::where('media_id',$trainingMedia->media_id);
                    //                 if($medias->count() > 0){
                    //                     foreach($medias->get() as $media){

                    //                         $organizationMedia = new OrganizationMedia;
                    //                         $organizationMedia->media_name = $media->media_name;
                    //                         $organizationMedia->media_size = $media->media_size;
                    //                         $organizationMedia->media_type = $media->media_type;
                    //                         $organizationMedia->media_url = $media->media_url;
                    //                         $organizationMedia->org_id = $organizationId;
                    //                         $organizationMedia->created_id = $authId;
                    //                         $organizationMedia->modified_id = $authId;
                    //                         $organizationMedia->save();

                    //                         $organizationTrainingMedia = new OrganizationTrainingMedia;
                    //                         $organizationTrainingMedia->training_id = $trainingId;
                    //                         $organizationTrainingMedia->media_id = $organizationMedia->media_id;
                    //                         $organizationTrainingMedia->org_id = $organizationId;
                    //                         $organizationTrainingMedia->is_active = 1;
                    //                         $organizationTrainingMedia->save();

                    //                         $contentLibrarys = ContentLibrary::where('media_id',$trainingMedia->media_id);
                    //                         if($contentLibrarys->count() > 0){
                    //                             foreach($contentLibrarys->get() as $contentLibrary){
                                                    
                    //                                 $organizationContentLibrary = new OrganizationContentLibrary;
                    //                                 $organizationContentLibrary->content_name = $contentLibrary->content_name;
                    //                                 $organizationContentLibrary->content_version = $contentLibrary->content_version;
                    //                                 $organizationContentLibrary->content_types_id = $contentLibrary->content_types_id;
                    //                                 $organizationContentLibrary->media_id = $organizationMedia->media_id;
                    //                                 $organizationContentLibrary->parent_content_id = $contentLibrary->parent_content_id;
                    //                                 $organizationContentLibrary->org_id = $organizationId; 
                    //                                 $organizationContentLibrary->created_id = $authId;
                    //                                 $organizationContentLibrary->modified_id = $authId;
                    //                                 $organizationContentLibrary->save();

                    //                             }
                    //                         }
                    //                     }
                    //                 }
                    //             }
                    //         }  

                    //     }
                    //     if($trainingLibrary->training_type_id == 2){

                    //         $trainingNotificationSettings = TrainingNotificationSetting::where('training_id',$trainingId);
                    //         if($trainingNotificationSettings->count() > 0){
                    //             foreach($trainingNotificationSettings->get() as $trainingNotificationSetting){
                    //                 $organizationTrainingNotificationSetting = new OrganizationTrainingNotificationSetting;
                    //                 $organizationTrainingNotificationSetting->training_notification_id = $trainingNotificationSetting->training_notification_id;
                    //                 $organizationTrainingNotificationSetting->training_id = $trainingId;
                    //                 $organizationTrainingNotificationSetting->notification_on = $trainingNotificationSetting->notification_on;
                    //                 $organizationTrainingNotificationSetting->is_active = $trainingNotificationSetting->is_active;
                    //                 $organizationTrainingNotificationSetting->org_id = $organizationId;
                    //                 $organizationTrainingNotificationSetting->save();
                    //             }
                    //         }

                            

                    //     }
                    //     if($trainingLibrary->training_type_id == 3){

                    //         $assessmentSetting = AssessmentSetting::where('training_id',$trainingId);
                    //         if($assessmentSetting->count() > 0){
                    //             $assessmentSetting = $assessmentSetting->first();

                    //             $organizationAssessmentSetting = new OrganizationAssessmentSetting;
                    //             $organizationAssessmentSetting->training_id = $trainingId;
                    //             $organizationAssessmentSetting->require_passing_score = $assessmentSetting->require_passing_score;
                    //             $organizationAssessmentSetting->passing_percentage = $assessmentSetting->passing_percentage;
                    //             $organizationAssessmentSetting->randomize_questions = $assessmentSetting->randomize_questions;
                    //             $organizationAssessmentSetting->display_type = $assessmentSetting->display_type;
                    //             $organizationAssessmentSetting->hide_after_completed = $assessmentSetting->hide_after_completed;
                    //             $organizationAssessmentSetting->attempt_count = $assessmentSetting->attempt_count;
                    //             $organizationAssessmentSetting->modiflearner_can_view_resultied_id = $assessmentSetting->learner_can_view_result;
                    //             $organizationAssessmentSetting->post_quiz_action = $assessmentSetting->post_quiz_action;
                    //             $organizationAssessmentSetting->pass_fail_status = $assessmentSetting->pass_fail_status;
                    //             $organizationAssessmentSetting->total_score = $assessmentSetting->total_score;
                    //             $organizationAssessmentSetting->correct_incorrect_marked = $assessmentSetting->correct_incorrect_marked;
                    //             $organizationAssessmentSetting->correct_incorrect_ans_marked = $assessmentSetting->correct_incorrect_ans_marked;
                    //             $organizationAssessmentSetting->timer_on = $assessmentSetting->timer_on;
                    //             $organizationAssessmentSetting->hrs = $assessmentSetting->hrs;
                    //             $organizationAssessmentSetting->mins = $assessmentSetting->mins;
                    //             $organizationAssessmentSetting->org_id = $organizationId;
                    //             $organizationAssessmentSetting->created_id = $authId;
                    //             $organizationAssessmentSetting->modified_id = $authId;
                    //             $organizationAssessmentSetting->save();
                    //         }

                    //         $assessmentQuestions = AssessmentQuestion::where('training_id',$trainingId);
                    //         if($assessmentQuestions->count() > 0){
                    //             foreach($assessmentQuestions->get() as $assessmentQuestion){

                    //                 $organizationAssessmentQuestion = new OrganizationAssessmentQuestion;
                    //                 $organizationAssessmentQuestion->training_id = $trainingId;
                    //                 $organizationAssessmentQuestion->question_type_id = $assessmentQuestion->question_type_id;
                    //                 $organizationAssessmentQuestion->question = $assessmentQuestion->question;
                    //                 $organizationAssessmentQuestion->show_ans_random = $assessmentQuestion->show_ans_random;
                    //                 $organizationAssessmentQuestion->number_of_options = $assessmentQuestion->number_of_options;
                    //                 $organizationAssessmentQuestion->org_id = $organizationId;
                    //                 $organizationAssessmentQuestion->save();

                    //                 $questionAnswers = QuestionAnswer::where('training_id',$trainingId)->where('question_id',$assessmentQuestion->question_id);
                    //                 if($questionAnswers->count() > 0){
                    //                     foreach($questionAnswers->get() as $questionAnswer){

                    //                         $organizationQuestionAnswer = new OrganizationQuestionAnswer;
                    //                         $organizationQuestionAnswer->question_id = $organizationAssessmentQuestion->question_id;
                    //                         $organizationQuestionAnswer->options = $questionAnswer->options;
                    //                         $organizationQuestionAnswer->is_correct = $questionAnswer->is_correct;
                    //                         $organizationQuestionAnswer->text_ans = $questionAnswer->text_ans;
                    //                         $organizationQuestionAnswer->numberic_ans = $questionAnswer->numberic_ans;
                    //                         $organizationQuestionAnswer->text_box = $questionAnswer->text_box;
                    //                         $organizationQuestionAnswer->numberic_ans = $questionAnswer->numberic_ans;
                    //                         $organizationQuestionAnswer->org_id = $organizationId;
                    //                         $organizationQuestionAnswer->save();

                    //                     }
                    //                 }
                    //             }
                    //         }
                    //     }
                    // }
                }
            }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Course catalog reset successfully.'],200);

        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }
}
