<?php

namespace App\Http\Controllers;

use App\Repositories\ProvisioningRepository;
use Illuminate\Http\Request;

class CdnProvisioningController extends Controller
{

    private $provisioningRepository;


    public function __construct()
    {
        $this->provisioningRepository = new ProvisioningRepository();
    }

    public function newTenant(Request $request)
    {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->provisioningRepository->newTenant($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }


    public function newCdnResource(Request $request)
    {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->provisioningRepository->newCdnResource($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);


    }


    public function copyCdnTemplate(Request $request) {


        return  $this->provisioningRepository->copyCdnTemplate($request->all());

    }

    public function sslInstall(Request $request)
    {
    }
}
