<?php


namespace App\Http\Controllers;


use App\Models\Event;

class EventController extends Controller
{
    public function getEvent($id){
        $event = Event::with('images')->findOrFail($id);
        return response()->json($event);
    }
}
