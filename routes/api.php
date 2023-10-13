<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\PassportAuthController;
use App\Http\Controllers\API\VerifyEmailController;
use App\Http\Controllers\API\ForgotPasswordController;
use App\Http\Controllers\API\DomainController;
use App\Http\Controllers\API\OrganizationController;
use App\Http\Controllers\API\UserController;
use App\Http\Controllers\API\CompanyController;
use App\Http\Controllers\API\CategoryMasterController;
use App\Http\Controllers\API\SkillMasterController;
use App\Http\Controllers\API\NotificationMasterController;
use App\Http\Controllers\API\AnnouncementController;
use App\Http\Controllers\API\CertificateMasterController;
use App\Http\Controllers\API\OrganizationCertificateController;
use App\Http\Controllers\API\ThemeMasterController;
use App\Http\Controllers\API\ContentTypeController;
use App\Http\Controllers\API\MediaController;
use App\Http\Controllers\API\ContentLibraryController;
use App\Http\Controllers\API\GroupSettingController;
use App\Http\Controllers\API\OrgGroupSettingController;
use App\Http\Controllers\API\GroupMasterController;
use App\Http\Controllers\API\OrganizationGroupController;
use App\Http\Controllers\API\ModuleMasterController;
use App\Http\Controllers\API\OrganizationTypeController;
use App\Http\Controllers\API\TagController;
use App\Http\Controllers\API\MenuMasterController;
use App\Http\Controllers\API\RoleController;
use App\Http\Controllers\API\ActionsMasterController;
use App\Http\Controllers\API\PermissionController;
use App\Http\Controllers\API\MenuController;
use App\Http\Controllers\API\MenuPermissionController;
use App\Http\Controllers\API\ActionsPermissionController;
use App\Http\Controllers\API\SubAdminController;
use App\Http\Controllers\API\InstructorController;
use App\Http\Controllers\API\DynamicFieldController;
use App\Http\Controllers\API\DynamicLinkController;
use App\Http\Controllers\API\OrganizationNotificationController;
use App\Http\Controllers\API\CourseCatalogController;
use App\Http\Controllers\API\CourseLibraryController;
use App\Http\Controllers\API\RequirementController;
use App\Http\Controllers\API\EnrollmentController;
use App\Http\Controllers\API\ActivityController;
use App\Http\Controllers\API\OrganizationCategoryController;
use App\Http\Controllers\API\OrganizationSkillController;
use App\Http\Controllers\API\UserGroupController;
use App\Http\Controllers\API\TrainingTypeController;
use App\Http\Controllers\API\TrainingStatusController;
use App\Http\Controllers\API\TrainingLibraryController;
use App\Http\Controllers\API\ScormController;
use App\Http\Controllers\API\TrainingNotificationController;
use App\Http\Controllers\API\IltEnrollmentController;
use App\Http\Controllers\API\QuestionTypeController;
use App\Http\Controllers\API\TeamApprovalController;
use App\Http\Controllers\API\TeamCreditController;
use App\Http\Controllers\API\MyTeamController;
use App\Http\Controllers\API\OrganizationAssignTrainingLibraryController;
use App\Http\Controllers\API\TrainingAssignController;
use App\Http\Controllers\API\UserNotificationController;
use App\Http\Controllers\API\IconController;
use App\Http\Controllers\API\GroupOrganizationController;
use App\Http\Controllers\API\DocumentLibraryController;
use App\Http\Controllers\API\FaqController;
use App\Http\Controllers\API\StudentCatalogController;
use App\Http\Controllers\API\TranscriptController;
use App\Http\Controllers\API\DashboardController;
use App\Http\Controllers\API\OrganizationMediaController;
use App\Http\Controllers\API\UserDocumentController;
use App\Http\Controllers\API\OrganizationRoleController;
use App\Http\Controllers\API\AreaController;
use App\Http\Controllers\API\LocationController;
use App\Http\Controllers\API\DivisionController;
use App\Http\Controllers\API\JobTitleController;
use App\Http\Controllers\API\CredentialController;
use App\Http\Controllers\API\NotificationCategoryController;
use App\Http\Controllers\API\NotificationEventController;
use App\Http\Controllers\API\GeneralSettingController;
use App\Http\Controllers\API\OrganizationCustomFieldController;
use App\Http\Controllers\API\ClassRoomClassController;
use App\Http\Controllers\API\OrganizationLearningPlanController;
use App\Http\Controllers\API\TruncateTableController;

use App\Http\Middleware\LastActivity;
use App\Http\Middleware\SuperAdmin;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });



