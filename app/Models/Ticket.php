<?php


namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    /**
     * Get the booking fee of the ticket.
     *
     * @return float|int
     */
    public function getBookingFeeAttribute()
    {
        return (int)ceil($this->price) === 0 ? 0 : round(
            ($this->price * (env('ticket_booking_fee_percentage') / 100)) + (env('ticket_booking_fee_fixed')),
            2
        );
    }

    /**
     * Get the organizer's booking fee.
     *
     * @return float|int
     */
    public function getOrganiserBookingFeeAttribute()
    {
        return (int)ceil($this->price) === 0 ? 0 : round(
            ($this->price * ($this->event->organiser_fee_percentage / 100)) + ($this->event->organiser_fee_fixed),
            2
        );
    }

    /**
     * Get the total price of the ticket.
     *
     * @return float|int
     */
    public function getTotalPriceAttribute()
    {
        return $this->getTotalBookingFeeAttribute() + $this->price;
    }

    /**
     * Get the total booking fee of the ticket.
     *
     * @return float|int
     */
    public function getTotalBookingFeeAttribute()
    {
        return $this->getBookingFeeAttribute() + $this->getOrganiserBookingFeeAttribute();
    }

    /**
     * Get the number of tickets remaining.
     *
     * @return \Illuminate\Support\Collection|int|mixed|static
     */
    public function getQuantityRemainingAttribute()
    {
        if (is_null($this->quantity_available)) {
            return 9999; //Better way to do this?
        }

        return $this->quantity_available - ($this->quantity_sold + $this->quantity_reserved);
    }

    /**
     * Get the number of tickets reserved.
     *
     * @return mixed
     */
    public function getQuantityReservedAttribute()
    {
        $reserved_total = DB::table('reserved_tickets')
            ->where('ticket_id', $this->id)
            ->where('expires', '>', Carbon::now())
            ->sum('quantity_reserved');

        return $reserved_total;
    }

    public function scopeWithSection($query,$event_id,$ticket_date){
        return $query->select('id','title','description',"price", "max_per_person", "min_per_person","start_sale_date","end_sale_date","ticket_date","section_id")
            ->with(['section:id,section_no,description,seats,section_no_ru,description_ru,section_no_tk,description_tk','reserved:seat_no,ticket_id','booked:seat_no,ticket_id'])
            ->where('event_id',$event_id)
            ->where('ticket_date',$ticket_date)
            ->orderBy('sort_order','asc');
    }
}
