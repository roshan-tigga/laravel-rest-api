<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\API\BaseController as BaseController;
use DB;

class TruncateTableController extends BaseController
{
    public function truncateTable(){
        $tables = [
            'category_group_assignment',

            'lms_area',
            'lms_division',
            'lms_job_title',
            'lms_location',

            'lms_document_library',
            'lms_document_library_category',
            'lms_enrollment',
            'lms_faq',
            'lms_group_master',
            'lms_group_org',
            'lms_group_org_settings',

            
            'lms_org_assignment_user_course',
            'lms_org_assign_training_library',
            'lms_org_category',
            'lms_org_category_group_assignment',
            'lms_org_certificate',
            'lms_org_classroom_classes',
            'lms_org_classroom_class_sessions',
            'lms_org_credentials',
            'lms_org_credential_custom_field',
            'lms_org_custom_fields',
            'lms_org_custom_number_of_fields',
            'lms_org_general_settings',
            'lms_org_learning_plan',
            'lms_org_learning_plan_requirements',
            
            'lms_org_resources',
            'lms_org_content_library',
            'lms_org_training_custom_field',

            'lms_org_skills',
           
            
            'lms_org_team_approvals',
            'lms_org_team_credit',
            'lms_tag',
            'lms_team_approvals',
            'lms_team_credit',

            'lms_org_roles',
            'lms_org_notification',
            'lms_org_media',
            'lms_notification_master',
            'lms_media',
            'lms_image',

            'lms_org_training_library',
            'lms_org_question_answer',
            'lms_org_assessment_question',
            'lms_org_assessment_settings',
            'lms_org_training_handouts',
            'lms_org_training_media',
            'lms_org_training_notifications_settings',
            'lms_org_sco_details',
            'lms_org_sco_menisfest_reader',
            'lms_org_sco_track',

            'lms_org_user_category_assignment',
            
            'lms_resources',
            'lms_org_user_custom_field',

            'lms_training_handouts',
            'lms_training_media',
            'lms_training_notifications_settings',
            'lms_category_master',
            'lms_certificate_master',
            'lms_content_library',
            'lms_assessment_question',
            'lms_assessment_settings',
            'lms_sco_details',
            'lms_sco_menisfest_reader',
            'lms_sco_track',
            'lms_question_answer',
            'lms_training_library',
            'lms_user_category',
            'lms_user_document',
            'lms_user_group',
            'lms_user_media',
            'lms_user_notification',
            'lms_user_org_group',
            'lms_user_requirement_courses',
            'lms_user_transcript'];
        foreach($tables as $table){
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            DB::table($table)->truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        DB::table('lms_domain')->where('domain_id','!=','1')->delete();
        DB::table('lms_org_master')->where('org_id','!=','1')->delete();
        DB::table('lms_user_master')->where('org_id','!=','1')->delete();
        DB::table('lms_user_login')->where('org_id','!=','1')->delete();

        return response()->json(['status'=>true,'code'=>200,'message'=>'Truncate table successfully.'],200);
    }
}
