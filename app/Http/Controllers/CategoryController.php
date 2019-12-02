<?php


namespace App\Http\Controllers;


use App\Models\Category;
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

        $category = Category::select('id','title_tk','title_ru','view_type','events_limit','parent_id')
            ->findOrFail($cat_id);

        [$order, $data] = $this->sorts_filters($request);
        $data['category'] = $category;
        $data['sub_cats'] = $category->children()
            ->withLiveEvents($order, $data['start'], $data['end'], $category->events_limit)
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
    public function showSubCategoryEvents($cat_id){
        $category = Category::select('id','title_tk','title_ru','view_type','events_limit','parent_id')
            ->findOrFail($cat_id);

        [$order, $data] = $this->sorts_filters();

        $data['category'] = $category;

        $data['events'] = $category->cat_events()
            ->onLive($data['start'],$data['end'])
            ->orderBy($order['field'],$order['order'])
            ->get();

        return response()->json($data);
    }
}
