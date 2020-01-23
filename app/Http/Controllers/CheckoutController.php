<?php


namespace App\Http\Controllers;


use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventStats;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QuestionAnswer;
use App\Models\ReservedTickets;
use App\Models\Ticket;
use App\Payment\CardPayment;
use App\Services\Order as OrderService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CheckoutController extends Controller
{
    /**
     * Payment gateway
     * @var CardPayment
     */
//    protected $gateway;
//
//    /**
//     * EventCheckoutController constructor.
//     * @param Request $request
//     */
//    public function __construct(Request $request, CardPayment $gateway)
//    {
//
//        $this->gateway = $gateway;
//    }

    public function postReserveTickets( Request $request,$event_id){
        try {


            if (!$request->has('tickets')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No seats selected',
                ]);
            }

            if (!$request->has('phone_id')) {
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

            $order_total = 0;
            $booking_fee = 0;
            $organiser_booking_fee = 0;
            $total_ticket_quantity = 0;
            $reserved = [];
            $tickets = [];
            $selectedSeats = json_decode($request->get('tickets'), true);
//        dd($selectedSeats);

            foreach ($selectedSeats as $ticket) {
                $ticket_id = $ticket['ticket_id'];
                $seats_count = count($ticket['seats']);
                if ($seats_count < 1)
                    continue;

                $seat_nos = $ticket['seats'];
                $reserved_tickets = ReservedTickets::where('ticket_id', $ticket_id)
                    ->where('expires', '>', Carbon::now())
                    ->whereIn('seat_no', $seat_nos)
                    ->pluck('seat_no');

                $booked_tickets = Attendee::where('ticket_id', $ticket_id)
                    ->where('event_id', $event_id)
                    ->whereIn('seat_no', $seat_nos)
                    ->pluck('seat_no');

                if (count($reserved_tickets) > 0 || count($booked_tickets) > 0)
                    return response()->json([
                        'status' => 'error',
                        'messages' => 'Your selected seats are already reserved or booked please choose other seats',//todo show which are reserved
                    ]);

                $eventTicket = Ticket::with('event:id,organiser_fee_fixed,organiser_fee_percentage')
                    ->findOrFail($ticket_id);

                $max_per_person = min($eventTicket->quantity_remaining, $eventTicket->max_per_person);
                /*
                 * Validation max min ticket count
                 */
                if ($seats_count < $eventTicket->min_per_person) {
                    $message = 'You must select at least ' . $eventTicket->min_per_person . ' tickets.';
                } elseif ($seats_count > $max_per_person) {
                    $message = 'The maximum number of tickets you can register is ' . $max_per_person;
                }

                if (isset($message)) {
                    return response()->json([
                        'status' => 'error',
                        'messages' => $message,
                    ]);
                }

                $total_ticket_quantity += $seats_count;
                $order_total += ($seats_count * $eventTicket->price);
                $booking_fee += ($seats_count * $eventTicket->booking_fee);
                $organiser_booking_fee += ($seats_count * $eventTicket->organiser_booking_fee);
                $tickets[] = [
                    'ticket_id' => $ticket_id,
                    'qty' => $seats_count,
                    'seats' => $seat_nos,
                    'price' => ($seats_count * $eventTicket->price),
                    'booking_fee' => ($seats_count * $eventTicket->booking_fee),
                    'organiser_booking_fee' => ($seats_count * $eventTicket->organiser_booking_fee),
                    'full_price' => $eventTicket->price + $eventTicket->total_booking_fee,
                ];

                foreach ($seat_nos as $seat_no) {
                    $reservedTickets = new ReservedTickets();
                    $reservedTickets->ticket_id = $ticket_id;
                    $reservedTickets->event_id = $event_id;
                    $reservedTickets->quantity_reserved = 1;
                    $reservedTickets->expires = $order_expires_time;
                    $reservedTickets->session_id = $request->get('phone_id');
                    $reservedTickets->seat_no = $seat_no;
                    $reserved[] = $reservedTickets->attributesToArray();
                }

            }

            ReservedTickets::insert($reserved);

            return response()->json([
                'status' => 'success',
//            'event_id'                => $event_id,
//            'tickets'                 => $tickets,
                'total_ticket_quantity' => $total_ticket_quantity,
                'order_started' => Carbon::now(),
                'expires' => env('CHECKOUT_TIMEOUT'),
                'order_total' => $order_total,
                'booking_fee' => $booking_fee,
                'organiser_booking_fee' => $organiser_booking_fee,
            ]);
        }
        catch (\Exception $ex){
            return response()->json([
                'status' => 'error',
                'message' => $ex->getMessage()
            ]);
        }
    }

    public function postRegisterOrder(Request $request, $event_id){

        $gateway = new CardPayment();

        $validator = Validator::make($request->all(),
            [
                'phone_id'=>'required|string|min:8|max:45',
                'name'=>'required|string|min:2|max:255',
                'surname'=>'required|string|min:2|max:255',
                'email'=>'required|email'
            ]);

        if($validator->fails()){
            return response()->json([
                'status'  => 'error',
                'message' => 'Please enter correctly',
            ]);
        }

        $phone_id = $request->get('phone_id');
        $holder_name = $request->get('name');
        $holder_surname = $request->get('surname');
        $holder_email = $request->get('email');

        $event = Event::withReserved($phone_id)
            ->findOrFail($event_id,['id','organiser_fee_fixed','organiser_fee_percentage']);

        if(empty($event->reservedTickets) || $event->reservedTickets->count() == 0){
            return response()->json([
                'status'  => 'error',
                'message' => 'Session expired',
            ]);
        }

        $order_total = 0;
        $total_booking_fee = 0;
        $booking_fee = 0;
        $organiser_booking_fee = 0;

//        DB::beginTransaction();

        foreach ($event->reservedTickets as $reserve){
            $order_total += $reserve->ticket->price;
            $booking_fee += $reserve->ticket->booking_fee;
            $organiser_booking_fee += $reserve->ticket->organiser_booking_fee;
            $total_booking_fee += $reserve->ticket->total_booking_fee;

//            $reserve->holder_name = $holder_name;
//            $reserve->holder_surname = $holder_surname;
//            $reserve->holder_email = $holder_email;
//            $reserve->save();
        }
//        DB::commit();

        $orderService = new OrderService($order_total, $total_booking_fee, $event);
        $orderService->calculateFinalCosts();

        $secondsToExpire = Carbon::now()->diffInSeconds($event->reservedTickets->first()->expires);

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
            $response = $gateway->registerPayment($transaction_data);

            if($response->isSuccessfull()){
                /*
                 * As we're going off-site for payment we need to store some data in a session so it's available
                 * when we return
                 */
                $order = new Order();
                $order->first_name = ($holder_name);//todo sanitize etmelimi?
                $order->last_name = ($holder_surname);
                $order->email = ($holder_email);
                $order->order_status_id = 5;//order awaiting payment
                $order->amount = $order_total;
                $order->booking_fee = $booking_fee;
                $order->organiser_booking_fee = $organiser_booking_fee;
                $order->discount = 0.00;
                $order->account_id = $event->account->id;
                $order->event_id = $event_id;
                $order->is_payment_received = 0;//false
                $order->taxamt = $orderService->getTaxAmount();
                $order->session_id = $phone_id;
                $order->transaction_id = $response->getPaymentReferenceId();
                $order->order_date = Carbon::now();
                $order->save();
                $return = [
                    'status' => 'success',
                    'order'  => $order,
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

    public function postCompleteOrder(Request $request, $event_id,CardPayment $gateway){
        $orderId = $request->get('orderId');

        try{
            $response = $gateway->getPaymentStatus($orderId);

            if ($response->isSuccessfull()) {

                return $this->completeOrder($event_id,$request);
            } else {

                return response()->json([
                    'status'          => 'error',
                    'message' => $response->errorMessage(),
                ]);
            }
        }catch (\Exception $ex){
            return response()->json([
                'status'          => 'error',
                'message' => $ex->getMessage(),
            ]);
        }

    }

    private function completeOrder($event_id, $request){
        DB::beginTransaction();

        try {

            $order = Order::select('amount', 'booking_fee', 'orgenizer_booking_fee', 'taxamt', 'first_name', 'last_name', 'email')
                ->where('event_id', $event_id)
                ->where('session_id', $request['phone_id'])
                ->where('transaction_id', $request['order_id'])
                ->first();

            $grand_total = $order->amount + $order->booking_fee + $order->orgenizer_booking_fee + $order->taxamt;

            /*
             * Update the event sales volume
             */
            $event = Event::findOrfail($event_id, ['id', 'sales_volume', 'organiser_fees_volume']);
            $event->increment('sales_volume', $grand_total);
            $event->increment('organiser_fees_volume', $order->organiser_booking_fee);

            $reserved_tickets = ReservedTickets::select('id', 'seat_no', 'ticket_id')
                ->with(['ticket:id,quantity_sold,sales_volume,organiser_fees_volume,price,organiser_booking_fee'])
                ->where('session_id', $request['phone_id'])
                ->where('event_id', $event_id)
                ->get();
            /*
             * Update the event stats
             */
            $event_stats = EventStats::updateOrCreate([
                'event_id' => $event_id,
                'date' => DB::raw('CURRENT_DATE'),
            ]);

            $event_stats->increment('tickets_sold', $reserved_tickets->count() ?? 0);
            $event_stats->increment('sales_volume', $order->amount);
            $event_stats->increment('organiser_fees_volume', $order->organiser_booking_fee);
            $attendee_increment = 1;
            /*
             * Add the attendees
             */

            foreach ($reserved_tickets as $reserved) {

                $ticket = $reserved->ticket;

                /*
                 * Update some ticket info
                 */
                $ticket->increment('quantity_sold', $reserved->quantity);//$reserved->quantity_reserved);
                $ticket->increment('sales_volume', $ticket->price);
                $ticket->increment('organiser_fees_volume', $ticket->orgniser_booking_fee);// * $reserved->quantity_reserved

                /*
                 * Insert order items (for use in generating invoices)
                 */
                $orderItem = new OrderItem();
                $orderItem->title = $ticket->title;
                $orderItem->quantity = 1;
                $orderItem->order_id = $order->id;
                $orderItem->unit_price = $ticket->price;
                $orderItem->unit_booking_fee = $ticket->booking_fee + $ticket->organiser_booking_fee;
                $orderItem->save();

                /*
                 * Create the attendees
                 */
                $attendee = new Attendee();
                $attendee->first_name = $order->first_name;
                $attendee->last_name = $order->last_name;
                $attendee->email = $order->email;
                $attendee->event_id = $event_id;
                $attendee->order_id = $order->id;
                $attendee->ticket_id = $reserved->ticket_id;
                $attendee->account_id = $event->account->id;
                $attendee->reference_index = $attendee_increment;
                $attendee->seat_no = $reserved->seat_no;
                $attendee->save();

                /* Keep track of total number of attendees */
                $attendee_increment++;
            }


            DB::commit();
        }
        catch (\Exception $ex){

            Log::error($ex);
            DB::rollBack();

            return response()->json([
                'status'  => 'error',
                'message' => 'Whoops! There was a problem processing your order. Please try again.'
            ]);
        }

        /*
         * Remove any tickets the user has reserved after they have been ordered for the user
         */
        ReservedTickets::where('session_id', $request->get('phone_id'))->delete();

        //todo fire event
    }

}
