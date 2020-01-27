<?php


namespace App\Http\Controllers;


use App\Models\Attendee;
use App\Models\Ticket;
use Illuminate\Http\Request;

class CheckinController extends Controller
{

    public function getAttendees(Request $request, $event_id){

        if(!$request->has('ticket_date'))
            return response()->json(['status'=>'error','message'=>'ticket_date does not exists'],405);

        $ticket_date = $request->get('ticket_date');
        $attendess = Attendee::select('attendees.id','ticket_id','first_name','last_name','email','seat_no',
            'reference_index','has_arrived','arrival_time','private_reference_number','order_id','orders.order_reference')
            ->join('tickets', 'tickets.id', '=', 'attendees.ticket_id')
            ->join('orders','orders.id','=','attendees.order_id')
            ->where(function ($query) use ($event_id,$ticket_date) {
                $query->where('attendees.event_id', $event_id)
                    ->where('tickets.ticket_date',$ticket_date)
                    ->where('attendees.is_cancelled',false);
            })
            ->get();

        return response()->json(['status'=>'error','attendees'=>$attendess]);
    }

    public function checkInAttendees(Request $request, $event_id){

    }
}
