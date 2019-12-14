<?php


namespace App\Http\Controllers;


use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CheckoutController extends Controller
{

    public function postValidateTickets($event_id, Request $request){
        if (!$request->has('seats')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No seats selected',
            ]);
        }

        /*
        * Order expires after X min
        */
        $order_expires_time = Carbon::now()->addMinutes(env('CHECKOUT_TIMEOUT'));

        $event = Event::findOrFail($event_id);
        $seats = $request->get('seats');
    }



}
