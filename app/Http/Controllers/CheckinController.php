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
            return response()->json(['status'=>'error','message'=>'ticket_date does not exists'],405);

        $ticket_date = $request->get('ticket_date');
        $attendess = Attendee::select('attendees.id','ticket_id','attendees.first_name','attendees.last_name',
            'attendees.email', 'seat_no','reference_index','has_arrived','arrival_time','orders.order_reference')
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

        $event = Event::where('id',$event_id)->where('user_id',$request->auth->id)->first();

        if(!empty($event) && $request->has('attendees')){
            $checks = json_decode($request->get('attendees'),true);
            dd($request->get('attendees',$checks));
            $arrivals = array_column($checks, 'arrival_time', 'id');
            $att_ids = array_column($checks, 'id');
            try{
                DB::beginTransaction();
                $attendees = Attendee::whereIn('id',$att_ids)->get();

                foreach ($attendees as $attendee){
                    $attendee->checked_in = true;
                    $attendee->arrival_time = $arrivals[$attendee->id];
                    $attendee->save();
                }
                DB::commit();

                return response()->json([
                    'status'=>'success'
                ]);

            }catch (\Exception $ex){
                DB::rollBack();
                return response()->json([
                    'status'=>'error',
                    'message' => $ex->getMessage()
                ]);
            }

        }
    }
}