Route::group(['middleware' => ['cors', 'log.route']], function () {

    // Verify email
    //Route::get('/email/verify/{id}/{hash}', [VerifyEmailController::class, 'verifyEmail'])->middleware(['signed'])->name('verification.verify');
    //Route::get('email/resend', [VerifyEmailController::class, 'resendEmail'])->name('verification.resend');

    Route::post('login', [PassportAuthController::class, 'login']);
    Route::post('verifyOrganization', [OrganizationController::class, 'verifyOrganization']);

    //Route::get('clear-token', [PassportAuthController::class, 'clear_token']);

    Route::post('forgotPassword', [ForgotPasswordController::class, 'forgotPassword']);
    Route::post('resetPassword', [ForgotPasswordController::class, 'resetPassword']);
    //add this middleware to ensure that every request is authenticated

    Route::post('getAnnouncementNotificationList', [AnnouncementController::class, 'getAnnouncementNotificationList']);

    Route::middleware('auth:api')->group(function () {

        Route::get('getIconList', [IconController::class, 'getIconList']);

        Route::post('logout', [PassportAuthController::class, 'logout']);
        Route::post('previewOrganization', [PassportAuthController::class, 'previewOrganization']);
        Route::post('scormPreview', [PassportAuthController::class, 'scormPreview']);

        //Route::middleware([LastActivity::class])->group(function(){

        Route::post('verify_token', [PassportAuthController::class, 'verify_token']);
        //Route::post('activity', [ActivityController::class, 'activity']);


        /**********************Start Domain*****************/
        Route::get('getDomainList', [DomainController::class, 'getDomainList']);
        Route::get('getDomainById/{domainId}', [DomainController::class, 'getDomainById']);
        //Route::put('updateDomainById', [DomainController::class, 'updateDomainById']);
        /*********************End Domain*********************/


        Route::post('getUserCompanyList', [OrganizationController::class, 'getUserCompanyList']);

        Route::middleware(SuperAdmin::class)->group(function () {
            /************************Start Organization*****************/
            Route::get('getCompanyList', [OrganizationController::class, 'getOrganizationList']);
            Route::post('addNewCompany', [OrganizationController::class, 'addNewOrganization']);
            Route::get('getOrganizationById/{organizationId}', [OrganizationController::class, 'getOrganizationById']);
            Route::put('updateOrganizationById', [OrganizationController::class, 'updateOrganizationById']);
            Route::put('updateOrganization', [OrganizationController::class, 'updateOrganization']);
            Route::get('getOrganization', [OrganizationController::class, 'getOrganization']);
        });

        Route::get('getPrimaryOrganizationList', [OrganizationController::class, 'getPrimaryOrganizationList']);
        Route::post('getPrimaryOrganizationDomain', [OrganizationController::class, 'getPrimaryOrganizationDomain']);
        Route::get('getCountryList', [OrganizationController::class, 'getCountryList']);
        Route::get('getOrganizationOptionsList', [OrganizationController::class, 'getOrganizationOptionsList']);
        Route::get('parentChildOrgList', [OrganizationController::class, 'parentChildOrgList']);
        Route::get('getAuthenticationType', [OrganizationController::class, 'getAuthenticationType']);

        /************************End Organization*********************/


        /************************Start User*****************/
        Route::get('getUserList', [UserController::class, 'getUserList']);
        Route::post('addNewUser', [UserController::class, 'addNewUser']);
        Route::get('getUserById/{userId}', [UserController::class, 'getUserById']);
        Route::get('viewUserById/{userId}', [UserController::class, 'viewUserById']);
        Route::put('updateUser', [UserController::class, 'updateUser']);
        Route::put('updateProfileDetails', [UserController::class, 'updateProfileDetails']);
        Route::delete('deleteUser', [UserController::class, 'deleteUser']);
        Route::delete('permanentDeleteUser', [UserController::class, 'permanentDeleteUser']);
        Route::post('archiveUser', [UserController::class, 'archiveUser']);
        Route::post('activeUser', [UserController::class, 'activeUser']);
        Route::post('userImport', [UserController::class, 'userImport']);

        Route::post('inactiveUser', [UserController::class, 'inactiveUser']);
        Route::post('bulkInactiveUser', [UserController::class, 'bulkInactiveUser']);

        Route::post('getUserListByOrgId', [UserController::class, 'getUserListByOrgId']);

        Route::delete('bulkDeleteUser', [UserController::class, 'bulkDeleteUser']);
        Route::post('bulkArchiveUser', [UserController::class, 'bulkArchiveUser']);
        Route::get('getUserListByRoleId/{roleId}', [UserController::class, 'getUserListByRoleId']);

        Route::get('getProfileDetails', [UserController::class, 'getProfileDetails']);

        Route::post('userNotificationAssignment', [UserController::class, 'userNotificationAssignment']);
        Route::get('getSupervisorUserList', [UserController::class, 'getSupervisorUserList']);

        //Route::post('getUserListByGroupId',[UserController::class,'getUserListByGroupId']);
        /************************End User*********************/


        /************************Start Sub Admin*****************/
        Route::get('getSubAdminList', [SubAdminController::class, 'getSubAdminList']);
        Route::get('getSubAdminUserList', [SubAdminController::class, 'getSubAdminUserList']);
        Route::delete('bulkDeleteSubAdmin', [SubAdminController::class, 'bulkDeleteSubAdmin']);
        Route::post('userToSubAdmin', [SubAdminController::class, 'userToSubAdmin']);
        Route::get('getRoleListForSubadmin', [SubAdminController::class, 'getRoleListForSubadmin']);
        Route::post('unAssignRole', [SubAdminController::class, 'unAssignRole']);

        Route::post('orgUserCategoryAssignment', [SubAdminController::class, 'orgUserCategoryAssignment']);
        Route::get('getOrgUserCategoryUnassignmentList/{userId}', [SubAdminController::class, 'getOrgUserCategoryUnassignmentList']);

        /************************End Sub Admin*********************/

        /************************Start Instructor Admin*****************/
        Route::get('getInstructorList', [InstructorController::class, 'getInstructorList']);
        Route::get('getInstructorUserList', [InstructorController::class, 'getInstructorUserList']);
        Route::delete('bulkDeleteInstructor', [InstructorController::class, 'bulkDeleteInstructor']);
        Route::post('userToInstructor', [InstructorController::class, 'userToInstructor']);
        Route::get('getRoleListForInstructor', [InstructorController::class, 'getRoleListForInstructor']);
        /************************End Instructor Admin*********************/


        /************************Start Category*****************/
        Route::get('getGenericCategoryList', [CategoryMasterController::class, 'getGenericCategoryList']);
        Route::get('getPrimaryCategoryList', [CategoryMasterController::class, 'getPrimaryCategoryList']);
        Route::post('addNewCategory', [CategoryMasterController::class, 'addNewCategory']);
        Route::get('getCategoryById/{categoryId}', [CategoryMasterController::class, 'getCategoryById']);
        Route::put('updateCategoryById', [CategoryMasterController::class, 'updateCategoryById']);
        Route::delete('deleteCategory', [CategoryMasterController::class, 'deleteCategory']);
        Route::delete('bulkDeleteGenericCategory', [CategoryMasterController::class, 'bulkDeleteGenericCategory']);

        Route::get('getCategoryOptionList', [CategoryMasterController::class, 'getCategoryOptionList']);

        Route::post('getUserCategoryList', [CategoryMasterController::class, 'getUserCategoryList']);
        Route::post('addUserCategory', [CategoryMasterController::class, 'addUserCategory']);

        Route::post('courseCategoryUnassign', [CategoryMasterController::class, 'courseCategoryUnassign']);
        Route::post('bulkCourseCategoryAssign', [CategoryMasterController::class, 'bulkCourseCategoryAssign']);

        Route::post('getAssignCourseListByCategoryId', [CategoryMasterController::class, 'getAssignCourseListByCategoryId']);
        Route::post('getUnassignCourseListByCategoryId', [CategoryMasterController::class, 'getUnassignCourseListByCategoryId']);

        Route::post('courseCategoryAssign', [CategoryMasterController::class, 'courseCategoryAssign']);
        Route::post('categoryGroupAssignment', [CategoryMasterController::class, 'categoryGroupAssignment']);
        Route::post('categoryAssignToOrgCategory', [CategoryMasterController::class, 'categoryAssignToOrgCategory']);
        /************************End Category*********************/

        /************************Start Skill*****************/
        Route::get('getSkillList', [SkillMasterController::class, 'getSkillList']);
        Route::post('addNewSkill', [SkillMasterController::class, 'addNewSkill']);
        Route::get('getSkillById/{skillId}', [SkillMasterController::class, 'getSkillById']);
        Route::put('updateSkillById', [SkillMasterController::class, 'updateSkillById']);
        Route::delete('deleteSkill', [SkillMasterController::class, 'deleteSkill']);
        /************************End Skill*********************/

        /************************Start Notification*****************/
        Route::get('getNotificationList', [NotificationMasterController::class, 'getNotificationList']);
        Route::post('addNewNotification', [NotificationMasterController::class, 'addNewNotification']);
        Route::get('getNotificationById/{notificationId}', [NotificationMasterController::class, 'getNotificationById']);
        Route::put('updateNotificationById', [NotificationMasterController::class, 'updateNotificationById']);
        Route::delete('deleteNotification', [NotificationMasterController::class, 'deleteNotification']);
        Route::delete('bulkDeleteNotification', [NotificationMasterController::class, 'bulkDeleteNotification']);
        Route::get('getNotificationOptionList', [NotificationMasterController::class, 'getNotificationOptionList']);
        Route::get('getDisplayNotificationList', [NotificationMasterController::class, 'getDisplayNotificationList']);
        Route::post('notificationAssignToOrgNotification', [NotificationMasterController::class, 'notificationAssignToOrgNotification']);
        Route::get('getNotificationListByOrgId/{organizationId}', [NotificationMasterController::class, 'getNotificationListByOrgId']);
        Route::post('bulkUpdateOrgNotification', [NotificationMasterController::class, 'bulkUpdateOrgNotification']);
        /************************End Notification*********************/

        /************************Start Organization Notification*****************/
        Route::get('getOrganizationNotificationList', [OrganizationNotificationController::class, 'getOrganizationNotificationList']);
        Route::post('addOrganizationNotification', [OrganizationNotificationController::class, 'addOrganizationNotification']);
        Route::get('getOrganizationNotificationById/{organizationNotificationId}', [OrganizationNotificationController::class, 'getOrganizationNotificationById']);
        Route::put('updateOrganizationNotification', [OrganizationNotificationController::class, 'updateOrganizationNotification']);
        Route::delete('deleteOrganizationNotification', [OrganizationNotificationController::class, 'deleteOrganizationNotification']);
        Route::delete('bulkDeleteOrgNotification', [OrganizationNotificationController::class, 'bulkDeleteOrgNotification']);
        Route::post('resetOrganizationNotification', [OrganizationNotificationController::class, 'resetOrganizationNotification']);
        Route::get('displayNotification', [OrganizationNotificationController::class, 'displayNotification']);
        Route::get('getOrgNotificationOptionList', [OrganizationNotificationController::class, 'getOrgNotificationOptionList']);
        Route::get('getDisplayOrgNotificationList', [OrganizationNotificationController::class, 'getDisplayOrgNotificationList']);
        Route::post('activeInactiveNotification', [OrganizationNotificationController::class, 'activeInactiveNotification']);
        /************************End Organization Notification*********************/

        /************************Start Announcement*****************/
        Route::get('getAnnouncementList', [AnnouncementController::class, 'getAnnouncementList']);
        Route::post('addNewAnnouncement', [AnnouncementController::class, 'addNewAnnouncement']);
        Route::get('getAnnouncementById/{announcementId}', [AnnouncementController::class, 'getAnnouncementById']);
        Route::put('updateAnnouncementById', [AnnouncementController::class, 'updateAnnouncementById']);
        Route::delete('deleteAnnouncement', [AnnouncementController::class, 'deleteAnnouncement']);
        /************************End Announcement*********************/


        /************************Start Certificate*****************/
        Route::get('getCertificateList', [CertificateMasterController::class, 'getCertificateList']);
        Route::post('addNewCertificate', [CertificateMasterController::class, 'addNewCertificate']);
        Route::get('getCertificateById/{certificateId}', [CertificateMasterController::class, 'getCertificateById']);
        Route::put('updateCertificateById', [CertificateMasterController::class, 'updateCertificateById']);
        Route::delete('deleteCertificate', [CertificateMasterController::class, 'deleteCertificate']);
        Route::get('getCertificateOptionList', [CertificateMasterController::class, 'getCertificateOptionList']);
        Route::get('getCertificateAssignedToOrganizationList/{certificateId}', [CertificateMasterController::class, 'getCertificateAssignedToOrganizationList']);
        Route::post('certificateAssignToOrgCertificate', [CertificateMasterController::class, 'certificateAssignToOrgCertificate']);
        Route::post('bulkCertificateAssignToOrgCertificate', [CertificateMasterController::class, 'bulkCertificateAssignToOrgCertificate']);
        Route::get('getCertificateListByOrgId/{organizationId}', [CertificateMasterController::class, 'getCertificateListByOrgId']);
        Route::post('bulkUpdateOrgCertificate', [CertificateMasterController::class, 'bulkUpdateOrgCertificate']);
        /************************End Certificate*********************/

        /************************Start Certificate*****************/
        Route::get('getOrgCertificateList', [OrganizationCertificateController::class, 'getOrgCertificateList']);
        Route::post('addNewOrgCertificate', [OrganizationCertificateController::class, 'addNewOrgCertificate']);
        Route::get('getOrgCertificateById/{certificateId}', [OrganizationCertificateController::class, 'getOrgCertificateById']);
        Route::put('updateOrgCertificateById', [OrganizationCertificateController::class, 'updateOrgCertificateById']);
        Route::delete('deleteOrgCertificate', [OrganizationCertificateController::class, 'deleteOrgCertificate']);
        Route::get('getOrgCertificateOptionList', [OrganizationCertificateController::class, 'getOrgCertificateOptionList']);
        /************************End Certificate*********************/

        /************************Start Theme*****************/
        Route::get('getThemeList', [ThemeMasterController::class, 'getThemeList']);
        //Route::post('addNewTheme', [ThemeMasterController::class, 'addNewTheme']);
        //Route::get('getThemeById/{themeId}', [ThemeMasterController::class, 'getThemeById']);
        //Route::put('updateThemeById', [ThemeMasterController::class, 'updateThemeById']);
        //Route::delete('deleteTheme', [ThemeMasterController::class, 'deleteTheme']);
        Route::post('setTheme', [ThemeMasterController::class, 'setTheme']);
        /************************End Theme*********************/

        /************************Start Content Type*****************/
        Route::get('getContentTypeList', [ContentTypeController::class, 'getContentTypeList']);
        Route::get('getContentTypeById/{contentTypeId}', [ContentTypeController::class, 'getContentTypeById']);
        /************************End Content Type*********************/

        /************************Start Media*****************/
        Route::get('getMediaList', [MediaController::class, 'getMediaList']);
        Route::post('addMedia', [MediaController::class, 'addMedia']);
        Route::get('getMediaById/{contentId}', [MediaController::class, 'getMediaById']);
        Route::put('updateMedia', [MediaController::class, 'updateMedia']);
        Route::delete('deleteMedia', [MediaController::class, 'deleteMedia']);
        //Route::post('playMedia', [MediaController::class, 'playMedia']);
        Route::get('getMediaOptionList', [MediaController::class, 'getMediaOptionList']);


        Route::get('getMediaCourseLibraryById/{mediaId}', [MediaController::class, 'getMediaCourseLibraryById']);
        Route::delete('deleteMediaCourseLibrary', [MediaController::class, 'deleteMediaCourseLibrary']);

        /************************End Media*********************/

        /************************Start Content*****************/
        Route::post('getContentVersion', [MediaController::class, 'getContentVersion']);
        Route::get('getParentContentList', [MediaController::class, 'getParentContentList']);

        //Route::get('getContentLibraryList',[ContentLibraryController::class,'getContentLibraryList']);
        //Route::post('addContentLibrary',[ContentLibraryController::class,'addContentLibrary']);
        //Route::get('getContentLibraryById/{contentId}',[ContentLibraryController::class,'getContentLibraryById']); 
        //Route::put('updateContentLibrary',[ContentLibraryController::class,'updateContentLibrary']);
        //Route::delete('deleteContentLibrary',[ContentLibraryController::class,'deleteContentLibrary']);
        //Route::get('getContentLibraryOptionList',[ContentLibraryController::class,'getContentLibraryOptionList']);
        /************************End Content*********************/


        /************************Start Group Setting*****************/
        Route::get('getGroupSettingList', [GroupSettingController::class, 'getGroupSettingList']);
        /************************End Group Setting*********************/


        /************************Start Group Master*****************/
        Route::get('getGroupList', [GroupMasterController::class, 'getGroupList']);
        Route::get('getGroupOptionList', [GroupMasterController::class, 'getGroupOptionList']);
        Route::get('getPrimaryGroupList', [GroupMasterController::class, 'getPrimaryGroupList']);
        Route::post('addGroup', [GroupMasterController::class, 'addGroup']);
        Route::get('getGroupById/{groupId}', [GroupMasterController::class, 'getGroupById']);
        Route::put('updateGroup', [GroupMasterController::class, 'updateGroup']);
        Route::delete('deleteGroup', [GroupMasterController::class, 'deleteGroup']);
        Route::delete('bulkDeleteGroup', [GroupMasterController::class, 'bulkDeleteGroup']);

        Route::post('getUserGroupList', [GroupMasterController::class, 'getUserGroupList']);
        Route::post('addUserGroup', [GroupMasterController::class, 'addUserGroup']);

        Route::get('groupExport', [GroupMasterController::class, 'groupExport']);
        Route::post('groupImport', [GroupMasterController::class, 'groupImport']);

        Route::get('getCategoryAssignGroupList/{categoryId}', [GroupMasterController::class, 'getCategoryAssignGroupList']);

        Route::get('getGroupListByCategoryId/{categoryId}', [GroupMasterController::class, 'getGroupListByCategoryId']);



        /************************End Group Master*********************/


        /************************Start Group Org*****************/
        Route::get('getOrgGroupList', [GroupOrganizationController::class, 'getOrgGroupList']);
        Route::get('getOrgGroupOptionList', [GroupOrganizationController::class, 'getOrgGroupOptionList']);
        Route::get('getOrgPrimaryGroupList', [GroupOrganizationController::class, 'getOrgPrimaryGroupList']);
        Route::post('addOrgGroup', [GroupOrganizationController::class, 'addOrgGroup']);
        Route::get('getOrgGroupById/{groupId}', [GroupOrganizationController::class, 'getOrgGroupById']);
        Route::put('updateOrgGroup', [GroupOrganizationController::class, 'updateOrgGroup']);
        Route::delete('deleteOrgGroup', [GroupOrganizationController::class, 'deleteOrgGroup']);
        Route::delete('bulkDeleteOrgGroup', [GroupOrganizationController::class, 'bulkDeleteOrgGroup']);

        Route::get('groupOrgExport', [GroupOrganizationController::class, 'groupOrgExport']);
        Route::post('groupOrgImport', [GroupOrganizationController::class, 'groupOrgImport']);
        Route::get('getOrgCategoryAssignGroupList/{categoryId}', [GroupOrganizationController::class, 'getOrgCategoryAssignGroupList']);

        Route::get('getOrgGroupListByCategoryId/{categoryId}', [GroupOrganizationController::class, 'getOrgGroupListByCategoryId']);
        Route::get('getUserListByGroupId/{groupId}', [GroupOrganizationController::class, 'getUserListByGroupId']);
        Route::get('getAssigendUserListByGroupId/{groupId}', [GroupOrganizationController::class, 'getAssigendUserListByGroupId']);
        Route::delete('unassigendGroupUserById/{id}', [GroupOrganizationController::class, 'unassigendGroupUserById']);


        /************************End Group Org*********************/


        /************************Start Group Master*****************/
        Route::get('getOrganizationGroupList', [OrganizationGroupController::class, 'getOrganizationGroupList']);
        Route::post('addOrganizationGroup', [OrganizationGroupController::class, 'addOrganizationGroup']);
        /************************End Group Master*********************/


        /************************Start Org Group Setting*****************/
        Route::get('getOrgGroupSettings', [OrgGroupSettingController::class, 'getOrgGroupSettings']);
        Route::post('addOrgGroupSetting', [OrgGroupSettingController::class, 'addOrgGroupSetting']);
        Route::put('updateOrgGroupSetting', [OrgGroupSettingController::class, 'updateOrgGroupSetting']);
        /************************End Org Group Setting*********************/


        /************************Start Module*****************/
        Route::get('getModuleList', [ModuleMasterController::class, 'getModuleList']);
        Route::post('addNewModule', [ModuleMasterController::class, 'addNewModule']);
        Route::get('getModuleById/{moduleId}', [ModuleMasterController::class, 'getModuleById']);
        Route::put('updateModule', [ModuleMasterController::class, 'updateModule']);
        Route::delete('deleteModule', [ModuleMasterController::class, 'deleteModule']);
        Route::get('getModuleOptionList', [ModuleMasterController::class, 'getModuleOptionList']);
        /************************End Module*********************/

        /************************Start Organization Type*****************/
        Route::get('getOrganizationType', [OrganizationTypeController::class, 'getOrganizationType']);
        Route::post('addOrganizationType', [OrganizationTypeController::class, 'addOrganizationType']);
        /************************End Organization Type*********************/

        /************************Start Tag*****************/
        Route::get('getTagList', [TagController::class, 'getTagList']);
        Route::post('addTag', [TagController::class, 'addTag']);
        Route::delete('deleteTag', [TagController::class, 'deleteTag']);
        /************************End Tag*********************/


        /************************Start Menu Master*****************/
        Route::get('getMenuMasterList', [MenuMasterController::class, 'getMenuMasterList']);
        Route::post('addNewMenuMaster', [MenuMasterController::class, 'addNewMenuMaster']);
        Route::get('getMenuMasterById/{menuId}', [MenuMasterController::class, 'getMenuMasterById']);
        Route::put('updateMenuMaster', [MenuMasterController::class, 'updateMenuMaster']);
        Route::delete('deleteMenuMaster', [MenuMasterController::class, 'deleteMenuMaster']);
        Route::get('getParentMenuMasterList', [MenuMasterController::class, 'getParentMenuMasterList']);
        Route::get('getMenuMasterOptionList', [MenuMasterController::class, 'getMenuMasterOptionList']);
        /************************End Menu Master*********************/

        /************************Start Role*****************/
        Route::get('getRoleList', [RoleController::class, 'getRoleList']);
        Route::get('getRoleOptionList', [RoleController::class, 'getRoleOptionList']);
        Route::post('addNewRole', [RoleController::class, 'addNewRole']);
        Route::get('getRoleById/{roleId}', [RoleController::class, 'getRoleById']);
        Route::put('updateRole', [RoleController::class, 'updateRole']);
        Route::delete('deleteRole', [RoleController::class, 'deleteRole']);
        /************************End Roler*********************/


        /************************Start Action Master*****************/
        Route::get('getActionList', [ActionsMasterController::class, 'getActionList']);
        Route::post('addNewAction', [ActionsMasterController::class, 'addNewAction']);
        Route::get('getActionById/{actionsId}', [ActionsMasterController::class, 'getActionById']);
        Route::put('updateAction', [ActionsMasterController::class, 'updateAction']);
        Route::delete('deleteAction', [ActionsMasterController::class, 'deleteAction']);
        Route::get('getActionOptionList', [ActionsMasterController::class, 'getActionOptionList']);
        Route::get('getActionsByModuleId/{moduleId}', [ActionsMasterController::class, 'getActionsByModuleId']);

        Route::get('getModuleActionsList', [ActionsMasterController::class, 'getModuleActionsList']);
        /************************End Action Master*********************/


        /************************Start Permission*****************/
        Route::get('getPermissionList', [PermissionController::class, 'getPermissionList']);
        Route::post('addNewPermission', [PermissionController::class, 'addNewPermission']);
        Route::get('getPermissionById/{permissionId}', [PermissionController::class, 'getPermissionById']);
        Route::put('updatePermission', [PermissionController::class, 'updatePermission']);
        Route::delete('deletePermission', [PermissionController::class, 'deletePermission']);
        Route::post('getPermissionsByOrganizationIdAndRoleId', [PermissionController::class, 'getPermissionsByOrganizationIdAndRoleId']);
        Route::post('addNewMultiplePermissions', [PermissionController::class, 'addNewMultiplePermissions']);
        /************************End Permission*********************/


        /************************Start Menu Permission*****************/
        Route::get('getMenuList', [MenuPermissionController::class, 'getMenuList']);
        Route::post('addNewMenu', [MenuPermissionController::class, 'addNewMenu']);
        Route::get('getMenuById/{menuId}', [MenuPermissionController::class, 'getMenuById']);
        Route::put('updateMenu', [MenuPermissionController::class, 'updateMenu']);
        Route::delete('deleteMenu', [MenuPermissionController::class, 'deleteMenu']);

        Route::put('bulkUpdateMenuPermission', [MenuPermissionController::class, 'bulkUpdateMenuPermission']);
        /************************End Menu Permission*********************/

        /************************Start Menu Submenu*********************/
        Route::get('getMenuSubmenuList', [MenuController::class, 'getMenuSubmenuList']);
        Route::get('getStudentMenuList', [MenuController::class, 'getStudentMenuList']);
        /************************End Menu Submenu*********************/

        Route::get('getActionsPermissionList', [ActionsPermissionController::class, 'getActionsPermissionList']);
        Route::get('getActionsPermissionListByModuleId/{moduleId}', [ActionsPermissionController::class, 'getActionsPermissionListByModuleId']);

        /**********************Start Dynamic Field*****************/
        Route::get('getDynamicFieldList', [DynamicFieldController::class, 'getDynamicFieldList']);
        /*********************End Dynamic Field*********************/

        /**********************Start Dynamic Link*****************/
        Route::get('getDynamicLinkList', [DynamicLinkController::class, 'getDynamicLinkList']);
        /*********************End Dynamic Link*********************/

        /**********************Start Course Catalog*****************/
        Route::get('getCourseCatalogList', [CourseCatalogController::class, 'getCourseCatalogList']);
        Route::post('addCourseCatalog', [CourseCatalogController::class, 'addCourseCatalog']);
        Route::get('getCourseCatalogById/{courseLibraryId}', [CourseCatalogController::class, 'getCourseCatalogById']);
        Route::put('updateCourseCatalog', [CourseCatalogController::class, 'updateCourseCatalog']);
        Route::delete('deleteCourseCatalog', [CourseCatalogController::class, 'deleteCourseCatalog']);
        Route::get('getCourseCatalogReferenceCodeOptionList', [CourseCatalogController::class, 'getCourseCatalogReferenceCodeOptionList']);
        /*********************End Course Catalog*********************/


        /**********************Start Training Library*****************/
        Route::get('getCourseLibraryList', [TrainingLibraryController::class, 'getCourseLibraryList']);
        Route::post('addCourseLibrary', [TrainingLibraryController::class, 'addCourseLibrary']);
        Route::get('getCourseLibraryById/{courseLibraryId}', [TrainingLibraryController::class, 'getCourseLibraryById']);
        Route::put('updateCourseLibrary', [TrainingLibraryController::class, 'updateCourseLibrary']);
        Route::delete('deleteCourseLibrary', [TrainingLibraryController::class, 'deleteCourseLibrary']);

        Route::get('getReferenceCodeOptionList', [TrainingLibraryController::class, 'getReferenceCodeOptionList']);
        Route::post('updateTrainingMedia', [TrainingLibraryController::class, 'updateTrainingMedia']);
        /**********************End Course Library*****************/

        /**********************Start Training Library*****************/
        //Route::get('getOrganizationCourseCatalogList',[CourseLibraryController::class,'getCourseLibraryList']);
        //Route::post('addOrganizationCourseCatalog',[CourseLibraryController::class,'addCourseLibrary']);
        //Route::get('getOrganizationCourseCatalogById/{courseCatalogById}',[CourseLibraryController::class,'getCourseLibraryById']);
        //Route::put('updateOrganizationCourseCatalog',[CourseLibraryController::class,'updateCourseLibrary']);
        //Route::delete('deleteOrganizationCourseCatalog',[CourseLibraryController::class,'deleteCourseLibrary']);
        /*********************End Course Library*********************/

        /**********************Start Requirement*****************/
        Route::get('getRequirementList', [RequirementController::class, 'getRequirementList']);
        /*********************End Requirement*********************/

        /**********************Start Enrollment*****************/
        Route::get('getEnrollmentList', [EnrollmentController::class, 'getEnrollmentList']);
        Route::post('addEnrollment', [EnrollmentController::class, 'addEnrollment']);
        Route::post('studentInprogressCourse', [EnrollmentController::class, 'studentInprogressCourse']);
        Route::post('studentCompletedCourse', [EnrollmentController::class, 'studentCompletedCourse']);
        /*********************End Enrollment*********************/


        /************************Start Organization Category*****************/
        Route::get('getOrganizationCategoryList', [OrganizationCategoryController::class, 'getOrganizationCategoryList']);
        Route::post('addOrganizationCategory', [OrganizationCategoryController::class, 'addOrganizationCategory']);
        Route::get('getOrganizationCategoryById/{categoryId}', [OrganizationCategoryController::class, 'getOrganizationCategoryById']);
        Route::put('updateOrganizationCategory', [OrganizationCategoryController::class, 'updateOrganizationCategory']);
        Route::delete('deleteOrganizationCategory', [OrganizationCategoryController::class, 'deleteOrganizationCategory']);
        Route::delete('bulkDeleteOrganizationCategory', [OrganizationCategoryController::class, 'bulkDeleteOrganizationCategory']);
        Route::get('getOrganizationPrimaryCategoryList', [OrganizationCategoryController::class, 'getOrganizationPrimaryCategoryList']);
        Route::get('getOrganizationCategoryOptionList', [OrganizationCategoryController::class, 'getOrganizationCategoryOptionList']);
        Route::post('orgCategoryGroupAssignment', [OrganizationCategoryController::class, 'orgCategoryGroupAssignment']);
        Route::get('getCategoryGroupList', [OrganizationCategoryController::class, 'getCategoryGroupList']);


        Route::post('getOrgAssignCourseListByCategoryId', [OrganizationCategoryController::class, 'getOrgAssignCourseListByCategoryId']);
        Route::post('getOrgUnAssignCourseListByCategoryId', [OrganizationCategoryController::class, 'getOrgUnAssignCourseListByCategoryId']);

        Route::post('orgBulkCourseCategoryAssign', [OrganizationCategoryController::class, 'orgBulkCourseCategoryAssign']);
        Route::post('orgCourseCategoryUnassign', [OrganizationCategoryController::class, 'orgCourseCategoryUnassign']);
        /************************End Organization Category*********************/


        /************************Start Organization Skill*****************/
        Route::get('getOrganizationSkillList', [OrganizationSkillController::class, 'getOrganizationSkillList']);
        Route::post('addOrganizationSkill', [OrganizationSkillController::class, 'addOrganizationSkill']);
        Route::get('getOrganizationSkillById/{skillId}', [OrganizationSkillController::class, 'getOrganizationSkillById']);
        Route::put('updateOrganizationSkill', [OrganizationSkillController::class, 'updateOrganizationSkill']);
        Route::delete('deleteOrganizationSkill', [OrganizationSkillController::class, 'deleteOrganizationSkill']);
        /************************End Organization Skill*********************/

        /************************Start User Group*****************/
        Route::post('userOrgGroupAssign', [UserGroupController::class, 'userOrgGroupAssign']);
        Route::post('userGroupAssign', [UserGroupController::class, 'userGroupAssign']);

        Route::get('getOrgUserGroupUnassignmentList/{userId}', [UserGroupController::class, 'getOrgUserGroupUnassignmentList']);
        Route::post('orgUserGroupAssignment', [UserGroupController::class, 'orgUserGroupAssignment']);

        Route::post('groupOrgUserAssign', [UserGroupController::class, 'groupOrgUserAssign']);
        Route::post('groupUserAssign', [UserGroupController::class, 'groupUserAssign']);
        /************************End User Group*********************/

        /************************Start Training Type*****************/
        Route::get('getTrainingTypeList', [TrainingTypeController::class, 'getTrainingTypeList']);
        /************************End Training Type*********************/

        /************************Start Training Status*****************/
        Route::get('getTrainingStatusList', [TrainingStatusController::class, 'getTrainingStatusList']);
        /************************End Training Status*********************/

        /************************Start Training Notification*****************/
        Route::get('getTrainingNotificationList', [TrainingNotificationController::class, 'getTrainingNotificationList']);
        /************************End Training Notification*********************/

        /************************Start Ilt Entrollment*****************/
        Route::get('getIltEnrollmentList', [IltEnrollmentController::class, 'getIltEnrollmentList']);
        /************************End Ilt Entrollment*********************/

        /************************Start Question Type*****************/
        Route::get('getQuestionTypeList', [QuestionTypeController::class, 'getQuestionTypeList']);
        /************************End Question Type*********************/

        /************************Start Team Approval*****************/
        Route::get('getTeamApprovalList', [TeamApprovalController::class, 'getTeamApprovalList']);
        Route::post('teamApproved', [TeamApprovalController::class, 'teamApproved']);
        Route::post('teamRejected', [TeamApprovalController::class, 'teamRejected']);
        /************************End Team Approval*********************/

        /************************Start Team Credit*****************/
        Route::get('getTeamCreditList', [TeamCreditController::class, 'getTeamCreditList']);
        Route::get('getTeamCreditById/{teamCreditId}', [TeamCreditController::class, 'getTeamCreditById']);
        /************************End Team Credit*********************/

        /************************Start My Team*****************/
        Route::get('getMyTeamList', [MyTeamController::class, 'getMyTeamList']);
        Route::post('getCourseListByUserId', [MyTeamController::class, 'getCourseListByUserId']);
        Route::post('giveCredit', [MyTeamController::class, 'giveCredit']);
        Route::get('viewCreditListByUserId/{userId}', [MyTeamController::class, 'viewCreditListByUserId']);
        Route::get('creditHistoryByUserId/{userId}', [MyTeamController::class, 'creditHistoryByUserId']);
        /************************End My Team*********************/

        /************************Start Training Assignment*****************/
        Route::get('getTrainingAssignedToOrganizationList/{trainingId}', [OrganizationAssignTrainingLibraryController::class, 'getTrainingAssignedToOrganizationList']);
        Route::post('trainingAssignmentToOrganization', [OrganizationAssignTrainingLibraryController::class, 'trainingAssignmentToOrganization']);
        Route::post('courseCatalogReset', [OrganizationAssignTrainingLibraryController::class, 'courseCatalogReset']);
        /************************End Training Assignment*********************/


        /************************Start Enrollment*****************/
        Route::post('trainingAssignToUser', [TrainingAssignController::class, 'trainingAssignToUser']);
        Route::post('addTrainingCategorytoGroupUserAssignment', [TrainingAssignController::class, 'addTrainingCategorytoGroupUserAssignment']);
        /************************End Enrollment*********************/

        Route::post('userNotification', [UserNotificationController::class, 'userNotification']);


        /************************Start Area*****************/
        Route::get('getAreaList', [AreaController::class, 'getAreaList']);
        Route::post('addArea', [AreaController::class, 'addArea']);
        /************************End Location*********************/

        /************************Start Location*****************/
        Route::get('getLocationList', [LocationController::class, 'getLocationList']);
        Route::post('addLocation', [LocationController::class, 'addLocation']);
        /************************End Location*********************/

        /************************Start Division*****************/
        Route::get('getDivisionList', [DivisionController::class, 'getDivisionList']);
        Route::post('addDivision', [DivisionController::class, 'addDivision']);
        /************************End Division*********************/

        /************************Start Job Title*****************/
        Route::get('getJobTitleList', [JobTitleController::class, 'getJobTitleList']);
        Route::post('addJobTitle', [JobTitleController::class, 'addJobTitle']);
        /************************End Job Title*********************/



        Route::get('getStudentCatalogList', [StudentCatalogController::class, 'getStudentCatalogList']);
        Route::post('saveQuiz', [StudentCatalogController::class, 'saveQuiz']);
        Route::post('saveSuspendVideo', [StudentCatalogController::class, 'saveSuspendVideo']);
        Route::get('getStudentCatalogById/{id}', [StudentCatalogController::class, 'getStudentCatalogById']);


        /************************Start Credential*****************/
        Route::get('getCredentialList', [CredentialController::class, 'getCredentialList']);
        Route::post('addCredential', [CredentialController::class, 'addCredential']);
        Route::get('getCredentialById/{credentialId}', [CredentialController::class, 'getCredentialById']);
        Route::put('updateCredential', [CredentialController::class, 'updateCredential']);
        Route::delete('deleteCredential', [CredentialController::class, 'deleteCredential']);
        Route::delete('bulkDeleteCredential', [CredentialController::class, 'bulkDeleteCredential']);
        Route::post('activeCredential', [CredentialController::class, 'activeCredential']);
        Route::post('archiveCredential', [CredentialController::class, 'archiveCredential']);
        Route::post('bulkArchiveCredential', [CredentialController::class, 'bulkArchiveCredential']);
        /************************End Credential*********************/


        /************************Start Media*****************/
        Route::get('getOrgMediaList', [OrganizationMediaController::class, 'getOrgMediaList']);
        Route::post('addOrgMedia', [OrganizationMediaController::class, 'addOrgMedia']);
        Route::get('getOrgMediaById/{contentId}', [OrganizationMediaController::class, 'getOrgMediaById']);
        Route::delete('deleteOrgMedia', [OrganizationMediaController::class, 'deleteOrgMedia']);
        Route::get('getOrgMediaOptionList', [OrganizationMediaController::class, 'getOrgMediaOptionList']);


        Route::get('getOrgMediaCourseLibraryById/{mediaId}', [OrganizationMediaController::class, 'getOrgMediaCourseLibraryById']);
        Route::delete('deleteOrgMediaCourseLibrary', [OrganizationMediaController::class, 'deleteOrgMediaCourseLibrary']);

        /************************End Media*********************/

        /************************Start Content*****************/
        Route::post('getOrgContentVersion', [OrganizationMediaController::class, 'getOrgContentVersion']);
        Route::get('getOrgParentContentList', [OrganizationMediaController::class, 'getOrgParentContentList']);

        Route::get('getTanscriptList', [TranscriptController::class, 'getTanscriptList']);
        Route::get('getTanscriptById/{tanscriptId}', [TranscriptController::class, 'getTanscriptById']);
        Route::get('getStudentDashboardCount', [DashboardController::class, 'getStudentDashboardCount']);

        Route::post('addDocument', [UserDocumentController::class, 'addDocument']);
        Route::get('getDocumentList', [UserDocumentController::class, 'getDocumentList']);


        Route::get('getNotificationCategoryList', [NotificationCategoryController::class, 'getNotificationCategoryList']);
        Route::get('notificationEventListByCategoryId/{notificationCategoryId}', [NotificationEventController::class, 'notificationEventListByCategoryId']);
        Route::get('getDynamicFieldListByEventId/{notificationEventId}', [DynamicFieldController::class, 'getDynamicFieldListByEventId']);



        Route::post('addGeneralSetting', [GeneralSettingController::class, 'addGeneralSetting']);
        Route::get('getGeneralSettingList', [GeneralSettingController::class, 'getGeneralSettingList']);


        Route::get('getRoleListByOrg', [RoleController::class, 'getRoleListByOrg']);
        Route::get('getRoleListByOrgId/{organizationId}', [RoleController::class, 'getRoleListByOrgId']);
        Route::put('bulkUpdateOrgRole/{organizationId}', [RoleController::class, 'bulkUpdateOrgRole']);


        Route::get('getOrgRoleList', [OrganizationRoleController::class, 'getOrgRoleList']);
        Route::get('getOrgRoleById/{roleId}', [OrganizationRoleController::class, 'getOrgRoleById']);
        Route::put('updateOrgRoleById/{roleId}', [OrganizationRoleController::class, 'updateOrgRoleById']);

        
        Route::get('getDocumentLibraryList', [DocumentLibraryController::class, 'getDocumentLibraryList']);
        Route::get('getOrgDocumentLibraryList', [DocumentLibraryController::class, 'getOrgDocumentLibraryList']);
        Route::get('getStudentDocumentLibraryList', [DocumentLibraryController::class, 'getOrgDocumentLibraryList']);
        Route::post('addDocumentLibrary', [DocumentLibraryController::class, 'addDocumentLibrary']);
        Route::get('getDocumentLibraryById/{id}', [DocumentLibraryController::class, 'getDocumentLibraryById']);
        Route::put('updateDocumentLibraryById/{id}', [DocumentLibraryController::class, 'updateDocumentLibraryById']);
        Route::delete('deleteDocumentLibraryById/{id}', [DocumentLibraryController::class, 'deleteDocumentLibraryById']);
        Route::post('documentLibraryOrder', [DocumentLibraryController::class, 'documentLibraryOrder']);

        Route::get('getFaqList', [FaqController::class, 'getFaqList']);
        Route::get('getOrgFaqList', [FaqController::class, 'getOrgFaqList']);
        Route::get('getStudentFaqList', [FaqController::class, 'getOrgFaqList']);
        Route::post('addFaq', [FaqController::class, 'addFaq']);
        Route::get('getFaqById/{id}', [FaqController::class, 'getFaqById']);
        Route::put('updateFaqById/{id}', [FaqController::class, 'updateFaqById']);
        Route::delete('deleteFaqById/{id}', [FaqController::class, 'deleteFaqById']);
        Route::post('faqOrder', [FaqController::class, 'faqOrder']);


        
        Route::get('getCustomFieldForList', [OrganizationCustomFieldController::class, 'getCustomFieldForList']);
        Route::get('getCustomFieldTypeList', [OrganizationCustomFieldController::class, 'getCustomFieldTypeList']);

        Route::get('getOrgCustomFieldList', [OrganizationCustomFieldController::class, 'getOrgCustomFieldList']);
        Route::post('addOrgCustomField', [OrganizationCustomFieldController::class, 'addOrgCustomField']);
        Route::get('getOrgCustomFieldById/{id}', [OrganizationCustomFieldController::class, 'getOrgCustomFieldById']);
        Route::put('updateOrgCustomFieldById/{id}', [OrganizationCustomFieldController::class, 'updateOrgCustomFieldById']);
        Route::delete('deleteOrgCustomFieldById/{id}', [OrganizationCustomFieldController::class, 'deleteOrgCustomFieldById']);
        Route::get('getUsersCustomFieldList', [OrganizationCustomFieldController::class, 'getUsersCustomFieldList']);
        Route::get('getTrainingCustomFieldList/{trainingTypeId}', [OrganizationCustomFieldController::class, 'getTrainingCustomFieldList']);
        Route::get('getCredentialsCustomFieldList', [OrganizationCustomFieldController::class, 'getCredentialsCustomFieldList']);

        Route::get('getOrgClassList', [ClassRoomClassController::class, 'getOrgClassList']);
        Route::post('addOrgClass', [ClassRoomClassController::class, 'addOrgClass']);
        Route::get('getOrgClassById/{id}', [ClassRoomClassController::class, 'getOrgClassById']);
        Route::get('getOrgClassSessionById/{id}', [ClassRoomClassController::class, 'getOrgClassSessionById']);
        Route::put('updateOrgClassById/{id}', [ClassRoomClassController::class, 'updateOrgClassById']);
        Route::put('updateOrgClassSessionById/{id}', [ClassRoomClassController::class, 'updateOrgClassSessionById']);
        Route::delete('deleteOrgClassById/{id}', [ClassRoomClassController::class, 'deleteOrgClassById']);
        Route::delete('deleteOrgClassSessionById/{id}', [ClassRoomClassController::class, 'deleteOrgClassSessionById']);
        Route::get('getClassesAndSessionsByCourseId/{id}', [ClassRoomClassController::class, 'getClassesAndSessionsByCourseId']);
        
        Route::get('getOrgLearningPlanList', [OrganizationLearningPlanController::class, 'getOrgLearningPlanList']);
        Route::post('addOrgLearningPlan', [OrganizationLearningPlanController::class, 'addOrgLearningPlan']);
        Route::get('getOrgLearningPlanById/{id}', [OrganizationLearningPlanController::class, 'getOrgLearningPlanById']);
        Route::get('getOrgLearningPlanRequirementById/{id}', [OrganizationLearningPlanController::class, 'getOrgLearningPlanRequirementById']);
        Route::put('updateOrgLearningPlanById/{id}', [OrganizationLearningPlanController::class, 'updateOrgLearningPlanById']);
        Route::put('updateOrgLearningPlanRequirementById/{id}', [OrganizationLearningPlanController::class, 'updateOrgLearningPlanRequirementById']);
        Route::delete('deleteOrgLearningPlanById/{id}', [OrganizationLearningPlanController::class, 'deleteOrgLearningPlanById']);
        Route::delete('deleteOrgLearningPlanRequirementById/{id}', [OrganizationLearningPlanController::class, 'deleteOrgLearningPlanRequirementById']);
        Route::get('getOrgRequirementListByLearningPlanId/{id}', [OrganizationLearningPlanController::class, 'getOrgRequirementListByLearningPlanId']);

        Route::get('getUserListByLearningPlanId/{id}', [OrganizationLearningPlanController::class, 'getUserListByLearningPlanId']);
        Route::post('learningPlanUserAssignment', [OrganizationLearningPlanController::class, 'learningPlanUserAssignment']);
        
        //}); 

        Route::post('scormTracking', [ScormController::class, 'scormTracking']);
        Route::get('getScormTrackingById/{scormId}', [ScormController::class, 'getScormTrackingById']);

        //Route::get('scorm/{id}', [ScormController::class, 'show']);
    });

    Route::get('truncateTable', [TruncateTableController::class, 'truncateTable']);
    
});
