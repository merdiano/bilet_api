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
//            ->select('seat_no','ticket_id')
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
}
