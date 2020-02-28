<?php


namespace App\Http\Controllers;


use App\Models\Page;

class PageController extends Controller
{
    public function index($slug)
    {
        $page = Page::findBySlug($slug);

        if (!$page)
        {
            return response()->json(['message' => 'not found'],404);
        }


        return response()->json(['page' => $page]);
    }

}
