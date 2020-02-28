<?php


namespace App\Http\Controllers;


use App\Models\Category;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CategoryController extends Controller
{

    public function get_categories($parent_id = false){
        $categories = Category::select('title_tk','title_ru','id','parent_id');

        if($parent_id)
            $categories->children($parent_id);
        else
            $categories->main();
        return response()->json($categories->get());
    }

    public function showCategoryEvents($cat_id, Request $request){

        [$order, $data] = $this->sorts_filters($request);

        $data['sub_cats'] = Category::where('parent_id',$cat_id)
            ->select('id','title_ru','title_tk','parent_id','lft')
            ->orderBy('lft')
            ->withLiveEvents($order, $data['start'], $data['end'])
            ->whereHas('cat_events',
                function ($query) use($data){
                    $query->onLive($data['start'], $data['end']);
                })->get();

        return response()->json($data);
    }

    private function sorts_filters($request){
        $data['start'] = $request->get('start') ?? Carbon::today();
        $data['end'] = $request->get('end')?? Carbon::today()->endOfCentury();
        $sort = $request->get('sort');

        if($sort == 'new')
            $orderBy = ['field'=>'created_at','order'=>'desc'];
        if ($sort =='popular')
            $orderBy = ['field'=>'views','order'=>'desc'];
        else
        {
            $orderBy =['field'=>'start_date','order'=>'asc'];
            $sort = 'start_date';
        }
        $data['sort'] = $sort;
        //todo check date formats;
        return [$orderBy, $data];
    }
    public function showSubCategoryEvents($cat_id, Request $request){
        [$order, $data] = $this->sorts_filters($request);

        return Event::where('sub_category_id',$cat_id)
            ->select('id','title_ru','title_tk')
            ->onLive($data['start'],$data['end'])
            ->orderBy($order['field'],$order['order'])
            ->paginate();

    }
}
