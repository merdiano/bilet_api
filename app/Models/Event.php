<?php


namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    public function scopeOnLive($query, $start_date = null, $end_date = null){
        //if date is null carbon creates now date instance
        if(isset($start_date) && isset($end_date))
            $query->where('start_date','<',$end_date)
                ->where('end_date','>',$start_date);
        else
            $query->where('end_date','>',Carbon::now(config('app.timezone')));

        return $query->where('is_live',1)
            ->withCount(['images as image_url' => function($q){
                $q->select(DB::raw("image_path as imgurl"))
                    ->orderBy('created_at','desc')
                    ->limit(1);
            }] );
    }

}
