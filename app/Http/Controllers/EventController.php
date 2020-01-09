<?php


namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Event;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function getMain(){
        return Category:: main()
            ->whereHas('events')
            ->categoryLiveEvents(10)
            ->get();

    }

    public function getEvent($id){

        //todo handle if not found
        $event = Event::findOrFail($id,[
                "id",
                "title",
                "description",
                "start_date",
                "end_date"
        ]);

         $tickets = Ticket::select('ticket_date')
             ->where('event_id',$id)
             ->where('is_hidden', false)
             ->where('ticket_date','>=',Carbon::now())
             ->orderBy('ticket_date', 'asc')
             ->groupBy('ticket_date')
             ->distinct()
             ->get();


        $ticket_dates = array();

        foreach ($tickets as $ticket){
            $date = $ticket->ticket_date->format('Y-m-d');
            $ticket_dates[$date][] = $ticket;
        }

        return response()->json([
            'event' => $event,
            'tickets' => $ticket_dates,
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

        if($tickets->count()==0)
            return response()->json([
               'status' => 'error',
               'message' => 'There is no tickets available'
            ]);

        return response()->json([
            'venue' => $event->venue,
            'tickets' => $tickets
        ]);
    }
}
