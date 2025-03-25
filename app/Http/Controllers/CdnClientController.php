<?php

namespace App\Http\Controllers;

use App\Repositories\ClientRepository;
use Illuminate\Http\Request;

class CdnClientController extends Controller
{
    private $cdnClientRepository;


    public function __construct()
    {
        $this->cdnClientRepository =  new ClientRepository();
    }


    public function getAll()
    {
        $response = $this->cdnClientRepository->getAll();
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }


    public function getByAccount(Request $request)
    {
        $response = $this->cdnClientRepository->getByAccount($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function resetPasswordByUserName(Request $request)
    {
        $response = $this->cdnClientRepository->resetPasswordByUserName($request->all());
        if(isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }


}
