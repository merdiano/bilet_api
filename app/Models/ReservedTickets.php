<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class ReservedTickets extends Model
{

    protected $dates = ['expects_payment_at'];
    public function ticket(){
        return $this->belongsTo(Ticket::class);
    }
}
