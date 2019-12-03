<?php


namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Event;

class EventController extends Controller
{
    public function getMain(){
        return Category:: main()
            ->categoryLiveEvents(4)
            ->whereHas('events')
            ->get();

    }

    public function getEvent($id){
        $event = Event::with('images')->findOrFail($id);
        return response()->json($event);
    }
}
