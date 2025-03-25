<?php

use App\Http\Controllers\LabCodeController;
use Illuminate\Support\Facades\Route;
use Afosto\Acme\Client;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/labcode', [LabCodeController::class, 'index']);


Route::get('/acme/cert', function() {
    /*$adapter = new LocalFilesystemAdapter('local');
    $filesystem = new Filesystem($adapter);
    $client = new Client( [
        'username' => env('SSL_MAIL_CONTACT'),
        'fs' => $filesystem,
        'mode' => env('SSL_ENVIROMENT') == 'prod' ? Client::MODE_STAGING : Client::MODE_LIVE,
    ]);*/
    dd(env('SSL_ENVIROMENT') == 'prod' ? Client::MODE_LIVE : Client::MODE_STAGING);
});
