<?php


namespace App\Http\Controllers;


use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\ReservedTickets;
use App\Models\Ticket;
use App\Payment\CardPayment;
use App\Services\Order as OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    /**
     * Payment gateway
     * @var CardPayment
     */
    protected $gateway;

    /**
     * EventCheckoutController constructor.
     * @param Request $request
     */
    public function __construct(Request $request, CardPayment $gateway)
    {

        $this->gateway = $gateway;
    }

    public function postValidateTickets($event_id, Request $request){
        if (!$request->has('seats')) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No seats selected',
            ]);
        }

        if(!$request->has('phone_id')){
            return reset()->json([
               'status' => 'error',
               'message' => 'Phone id required'
            ]);
        }

        /*
        * Order expires after X min
        */
        $order_expires_time = Carbon::now()->addMinutes(env('CHECKOUT_TIMEOUT'));

        /*
         * Remove any tickets the user has reserved
         */
        ReservedTickets::where('session_id', '=', $request->get('phone_id'))->delete();

//        $event = Event::findOrFail($event_id);
        $seats = $request->get('seats');

        $order_total = 0;
        $booking_fee = 0;
        $organiser_booking_fee = 0;
        $total_ticket_quantity = 0;
        $reserved = [];
        $tickets = [];

        foreach ($seats as $ticket_id=>$ticket_seats) {
            $seats_count = count($ticket_seats);
            if($seats_count<1)
                continue;

            $seat_nos = array_values($ticket_seats);
            $reserved_tickets = ReservedTickets::where('ticket_id',$ticket_id)
                ->where('expires','>',Carbon::now())
                ->whereIn('seat_no',$seat_nos)
                ->pluck('seat_no');

            $booked_tickets = Attendee::where('ticket_id',$ticket_id)
                ->where('event_id',$event_id)
                ->whereIn('seat_no',$seat_nos)
                ->pluck('seat_no');

            if(count($reserved_tickets)>0 || count($booked_tickets)>0)
                return response()->json([
                    'status'   => 'error',
                    'messages' => 'Some of selected seats are already reserved',//todo show which are reserved
                ]);

            $ticket = Ticket::with('event:id,organiser_fee_fixed,organiser_fee_percentage')
                ->findOrFail($ticket_id);

            $max_per_person = min($ticket->quantity_remaining, $ticket->max_per_person);
            /*
             * Validation max min ticket count
             */
            if($seats_count < $ticket->min_per_person){
                $message = 'You must select at least ' . $ticket->min_per_person . ' tickets.';
            }elseif ($seats_count > $max_per_person){
                $message = 'The maximum number of tickets you can register is ' . $ticket->quantity_remaining;
            }

            if (isset($message)) {
                return response()->json([
                    'status'   => 'error',
                    'messages' => $message,
                ]);
            }

            $total_ticket_quantity += $seats_count;
            $order_total += ($seats_count * $ticket->price);
            $booking_fee += ($seats_count * $ticket->booking_fee);
            $organiser_booking_fee += ($seats_count * $ticket->organiser_booking_fee);
            $tickets[] = [
                'ticket_id'                => $ticket->id,
                'qty'                   => $seats_count,
                'seats'                 => $ticket_seats,
                'price'                 => ($seats_count * $ticket->price),
                'booking_fee'           => ($seats_count * $ticket->booking_fee),
                'organiser_booking_fee' => ($seats_count * $ticket->organiser_booking_fee),
                'full_price'            => $ticket->price + $ticket->total_booking_fee,
            ];

            foreach ($ticket_seats as $seat_no) {
                $reservedTickets = new ReservedTickets();
                $reservedTickets->ticket_id = $ticket_id;
                $reservedTickets->event_id = $event_id;
                $reservedTickets->quantity_reserved = 1;
                $reservedTickets->expires = $order_expires_time;
                $reservedTickets->session_id = session()->getId();
                $reservedTickets->seat_no = $seat_no;
                $reserved[] = $reservedTickets->attributesToArray();
            }

        }

        ReservedTickets::insert($reserved);

        if (empty($tickets)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No tickets selected.',
            ]);
        }

        return response()->json([

            'event_id'                => $event_id,
            'tickets'                 => $tickets,
            'total_ticket_quantity'   => $total_ticket_quantity,
            'order_started'           => time(),
            'expires'                 => $order_expires_time,
            'order_total'             => $order_total,
            'booking_fee'             => $booking_fee,
            'organiser_booking_fee'   => $organiser_booking_fee,
            'total_booking_fee'       => $booking_fee + $organiser_booking_fee,

        ]);
    }

    public function postRegisterOrder(Request $request, $event_id){
        $phone_id = $request->get('phone_id');
        $holder_name = $request->get('name');
        $holder_surname = $request->get('name');
        $holder_email = $request->get('name');

        //todo validation

        $event = Event::withReserved($phone_id)
            ->findOrFail($event_id,['id','organiser_fee_fixed','organiser_fee_percentage']);

        if(empty($event->reserved_tickets) || $event->reserved_tickets->count == 0){
            return response()->json([
                'status'  => 'error',
                'message' => 'Session expired',
            ]);
        }

        $order_total = 0;
        $total_booking_fee = 0;
        $booking_fee = 0;
        $organiser_booking_fee = 0;

        DB::beginTransaction();
        foreach ($event->reserved_tickets as $reserve){
            $order_total += $reserve->ticket->price;
            $booking_fee += $reserve->ticket->booking_fee;
            $organiser_booking_fee += $reserve->ticket->organiser_booking_fee;
            $total_booking_fee += $reserve->ticket->total_booking_fee;

            $reserve->holder_name = $holder_name;
            $reserve->holder_surname = $holder_surname;
            $reserve->holder_email = $holder_email;
            $reserve->save();
        }
        DB::commit();

        $orderService = new OrderService($order_total, $total_booking_fee, $event);
        $orderService->calculateFinalCosts();

        $secondsToExpire = Carbon::now()->diffInSeconds($event->reserved_tickets->first()->expires);

        $transaction_data = [
            'amount'      => $orderService->getGrandTotal()*100,//multiply by 100 to obtain tenge
            'currency' => 934,
            'sessionTimeoutSecs' => $secondsToExpire,
            'description' => 'Order for customer: ' . $request->get('order_email'),
            'orderNumber'     => uniqid(),
            'failUrl'     => route('showEventCheckoutPaymentReturn', [
                'event_id'             => $event_id,
                'is_payment_cancelled' => 1
            ]),
            'returnUrl' => route('showEventCheckoutPaymentReturn', [
                'event_id'              => $event_id,
                'is_payment_successful' => 1
            ]),

        ];
        try{
            $response = $this->gateway->registerPayment($transaction_data);

            if($response->isSuccessfull()){
                /*
                 * As we're going off-site for payment we need to store some data in a session so it's available
                 * when we return
                 */

                $return = [
                    'status'       => 'success',
                    'orderId'  => $response->getPaymentReferenceId(),
                ];

            } else {
                // display error to customer
                $return = [
                    'status'  => 'error',
                    'message' => $response->errorMessage(),
                ];
            }


        }
        catch (\Exeption $e) {
            $return = [
                'status'  => 'error',
                'message' => 'Sorry, there was an error processing your payment. Please try again later.',
            ];

        }
        return response()->json($return);
    }

    public function postCompleteOrder(Request $request, $event_id){
        $orderId = $request->get('orderId');
        $pone_id = $request->get('phone_id');
        //todo check order status then complete order

        try{
            $response = $this->gateway->getPaymentStatus($orderId);

            if ($response->isSuccessfull()) {

                return $this->completeOrder($event_id, false);
            } else {
                session()->flash('message', $response->errorMessage());
                return response()->json([
                    'status'          => 'error',
                    'message' => $response->errorMessage(),
                ]);
            }
        }catch (\Exception $ex){

        }


    }

    public function postCancellReserveration(Request $request){
        $phone_id = $request->get('phone_id');
    }
}
