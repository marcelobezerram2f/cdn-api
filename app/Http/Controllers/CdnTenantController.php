<?php

namespace App\Http\Controllers;

use App\Repositories\CdnTenantRepository;
use Illuminate\Http\Request;

class CdnTenantController extends Controller
{

    private $cdnTenantRepository;

    public function __construct()
    {
        $this->cdnTenantRepository = new CdnTenantRepository();
    }

    public function allTenants(Request $request) {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->cdnTenantRepository->allTenants($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }

    public function getByName(Request $request) {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->cdnTenantRepository->tenantByName($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }


    public function deleteTenant(Request $request) {
        $data = $request->all();
        $response = $this->cdnTenantRepository->deleteTenant($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }


}
