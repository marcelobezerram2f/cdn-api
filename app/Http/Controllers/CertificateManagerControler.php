<?php

namespace App\Http\Controllers;

use App\Repositories\CertificateManagerRepository;
use Illuminate\Http\Request;

class CertificateManagerControler extends Controller
{
    private $certificateManagerRepository;

    public function __construct()
    {
        $this->certificateManagerRepository =  new CertificateManagerRepository;
    }

    public function update(Request $request) {
        $data = $request->all();
        $response = $this->certificateManagerRepository->update($data);
        if (isset($response['code'])) {
            $code = $response['code'];
        } else {
            $code = 200;
        }
        return response($response, $code);
    }

}
