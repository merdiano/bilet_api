<?php
/**
 * Created by PhpStorm.
 * User: merdan
 * Date: 8/16/2019
 * Time: 12:44
 */

namespace App\Payment;


class PaymentRegistrationResponse extends PaymentResponse {


    public function isSuccessfull(){
        if (!$this->exception_message)
            return $this->response_data['errorCode'] == 0;

        return false;
    }

    public function getRedirectUrl(){
        if($this->response_data)
            return $this->response_data['formUrl'];

        return '';
    }

    public function getPaymentReferenceId()
    {
        return $this->response_data['orderId'];
    }
}