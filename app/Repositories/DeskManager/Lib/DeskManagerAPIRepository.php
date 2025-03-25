<?php

namespace App\Repositories\DeskManager\Lib;



class DeskManagerAPIRepository
{

    public function token ($endpoint, $chave_ambiente, $chave_operador) {
		$curl = curl_init($endpoint);
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,json_encode(array("PublicKey"=>$chave_ambiente)));
  		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  					"Authorization: $chave_operador",
  					"JsonPath: true",
              	"Content-type: application/json"
              ));
		$resp = curl_exec($curl);

		curl_close($curl);
		$retorno = json_decode($resp);

		if (strlen($retorno->access_token) > 1) {
			return array("status"=>"ok", "access_token"=>$retorno->access_token);
		} else {
			return array("status"=>"error", "access_token"=>$retorno->erro);
		}
	}

    public function put ($endpoint, $token, $dados) {

		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
  		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  					"Authorization: $token",
  					"JsonPath: true",
     				"Content-type: application/json;",
     	));

		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dados));



		$resp = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($status != 200 && $status != 201) {
      	echo ("Error: call to URL $endpoint failed with status $status, response $resp, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
	  	}
		return json_decode($resp);
	}


	public function post ($endpoint, $token, $dados) {

		$curl = curl_init($endpoint);
		curl_setopt($curl, CURLOPT_URL, $endpoint);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);

  		curl_setopt($curl, CURLOPT_HTTPHEADER, array(
  					"Authorization: $token",
  					"JsonPath: true",
              	"Content-type: application/json;",
              ));

		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($dados));

		$resp = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		if ($status != 200 && $status != 201) {
      	echo ("Error: call to URL $endpoint failed with status $status, response $resp, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
	  	}
		return json_decode($resp);
	}
}
