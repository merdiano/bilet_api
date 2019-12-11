<?php


namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    protected $dates = ['start_sale_date', 'end_sale_date','ticket_date'];

    /**
     * The event associated with the ticket.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo(\App\Models\Event::class);
    }

    public function section(){
        return $this->belongsTo(Section::class);
    }

    public function reserved()
    {
        return $this->hasMany(ReservedTickets::class)
            ->where('expires','>',Carbon::now())
            ->orderBy('seat_no','asc');
    }

    public function booked(){
        return $this->hasMany(Attendee::class)
            ->orderBy('seat_no','asc');
    }
}
