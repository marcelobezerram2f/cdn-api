<?php

namespace App\Http\Controllers;

use App\Repositories\CdnResourcesRepository;
use Illuminate\Http\Request;

class CdnResourcesController extends Controller
{
    private $cdnResourcesRepository;


    public function __construct()
    {
        $this->cdnResourcesRepository = new CdnResourcesRepository();
    }

    public function allTenants(Request $request)
    {
        $response = $this->cdnResourcesRepository->allTenants();
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function tenantByName(Request $request)
    {
        $response = $this->cdnResourcesRepository->tenantByName($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);
    }

    public function getCdnResource(Request $request)
    {
        $response = $this->cdnResourcesRepository->getCdnResource($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function blockCdnResource(Request $request)
    {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->cdnResourcesRepository->blockResource($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function unblockCdnResource(Request $request)
    {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->cdnResourcesRepository->unblockResource($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function update(Request $request)
    {
        $header = getHeader($request);
        $data = $request->all();
        $data['header'] = $header;
        $response = $this->cdnResourcesRepository->update($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function delete(Request $request)
    {
        $data = $request->all();
        $response = $this->cdnResourcesRepository->deleteResource($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function sslRequest(Request $request)
    {
        $data = $request->all();
        $response = $this->cdnResourcesRepository->sslActive($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function sslRecheck(Request $request)
    {
        $data = $request->all();
        $response = $this->cdnResourcesRepository->sslRecheck($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }
    public function checkDnsCname(Request $request)
    {
        $response = $this->cdnResourcesRepository->checkDnsCname($request->all());
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }





}
