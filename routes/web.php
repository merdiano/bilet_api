<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' =>'v1'], function () use ($router){
    $router->get('categories[/{parent_id}]','CategoryController@get_categories');
    $router->get('category/{cat_id}/events','CategoryController@showCategoryEvents');
    $router->get('sub_category/{cat_id?}/events','CategoryController@showSubCategoryEvents');
    $router->get('event/{id}/details','EventController@getEvent');
});
