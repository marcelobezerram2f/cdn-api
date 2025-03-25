<?php

namespace App\Http\Controllers;

use App\Repositories\WhmcsInvoicesRepository;
use Illuminate\Http\Request;

class WhmcsInvoiceController extends Controller
{
    private $whmcsInvoiceRepository;

    public function __construct()
    {
        $this->whmcsInvoiceRepository = new WhmcsInvoicesRepository();
    }


    public function getInvoices(Request $request)
    {

        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->whmcsInvoiceRepository->getInvoices($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);


    }


    public function getInvoice(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->whmcsInvoiceRepository->getInvoice($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }

    public function getSSO(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->whmcsInvoiceRepository->ssoInvoices($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }


    public function getSSOInvoice(Request $request)
    {
        $data = $request->all();
        $data['token'] = $request->bearerToken();
        $response = $this->whmcsInvoiceRepository->ssoInvoice($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response()->json($response, $code);

    }
}
