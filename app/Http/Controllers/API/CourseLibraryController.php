<?php

namespace App\Http\Controllers\API;

use App\Models\CourseLibrary;
use Illuminate\Http\Request;
use Validator;
use App\Http\Controllers\API\BaseController as BaseController;
use Auth;
use Illuminate\Support\Facades\Redis;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class CourseLibraryController extends BaseController
{
    public function getCourseLibraryList(Request $request){
        $organizationId = Auth::user()->org_id;

        $sort = $request->has('sort') ? $request->get('sort') : 'course.org_course_catalog_id';
        $order = $request->has('order') ? $request->get('order') : 'DESC';
        $search = $request->has('search') ? $request->get('search') : '';

        $sortColumn = $sort;
        if($sort == 'courseCode'){
            $sortColumn = 'course.course_code';
        }elseif($sort == 'trainingType'){
            $sortColumn = 'course.training_type';
        }elseif($sort == 'courseName'){
            $sortColumn = 'course.course_name';
        }elseif($sort == 'categoryName'){
            $sortColumn = 'category.category_name';
        }elseif($sort == 'status'){
            $sortColumn = 'course.status';
        }elseif($sort == 'isActive'){
            $sortColumn = 'course.is_active';
        }

        $courseCatalogs = DB::table('lms_org_course_catalog as course')
        ->join('lms_category_master as category','course.category_id','=','category.category_id')
        ->where('course.is_active','!=','0')->where('course.org_id',$organizationId)
        ->where(function($query) use ($search){
            if($search != ''){
                $query->where('course.course_code', 'LIKE', '%'.$search.'%');
                $query->orWhere('course.training_type', 'LIKE', '%'.$search.'%');
                $query->orWhere('course.course_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('category.category_name', 'LIKE', '%'.$search.'%');
                $query->orWhere('course.status', 'LIKE', '%'.$search.'%');
                if(in_array($search,['active','act','acti','activ'])){
                    $query->orWhere('course.is_active','1');
                }
                if(in_array($search,['inactive','inact','inacti','inactiv'])){
                    $query->orWhere('course.is_active','2');
                }
            }
        })
        ->select('course.org_course_catalog_id as courseCatalogId','course.course_code as courseCode','course.training_type as trainingType','course.course_name as courseName','category.category_name as categoryName','course.status','course.is_active as isActive')
        ->get();
        return response()->json(['status'=>true,'code'=>200,'data'=>$courseCatalogs],200);
    }

    function courseCode(){
        $organization = CourseLibrary::count();
        if($organization > 0){
            $course_code = CourseLibrary::orderBy('course_code','DESC')->select('course_code')->first()->course_code;
            $code = $course_code+1;
        }else{
            $code = '110001';
        }
        return $code;
    }

    public function addCourseLibrary(Request $request){

        $organizationId = Auth::user()->org_id;
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'trainingType' => 'required|integer',
            'courseName' => 'required|max:255',
            'courseTitle' => 'nullable|max:255',
            'quizType' => 'nullable|integer',
            'category' => 'nullable|integer',
            'trainingContent' => 'nullable|integer',
            'referenceCode' => 'nullable',
            'courseImage' => 'nullable',
            'credit' => 'nullable|integer',
            'creditVisibility' => 'nullable|integer',
            'point' => 'nullable|integer',
            'pointVisibility' => 'nullable|integer',
            'certificate_id' => 'nullable|integer',
            'iltAssessment' => 'nullable|integer',
            'activityReview' => 'nullable|integer',
            'enrollmentType' => 'nullable|integer',
            'unenrollment' => 'nullable|integer',
            'isActive' => 'nullable|integer',
            'status' => 'nullable|integer',
            'passingScore' => 'nullable',
            'sslForAicc' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{



            $trainingType = $request->trainingType;
            $courseCatalogId = '';
            if($trainingType == 1){

                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/elearing';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = new CourseLibrary;
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_code = $this->courseCode();
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->course_title = $request->courseTitle;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->training_content = $request->trainingContent;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;

                $courseCatalog->passing_score = $request->passingScore;
                $courseCatalog->ssl_for_aicc = $request->sslForAicc;
                $courseCatalog->status = $request->status;
                $courseCatalog->org_id = $organizationId;

                $courseCatalog->time = date('H');
                $courseCatalog->due_date = Carbon::now();

                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }elseif($trainingType == 2){
                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/virtual_classroom/classroom ';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = new CourseLibrary;
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_code = $this->courseCode();
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;
                $courseCatalog->ilt_assessment = $request->iltAssessment;
                $courseCatalog->activity_review = $request->activityReview;
                $courseCatalog->enrollment_type = $request->enrollmentType;
                $courseCatalog->unenrollment = $request->unenrollment;
                $courseCatalog->status = $request->status;
                $courseCatalog->org_id = $organizationId;
                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }elseif($trainingType == 3){
                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/assessment ';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = new CourseLibrary;
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_code = $this->courseCode();
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->quiz_type = $request->quizType;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->training_content = $request->trainingContent;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;
                $courseCatalog->status = $request->status;
                $courseCatalog->org_id = $organizationId;
                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }else{
                return response()->json(['status'=>true,'code'=>400,'error'=>'Course not create.'],400);
            }

            // if($courseCatalogId != ''){
            //     if(isset($request->skills)){
            //         $skills = [];
            //         foreach($request->skills as $skill){
            //             $skills[] = [
            //                 'skill_id' => $skill['skill'],
            //                 'level' => $skill['level'],
            //                 'credit' => $skill['credit'],
            //                 'course_catalog_id' => $courseCatalogId,
            //                 'created_id' => $authId,
            //                 'modified_id' => $authId
            //             ];
            //         }

            //         if(!empty($skills)){
            //             DB::table("lms_course_skill")->insert($skills);
            //         }

            //     }
            // }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Course has been created successfully.'],200);
        
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function getCourseLibraryById($courseCatalogById){
        $organizationId = Auth::user()->org_id;
        $courseCatalog = DB::table('lms_org_course_catalog as course_catalog')
        ->leftJoin('lms_category_master as category','course_catalog.category_id','=','category.category_id')
        ->leftJoin('lms_certificate_master as certificate','course_catalog.certificate_id','=','certificate.certificate_id')
        ->where('course_catalog.org_id',$organizationId)
        ->where('course_catalog.is_active','!=','0')->where('course_catalog.org_course_catalog_id',$courseCatalogById)
        ->select('course_catalog.org_course_catalog_id as courseCatalogId','course_catalog.training_type as trainingType','course_catalog.course_name as courseName','course_catalog.course_title as courseTitle','course_catalog.quiz_type as quizType','course_catalog.description','course_catalog.category_id as categoryId','category.category_name as categoryName','course_catalog.training_Content as trainingContent','course_catalog.reference_code as referenceCode','course_catalog.course_image as courseImage','course_catalog.credit','course_catalog.credit_visibility as creditVisibility','course_catalog.point','course_catalog.point_visibility as pointVisibility','course_catalog.certificate_id as certificateId','certificate.certificate_name as certificateName','course_catalog.ilt_assessment as iltAssessment','course_catalog.activity_review as activityReview','course_catalog.enrollment_type as enrollmentType','course_catalog.unenrollment','course_catalog.passing_score as passingScore','course_catalog.ssl_for_aicc as sslForAicc','course_catalog.status','course_catalog.is_active as isActive')
        ->first();
        if($courseCatalog->courseImage != ''){
            if($courseCatalog->trainingType == 1){
                $courseCatalog->courseImage = getFileS3Bucket(getPathS3Bucket().'/courses/elearing/'.$courseCatalog->courseImage);
            }
            if($courseCatalog->trainingType == 2){
                $courseCatalog->courseImage = getFileS3Bucket(getPathS3Bucket().'/courses/virtual_classroom/classroom/'.$courseCatalog->courseImage);
            }
            if($courseCatalog->trainingType == 3){
                $courseCatalog->courseImage = getFileS3Bucket(getPathS3Bucket().'/courses/assessment/'.$courseCatalog->courseImage);
            }  
        }
        return response()->json(['status'=>true,'code'=>200,'data'=>$courseCatalog],200);
    }

    public function updateCourseLibrary(Request $request){
        $authId = Auth::user()->user_id;
        $validator = Validator::make($request->all(), [
            'trainingType' => 'required|integer',
            'courseName' => 'required|max:255',
            'courseTitle' => 'nullable|max:255',
            'quizType' => 'nullable|integer',
            'category' => 'nullable|integer',
            'trainingContent' => 'nullable|integer',
            'referenceCode' => 'nullable',
            'courseImage' => 'nullable',
            'credit' => 'nullable|integer',
            'creditVisibility' => 'nullable|integer',
            'point' => 'nullable|integer',
            'pointVisibility' => 'nullable|integer',
            'certificate_id' => 'nullable|integer',
            'iltAssessment' => 'nullable|integer',
            'activityReview' => 'nullable|integer',
            'enrollmentType' => 'nullable|integer',
            'unenrollment' => 'nullable|integer',
            'isActive' => 'nullable|integer',
            'status' => 'nullable|integer',
            'passingScore' => 'nullable',
            'sslForAicc' => 'nullable|integer'
        ]);

        if ($validator->fails())
        {
            return response()->json(['status'=>false,'code'=>400,'errors'=>$validator->errors()->all()], 400);
        }

        try{

            $trainingType = $request->trainingType;
            $courseCatalogId = '';
            if($trainingType == 1){

                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/elearing';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = CourseLibrary::findOrFail($request->courseCatalogId);
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->course_title = $request->courseTitle;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->training_content = $request->trainingContent;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;

                $courseCatalog->passing_score = $request->passingScore;
                $courseCatalog->ssl_for_aicc = $request->sslForAicc;
                $courseCatalog->status = $request->status;

                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }elseif($trainingType == 2){
                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/virtual_classroom/classroom ';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = CourseLibrary::findOrFail($request->courseCatalogId);
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;
                $courseCatalog->ilt_assessment = $request->iltAssessment;
                $courseCatalog->activity_review = $request->activityReview;
                $courseCatalog->enrollment_type = $request->enrollmentType;
                $courseCatalog->unenrollment = $request->unenrollment;
                $courseCatalog->status = $request->status;
                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }elseif($trainingType == 3){
                $courseImage = '';
                if($request->file('courseImage') != ''){
                    $path = getPathS3Bucket().'/courses/assessment ';
                    $s3CourseImage = Storage::disk('s3')->put($path, $request->courseImage);
                    $courseImage = substr($s3CourseImage, strrpos($s3CourseImage, '/') + 1);
                }

                $courseCatalog = CourseLibrary::findOrFail($request->courseCatalogId);
                $courseCatalog->training_type = $request->trainingType;
                $courseCatalog->course_name = $request->courseName;
                $courseCatalog->quiz_type = $request->quizType;
                $courseCatalog->description = $request->description;
                $courseCatalog->category_id = $request->category;
                $courseCatalog->training_content = $request->trainingContent;
                $courseCatalog->reference_code = $request->referenceCode;
                $courseCatalog->course_image = $courseImage;
                $courseCatalog->credit = $request->credit;
                $courseCatalog->credit_visibility = $request->creditVisibility;
                $courseCatalog->point = $request->point;
                $courseCatalog->point_visibility = $request->pointVisibility;
                $courseCatalog->certificate_id = $request->certificate;
                $courseCatalog->status = $request->status;
                $courseCatalog->is_active = $request->isActive == '' ? '1' : $request->isActive;
                $courseCatalog->created_id = $authId;
                $courseCatalog->modified_id = $authId;
                $courseCatalog->save();

                //$courseCatalogId = $courseCatalog->course_catalog_id;

            }else{
                return response()->json(['status'=>true,'code'=>400,'error'=>'Course not create.'],400);
            }

            // if($courseCatalogId != ''){
            //     if(isset($request->skills)){
            //         $skills = [];
            //         foreach($request->skills as $skill){
            //             $skills[] = [
            //                 'skill_id' => $skill['skill'],
            //                 'level' => $skill['level'],
            //                 'credit' => $skill['credit'],
            //                 'course_catalog_id' => $courseCatalogId,
            //                 'created_id' => $authId,
            //                 'modified_id' => $authId
            //             ];
            //         }

            //         if(!empty($skills)){
            //             DB::table("lms_course_skill")->insert($skills);
            //         }

            //     }
            // }
            return response()->json(['status'=>true,'code'=>200,'message'=>'Course has been updated successfully.'],200);
        
        } catch (\Throwable $e) {
            return response()->json(['status'=>false,'code'=>501,'message'=>$e->getMessage()],501);
        }
    }

    public function deleteCourseLibrary(Request $request){
        $organizationId = Auth::user()->org_id;
        CourseLibrary::where('org_course_catalog_id',$request->courseCatalogId)->where('org_id',$organizationId)->update([
            'is_active' => 0
        ]);

        return response()->json(['status'=>true,'code'=>200,'message'=>'Course has been deleted successfully.'],200);
    }
}
