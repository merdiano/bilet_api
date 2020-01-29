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

        return response()->json(['message'=>'success','attendees'=>$attendess]);
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
                ],400);
            }catch (\Exception $ex){

                return response()->json([
                    'message' => $ex->getMessage()
                ],400);
            }

        }
        else
            return response()->json(['message' => 'provide valid event id and attendees array'],400);
    }
}
