<?php
/**
 * Created by PhpStorm.
 * User: merdan
 * Date: 8/16/2019
 * Time: 13:26
 */

namespace App\Payment;


class PaymentStatusResponse extends PaymentResponse
{

    public function isSuccessfull()
    {
        if(!$this->exception_message)
            return $this->response_data['ErrorCode'] == 0
                && $this->response_data['OrderStatus'] == 2;

        return false;
    }

    public function getPaymentReferenceId()
    {
        return $this->response_data['OrderNumber'];
    }
}
