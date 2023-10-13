<?php

namespace App\Http\Controllers\API;

use App\Models\Transcript;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use DB;

class TranscriptController extends BaseController
{

    public function getTanscriptList(){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $transcripts = Transcript::
        leftJoin('lms_org_training_library as trainingLibrary','lms_user_transcript.training_id','=','trainingLibrary.training_id')
        ->leftJoin('lms_training_types as trainingTypes','trainingLibrary.training_type_id','=','trainingTypes.training_type_id')
        ->leftJoin('lms_user_requirement_courses as requirement','lms_user_transcript.training_id','=','requirement.training_id')
        //->leftJoin('lms_certificate_master as certificate','trainingLibrary.certificate_id','=','certificate.certificate_id')
        ->where('lms_user_transcript.user_id',$authId)->where('lms_user_transcript.is_active','1')
        ->where('lms_user_transcript.org_id',$organizationId)
        ->where('trainingLibrary.org_id',$organizationId)
        ->select('lms_user_transcript.transcript_id as transcriptId','trainingLibrary.training_name as courseName',
        DB::raw('(CASE WHEN lms_user_transcript.status = 1 THEN "Completed"  ELSE "InProgress" END) AS status'),
        'lms_user_transcript.credit',
        'lms_user_transcript.date_created as dateCreated',
        'requirement.due_date as dueDate',
        //'certificate.cert_structure as certStructure',
        //'certificate.bgimage as certificateImage',
        'lms_user_transcript.certificate_link as certificateLink',
        'lms_user_transcript.notes',
        'lms_user_transcript.result',
        'trainingTypes.training_type as trainingType'
        )
        ->get();

        if($transcripts->count() > 0){
            foreach($transcripts as $transcript){
                //$transcript->certStructure = dynamicField($transcript->certStructure,$authId);
                if($transcript->certificateLink != ''){
                    $transcript->certificateLink = getFileS3Bucket(getPathS3Bucket().'/user/certificate/'.$transcript->certificateLink);
                }
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$transcripts],200);
    }

    public function getTanscriptById($tanscriptId){
        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;

        $transcripts = Transcript::
        leftJoin('lms_org_training_library as trainingLibrary','lms_user_transcript.training_id','=','trainingLibrary.training_id')
        ->leftJoin('lms_training_types as trainingTypes','trainingLibrary.training_type_id','=','trainingTypes.training_type_id')
        ->leftJoin('lms_user_requirement_courses as requirement','lms_user_transcript.training_id','=','requirement.training_id')
        //->leftJoin('lms_certificate_master as certificate','trainingLibrary.certificate_id','=','certificate.certificate_id')
        ->where('lms_user_transcript.user_id',$authId)->where('lms_user_transcript.is_active','1')
        ->where('lms_user_transcript.org_id',$organizationId)
        ->where('trainingLibrary.org_id',$organizationId)
        ->select('lms_user_transcript.transcript_id as transcriptId','trainingLibrary.training_name as courseName',
        DB::raw('(CASE WHEN lms_user_transcript.status = 1 THEN "Completed" ELSE "InProgress" END) AS status'),
        'lms_user_transcript.credit',
        'lms_user_transcript.date_created as dateCreated',
        'requirement.due_date as dueDate',
        //'certificate.cert_structure as certStructure',
        //'certificate.bgimage as certificateImage',
        'lms_user_transcript.certificate_link as certificateLink',
        'lms_user_transcript.notes',
        'lms_user_transcript.result',
        'trainingTypes.training_type as trainingType'
        )
        ->find($tanscriptId);

        if($transcripts){
            if($transcripts->certificateLink != ''){
                $transcripts->certificateLink = getFileS3Bucket(getPathS3Bucket().'/user/certificate/'.$transcripts->certificateLink);
            }
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$transcripts],200);
    }

}
