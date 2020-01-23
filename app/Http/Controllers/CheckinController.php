<?php


namespace App\Http\Controllers;


use App\Models\Attendee;
use App\Models\Ticket;
use Illuminate\Http\Request;

class CheckinController extends Controller
{

    public function getAttendees(Request $request, $event_id){

        $ticket_date = $request->get('ticket_date');
        $attendess = Attendee::select('id','ticket_id','first_name','last_name','email','seat_no','reference_index','has_arrived','arrival_time')
            ->join('tickets', 'tickets.id', '=', 'attendees.ticket_id')
            ->where(function ($query) use ($event_id,$ticket_date) {
                $query->where('attendees.event_id', $event_id)
                    ->where('tickets.ticket_date',$ticket_date)
                    ->where('attendees.is_cancelled',false);
            })
            ->get();

        return $attendess;

    }

    public function checkInAttendees(Request $request, $event_id){

    }
}
