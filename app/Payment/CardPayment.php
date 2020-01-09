<?php
/**
 * Created by PhpStorm.
 * User: merdan
 * Date: 8/15/2019
 * Time: 17:47
 */

namespace App\Payment;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Facades\Log;

class CardPayment{

    protected $client;

    public function __construct()
    {
        $this->client = new Client(config('payment.card.config'));
    }

    public function registerPayment($transaction_data){

        $params['form_params'] = array_merge($transaction_data,config('payment.card.params'));
        $response = new PaymentRegistrationResponse();
        //dd($params);
        try{
            $request = $this->client->post('register.do',$params);
            $response->setResponseData($request->getBody());
        }
        catch (\Exception $ex){
            Log::error($ex);
            $error = 'Sorry, there was an error processing your payment. Please try again.';
            $response->setExceptionMessage($error);
        }
        return $response;
    }

    public function registerCard($cardDetails){
        $response = new CardRegistrationResponce;

        try{
            $request = $this->client->post('processform.do',['form_params' =>$cardDetails]);
            $response->setResponseData($request->getBody());
        }catch (\Exception $ex){
            Log::error($ex);
            $error = 'Sorry, there was an error processing your payment. Please try again.';
            $response->setExceptionMessage($error);
        }
    }

    public function getPaymentStatus($orderId){
        $params['form_params'] = config('payment.card.params');
        $params['form_params']['orderId'] = $orderId;
        $response = new PaymentStatusResponse();
        try{
            $request = $this->client->post('getOrderStatus.do',$params);
            $response->setResponseData($request->getBody());
        }
        catch (\Exception $ex){
            Log::error($ex);
            $response->setExceptionMessage($ex->getMessage());
        }
        return $response;
    }
}
