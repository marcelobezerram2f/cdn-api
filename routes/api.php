    <?php

use App\Http\Controllers\AcmeLetsEncryptController;
use App\Http\Controllers\BillingController;
use App\Http\Controllers\CdnClientController;
use App\Http\Controllers\CdnIngestPointController;
use App\Http\Controllers\CdnOriginGroupController;
use App\Http\Controllers\CdnProvisioningController;
use App\Http\Controllers\CdnResourcesController;
use App\Http\Controllers\CdnTargetGroupController;
use App\Http\Controllers\CdnTemplateController;
use App\Http\Controllers\CdnTenantController;
use App\Http\Controllers\CertificateManagerControler;
use App\Http\Controllers\DeskManagerController;
use App\Http\Controllers\LabCodeController;
use App\Http\Controllers\WhmcsInvoiceController;
use App\Http\Controllers\GraphDataController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\SalesforceController;



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
Route::get('/labcode', [LabCodeController::class, 'index']);
Route::post('/provisioning/copyCdnTemplate', [CdnProvisioningController::class, 'copyCdnTemplate']);


Route::get('/acme/cert', [AcmeLetsEncryptController::class, 'generateCert']);
//Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//    return $request->user();
//});

Route::group(['prefix' => 'v1'], function () {
    Route::post('/user/login', [UserAuthController::class, 'login']);
    Route::post('/user/logout', [UserAuthController::class, 'logout']);
    Route::post('/user/confirmChangePassword', [UserAuthController::class, 'confirmChangePassword']);
    Route::post('/user/changePassword', [UserAuthController::class, 'changePassword']);
    Route::group(['middleware' => 'client'], function () {
        //Rota alterar senha usuario logado
        Route::post('/user/changePasswordLoggedUser', [UserAuthController::class, 'changePasswordLoggedUser']);

        //Rotas de provisionamento
        Route::post('/provisioning/newTenant', [CdnProvisioningController::class, 'newTenant']);
        Route::post('/provisioning/newResource', [CdnProvisioningController::class, 'newCdnResource']);
        //Rotas crud Target Groups (planos)
        Route::get('/targetGroup/getall', [CdnTargetGroupController::class, 'getAll']);
        Route::post('/targetGroup/create', [CdnTargetGroupController::class, 'create']);
        Route::post('/targetGroup/delete', [CdnTargetGroupController::class, 'delete']);
        //Rotas crud Ingest Points (Localidades)
        Route::get('/ingestPoint/getall', [CdnIngestPointController::class, 'getAll']);
        Route::post('/ingestPoint/create', [CdnIngestPointController::class, 'create']);
        Route::post('/ingestPoint/delete', [CdnIngestPointController::class, 'delete']);
        //rotas Crud template
        Route::get('/template/getAll', [CdnTemplateController::class, 'getAll']);
        //Tenants
        Route::post('/tenant/getAll', [cdnTenantController::class, 'allTenants']);
        Route::post('/tenant/getbyName', [cdnTenantController::class, 'getbyName']);
        Route::post('/tenant/getByClient', [cdnTenantController::class, 'getbyClient']);
        Route::post('/tenant/deleteTenant', [cdnTenantController::class, 'deleteTenant']);


        //Resources
        Route::post('/resources/checkCname', [CdnResourcesController::class, 'checkDnsCname']);
        Route::post('/resource/newResource', [CdnResourcesController::class, 'newResource']);
        Route::post('/resources/getResource', [CdnResourcesController::class, 'getCdnResource']);
        Route::post('/resources/block', [CdnResourcesController::class, 'blockCdnResource']);
        Route::post('/resources/unblock', [CdnResourcesController::class, 'unblockCdnResource']);
        Route::post('/resources/update', [CdnResourcesController::class, 'update']);
        Route::post('/resources/delete', [CdnResourcesController::class, 'delete']);
        Route::post('/resouces/ssl', [CdnResourcesController::class, 'sslRequest']);
        Route::post('/resource/ssl/recheck', [CdnResourcesController::class, 'sslRecheck']);
        Route::post('/resources/ssl/update', [CertificateManagerControler::class, 'update']);

        //origin servers & origin servers groups
        Route::post('/origin-servers/group/getByTenant', [CdnOriginGroupController::class, 'getByTenant']);
        Route::post('/origin-servers/group/create', [CdnOriginGroupController::class, 'create']);
        Route::post('/origin-servers/group/getById', [CdnOriginGroupController::class, 'getById']);
        Route::post('/origin-servers/getOriginServers', [CdnOriginGroupController::class, 'getOriginServers']);
        Route::post('/origin-servers/group/update', [CdnOriginGroupController::class, 'updateGroup']);
        Route::post('/origin-servers/update',  [CdnOriginGroupController::class, 'updateOriginServer']);
        Route::post('/origin-servers/group/delete', [CdnOriginGroupController::class, 'deleteGroup']);
        Route::post('/origin-servers/delete', [CdnOriginGroupController::class, 'deleteOriginServer']);

        //whmcs- invoices
        Route::post('/whmcs/getSSO', [WhmcsInvoiceController::class, 'getSSO']);
        Route::post('/whmcs/getSSOInvoice', [WhmcsInvoiceController::class, 'getSSOInvoice']);
        Route::post('/whmcs/getInvoices', [WhmcsInvoiceController::class, 'getInvoices']);
        Route::post('/whmcs/getInvoice', [WhmcsInvoiceController::class, 'getInvoice']);
        // Salesforce
        Route::post('/salesforce/openCase', [DeskManagerController::class, 'OpenCase']);
        Route::post('/salesforce/infoCases', [DeskManagerController::class, 'infoCases']);
        Route::post('/salesforce/addComments', [DeskManagerController::class, 'addComments']);
        Route::post('/salesforce/getCases', [DeskManagerController::class, 'getCases']);
        Route::post('/salesforce/getClosedCases', [DeskManagerController::class, 'getClosedCases']);
        // Salesforce
        /*Route::post('/salesforce/openCase', [SalesforceController::class, 'OpenCase']);
        Route::post('/salesforce/infoCases', [SalesforceController::class, 'infoCases']);
        Route::post('/salesforce/addComments', [SalesforceController::class, 'addComments']);
        Route::post('/salesforce/getCases', [SalesforceController::class, 'getCases']);
        Route::post('/salesforce/getClosedCases', [SalesforceController::class, 'getClosedCases']);
        */

        Route::get('/account/getAll', [CdnClientController::class, 'getAll']);
        Route::post('/account/getByAccount', [CdnClientController::class, 'getByAccount']);
        Route::post('/account/resetPassword', [CdnClientController::class, 'resetPasswordByUserName']);



        Route::post('/acme/register', [AcmeLetsEncryptController::class, 'register']);


        // Rotas de captura de bilhetagem (aggregator)
        Route::post('/billing/rawData', [BillingController::class, 'getRawData']);
        Route::post('/billing/summaryHour', [BillingController::class, 'getSummariesHour']);
        Route::post('/billing/summarized', [BillingController::class, 'getSummarized']);
        Route::post('/billing/statement', [BillingController::class, 'getStatement']);

        //Rotas de captura para os graficos (aggregator)
        Route::post('/graph/getFiveToFive', [GraphDataController::class, 'fiveMinutesAverage']);
        Route::post('/graph/averyMinutes', [GraphDataController::class, 'everyMinutes']);
        Route::post('/graph/daily', [GraphDataController::class, 'daily']);
        Route::post('/graph/monthly', [GraphDataController::class, 'monthly']);

    });
});

