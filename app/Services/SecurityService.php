<?php
namespace App\Services;

class SecurityService {


    private $iv;
    private $method ;
    private $password;


    /**
     * On contructor sets the parameter of the class (optional)
     * @param type $method
     * @param type $password
     * @param type $iv
     */
    function __construct()
    {
        $this->method = config('app.cipher');
        $this->password = config('app.key');
        $this->iv = config('app.iv');
    }

    /**
     *
     * @param string $gata
     * @return string
     */

    public function dataEncrypt($data)
    {
       return openssl_encrypt($data, $this->method, $this->password, 0, $this->iv);
    }

    /**
     * @param string $data
     * @return string
     */

    public function dataDecrypt($data)
    {
        return openssl_decrypt($data, $this->method, $this->password, 0, $this->iv);
    }

}


