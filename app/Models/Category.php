<?php


namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Category extends Model
{
    /**
     * Indicates whether the model should be timestamped.
     *
     * @var bool $timestamps
     */
    public $timestamps = false;
    /**
     * The database table used by the model.
     *
     * @var string $table
     */
    protected $table = 'categories';
    /**
     * The events associated with the category.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function events(){
        return $this->hasMany(\App\Models\Event::class);
    }

    public function cat_events(){
        return $this->hasMany(\App\Models\Event::class,'sub_category_id')
            ->withCount(['stats as views' => function($q){
                $q->select(DB::raw("SUM(views) as v"));
            }]);
    }

    public function scopeCategoryLiveEvents($query,$limit){
//        dd($this->view_type);
        return $query->select('id','title_tk','title_ru','lft')
            ->orderBy('lft')
            ->with(['events' => function($q) use($limit){
                $q->select('id','title','description','category_id','sub_category_id','start_date')
                    ->limit($limit)
                    ->with('starting_ticket')
                    ->withCount(['stats as views' => function($q){
                        $q->select(DB::raw("SUM(views) as v"));
                    }])
                    ->onLive();
            }]);
    }

    public function parent(){
        return $this->belongsTo(Category::class,'parent_id');
    }

    public function children(){
        return $this->hasMany(Category::class,'parent_id')
            ->select('id','title_ru','title_tk','parent_id','lft')
            ->orderBy('lft');
    }
    public function scopeMain($query){
        return $query->where('depth',1)->orderBy('lft','asc');
    }

    public function scopeSub($query){
        return $query->where('depth',2)->orderBy('lft','asc');
    }

    public function scopeChildren($query,$parent_id){
        return $query->where('parent_id',$parent_id)->orderBy('lft','asc');
    }

    public function scopeWithLiveEvents($query, $orderBy, $start_date = null,$end_date = null, $limit = 8 ){
        return $query->with(['cat_events' => function($query) use ($start_date, $end_date, $limit, $orderBy) {
            $query->select('id','title','description','category_id','sub_category_id','start_date')
                ->limit($limit)
                //->with('starting_ticket')
                //->withCount(['stats as views' => function($q){
                //    $q->select(DB::raw("SUM(views) as v"));}])
                ->onLive($start_date, $end_date)//event scope onLive get only live events
                ->orderBy($orderBy['field'],$orderBy['order']);
        }]);

    }
}
