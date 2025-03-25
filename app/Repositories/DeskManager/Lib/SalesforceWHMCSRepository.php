<?php

namespace App\Repositories\Salesforce\Lib;


class SalesforceWHMCSRepository {

    public function Clientes () {

		$values = array(
		            'limitnum' 		=> 10000,
		        );

		$retorno = localAPI("GetClients", $values, null);

		if ($retorno["result"] != "success") {
			return "erro";
		} else {
			return $retorno["clients"]["client"];
		}
	}

	public function ClienteInfo ($id) {

		$values = array(
		            'clientid' => $id,
		            'stats' => true,
		        );

		$retorno = localAPI("GetClientsDetails", $values, null);

		if ($retorno["result"] != "success") {
			return "erro";

		} else {
			return $retorno;
		}
	}

}
