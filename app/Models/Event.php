<?php


namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Event extends  Model
{
    protected $dates = ['start_date', 'end_date', 'on_sale_date'];
    protected $table = 'events';
    /**
     * The images associated with the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany(\App\Models\EventImage::class);
    }

    /**
     * The tickets associated with the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany(\App\Models\Ticket::class);
    }

    public function starting_ticket(){
        return $this->tickets()
            ->select('id','ticket_date','event_id','price')
            ->where('ticket_date','>=',Carbon::now(\config('app.timezone')))
            ->orderBy('ticket_date')
            ->orderBy('price')
            ->limit(2); // limit 1 returns null ???
    }
    /**
     * The stats associated with the event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function stats()
    {
        return $this->hasMany(\App\Models\EventStats::class);
    }

    public function views(){
        return $this->stats()->sum('views');
    }
    public function venue(){
        return $this->belongsTo(Venue::class);
    }
    /**
     * Category associated with the event
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function mainCategory(){
        return $this->belongsTo(Category::class,'category_id');
    }

    /**
     * Sub category associated with the event
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subCategory(){
        return $this->belongsTo(Category::class,'sub_category_id');
    }

}
