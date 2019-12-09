<?php


namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function getMain(){
        return Category:: main()
            ->whereHas('events')
            ->categoryLiveEvents(4)
            ->get();

    }

    public function getEvent($id){
        $event = Event::with('images')->findOrFail($id);
        $now =Carbon::now(config('app.timezone'));
        $tickets = $event->tickets()->select('id','ticket_date')
            ->where('is_hidden', false)
            ->where('ticket_date','>=',$now)
            ->orderBy('ticket_date', 'asc')
            ->groupBy('ticket_date')
            ->distinct()
            ->get();

        $ticket_dates = array();

        foreach ($tickets as $ticket){
            $date = $ticket->ticket_date->format('d M');
            $ticket_dates[$date][] = $ticket;
        }

        return response()->json([
            'event' => $event,
            'ticket_dates' =>$ticket_dates,
        ]);
    }

    public function getEventSeats(Request $request,$event_id){
        $this->validate($request,['ticket_date'=>'required|date']);
        $event = Event::with('venue')->findOrFail($event_id,['id','venue_id']);

        $tickets = Ticket::with(['section','reserved:seat_no,ticket_id','booked:seat_no,ticket_id'])
            ->where('event_id',$event_id)
            ->where('ticket_date',$request->get('ticket_date'))
            ->where('is_hidden', false)
            ->orderBy('sort_order','asc')
            ->get();
        return response()->json([
            'venue' => $event->venue,
            'tickets' => $tickets
        ]);
    }
}
