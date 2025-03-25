<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SendUserCredentials extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */


    private $email;
    private $name;
    private $user_name;
    private $password;
    private $lang;

    public function __construct($email, $name, $user_name, $password, $lang)
    {
        $this->email = $email;
        $this->name = $name;
        $this->user_name = $user_name;
        $this->password = $password;
        $this->lang = $lang;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $app_env = strtolower(env('APP_ENV'));
        $app_url = 'https://portal.vcdn.net.br';

        if (isset($app_env)){
            if (($app_env == 'dev')  || ($app_env == 'sandbox') || ($app_env == 'qa')){
                 $app_url = 'https://portal.' . $app_env .'.vcdn.net.br';
            }else{
                $app_env = null;
            }
        }

        switch ($this->lang) {
            case 'en':
                $bladeLang = 'emails.UserCredentialsEN';
                $subjectMessage = 'Access to the VCDN Portal';
                $environmentMessage = isset($app_env) ? ' - Email sent environment ' . strtoupper($app_env) : '';
                break;
            case 'es':
                $bladeLang = 'emails.UserCredentialsES';
                $subjectMessage = 'Acceso al Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env) : '';
                break;
            case 'ptbr':
                $bladeLang = 'emails.UserCredentialsPTBR';
                $subjectMessage = 'Acceso ao Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env) : '';
                break;
            default:
                $bladeLang = 'emails.UserCredentialsPTBR';
                $subjectMessage = 'Acesso ao Portal VCDN';
                $environmentMessage = isset($app_env) ? ' - Email enviado ambiente ' . strtoupper($app_env) : '';
                break;
        }

        return $this->from(env('MAIL_FROM_ADDRESS'))->markdown($bladeLang)
                     ->subject($subjectMessage . $environmentMessage)
                     ->with([
                        'email' => $this->email,
                        'password' => $this->password,
                        'name' => $this->name,
                        'user_name' => $this->user_name,
                        'link' => $app_url
                    ]);
    }
}
