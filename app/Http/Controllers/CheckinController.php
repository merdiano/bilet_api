<?php


namespace App\Http\Controllers;


use App\Models\Attendee;
use App\Models\Event;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckinController extends Controller
{

    public function getAttendees(Request $request, $event_id){

        if(!$request->has('ticket_date'))
            return response()->json(['message'=>'error','message'=>'ticket_date does not exists'],400);

        $ticket_date = $request->get('ticket_date');
        $attendess = Attendee::select('attendees.id','ticket_id','attendees.first_name','attendees.last_name','private_reference_number',
            'attendees.email', 'seat_no','reference_index','has_arrived','arrival_time','orders.order_reference')
            ->join('tickets', 'tickets.id', '=', 'attendees.ticket_id')
            ->join('orders','orders.id','=','attendees.order_id')
            ->where(function ($query) use ($event_id,$ticket_date) {
                $query->where('attendees.event_id', $event_id)
                    ->where('tickets.ticket_date',$ticket_date)
                    ->where('attendees.is_cancelled',false);
            })
            ->get();

        return response()->json(['message'=>'success','attendees'=>$attendess]);
    }

    public function getTicketsAttendees(Request $request, $event_id){
        if(!$request->has('ticket_date'))
            return response()->json(['message'=>'error','message'=>'ticket_date does not exists'],400);

        $ticket_date = $request->get('ticket_date');

        $tickets = Ticket::select('id','section_id','event_id')
            ->with(['section','booked' => function($q){
            $q ->select('attendees.id','order_id','attendees.first_name','attendees.last_name',
                'private_reference_number', 'attendees.email', 'attendees.seat_no',
                'attendees.reference_index','attendees.has_arrived','attendees.arrival_time','orders.order_reference')
                ->join('orders','orders.id','=','attendees.order_id')
                ->where('attendees.is_cancelled',false);
            }])
            ->where('event_id',$event_id)
            ->where('ticket_date',$ticket_date)
            ->get();

        return response()->json(['message'=>'success','tickets' => $tickets]);
    }

    public function checkInAttendees(Request $request, $event_id){

        $event = Event::where('id',$event_id)->where('user_id',$request->auth->id)->first();

        if(!empty($event) && $request->has('attendees')){
            try{
            $checks = json_decode($request->get('attendees'),true);
//            dd($request->get('attendees'),$checks);
            $arrivals = array_column($checks, 'arrival_time', 'id');
            $att_ids = array_column($checks, 'id');

                DB::beginTransaction();
                $attendees = Attendee::whereIn('id',$att_ids)->get();

                foreach ($attendees as $attendee){
                    $attendee->has_arrived = true;
                    $attendee->arrival_time = $arrivals[$attendee->id];
                    $attendee->save();
                }
                DB::commit();

                return response()->json([
                    'message'=>'success'
                ]);

            }catch (\JsonException $ex){
                DB::rollBack();
                return response()->json([
                    'message' => $ex->getMessage(),
                    'attendees' => $request->get('attendees') ,
                ],200);
            }catch (\Exception $ex){

                return response()->json([
                    'message' => $ex->getMessage(),
                    'attendees' => $request->get('attendees')
                ],200);
            }

        }
        else
            return response()->json(['message' => 'provide valid event id and attendees array'],400);
    }

    public function getTickets(Request $request){
//        dd(url("../e/{$event_id}/checkout/finish_mobile"));
        if(!$request->has('phone_id')){
            return response()->json(['status'=>'error','message'=>'phone_id is required'],400);
        }

        $phone_id = $request->get('phone_id');

        $attendess = Attendee::select('attendees.first_name','attendees.last_name','attendees.email','events.title',
            'private_reference_number', 'seat_no','reference_index','orders.order_reference', 'venues.venue_name','tickets.ticket_date')
            ->join('orders','orders.id','=','attendees.order_id')
            ->join('events','events.id','=','attendees.event_id')
            ->join('venues', 'venues.id', '=', 'events.venue_id')
            ->join('tickets', 'tickets.id', '=', 'attendees.ticket_id')
            ->where(function ($query) use ($phone_id) {
                $query->where('orders.session_id', $phone_id)
                    ->where('orders.is_payment_received',1)
                    ->where('orders.order_status_id',1)
                    ->where('attendees.is_cancelled',0)
                    ->where('orders.is_deleted',0)
                    ->where('orders.is_cancelled',0)
                    ->where('orders.is_partially_refunded',0)
                    ->where('orders.is_refunded',0);
            })
            ->orderBy('attendees.id','DESC')
            ->paginate(20);

        return $attendess;

    }
}
