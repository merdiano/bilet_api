<?php
/**
 * Created by PhpStorm.
 * User: merdan
 * Date: 8/16/2019
 * Time: 13:30
 */

namespace App\Payment;


abstract class PaymentResponse
{
    protected $response_data;
    protected $exception_message;

    public function setResponseData($data){
        $this->response_data = json_decode($data, true);
    }

    public function setExceptionMessage($message){
        $this->exception_message = $message;
    }

    public function errorMessage(){
        if(!$this->exception_message)
        {
            return $this->response_data['ErrorMessage'];}
        else
            return $this->exception_message;
    }

    public abstract function isSuccessfull();
    public abstract function getPaymentReferenceId();
}
