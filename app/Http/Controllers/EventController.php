<?php


namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Event;
use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $event = Event::select(
            "id",
            "title",
            "description",
            "start_date",
            "end_date")
            ->with('ticket_dates')
            ->WithViews()
            ->where('id',$id)

            ->first();


        $ticket_dates = array();

        foreach ($event->ticket_dates as $ticket){
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
        $event = Event::with('venue:id,venue_name,seats_image,address,venue_name_ru,venue_name_tk')
            ->findOrFail($event_id,['id','venue_id']);

        $tickets = Ticket::select('id','title','description',"price", "max_per_person", "min_per_person","start_sale_date","end_sale_date","ticket_date","section_id")
            ->with(['section:id,section_no,description,seats,section_no_ru,description_ru,section_no_tk,description_tk','reserved:seat_no,ticket_id','booked:seat_no,ticket_id'])
            ->where('event_id',$event_id)
            ->where('ticket_date',$request->get('ticket_date'))
            ->where('end_sale_date','>',Carbon::now())
            ->where('start_sale_date','<',Carbon::now())
            ->where('is_hidden', false)
            ->where('is_paused', false)
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

    public function search(Request $request){
        $key = $request->get('key');
    }

    public function getVendorEvents(Request $request){
        return $request->auth->events()
            ->select('id','title','start_date','end_date',"sales_volume","organiser_fees_volume","is_live")
            ->WithViews()
            ->with('ticket_dates')
            ->withCount(['images as image_url' => function($q){
                $q->select(DB::raw("image_path as imgurl"))
                    ->orderBy('created_at','desc')
                    ->limit(1);
            }] )
            ->orderBy('id','DESC')
            ->paginate(10);
    }

    public function getVendorEvent(Request $request,$event_id){
        return Event::with('ticket_dates')
            ->select("id", 'start_date','end_date',"sales_volume","organiser_fees_volume","is_live")
            ->WithViews()
            ->withCount(['images as image_url' => function($q){
                $q->select(DB::raw("image_path as imgurl"))
                    ->orderBy('created_at','desc')
                    ->limit(1);
            }] )
            ->where('id',$event_id)
            ->where('user_id',$request->auth->id)
            ->first();
    }
}
