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
    $router->get('home','EventController@index');

    $router->get('main','EventController@getMain');

    $router->post('search','EventController@search');

    $router->get('categories[/{parent_id}]','CategoryController@get_categories');

    $router->get('category/{cat_id}/events','CategoryController@showCategoryEvents');

    $router->get('sub_category/{cat_id}/events','CategoryController@showSubCategoryEvents');

    $router->get('event/{id}/details','EventController@getEvent');

    $router->post('event/{id}/seats','EventController@getEventSeats');

    $router->get('event/{id}/seats','EventController@getEventSeats');

    $router->post('event/{id}/reserve','CheckoutController@postReserveTickets');

    $router->post('event/{id}/register_order','CheckoutController@postRegisterOrder');

    $router->post('event/{id}/checkout','CheckoutController@postCompleteOrder');

    $router->get('my_tickets','CheckinController@getTickets');
});

$router->post('auth/login', 'AuthController@authenticate');

$router->group(
    ['middleware' => 'jwt.auth','prefix'=>'vendor'],
    function() use ($router) {
        $router->get('events', 'EventController@getVendorEvents');

        $router->get('event/{id}/details', 'EventController@getVendorEvent');

        $router->get('event/{id}/attendees', 'CheckinController@getAttendees');

        $router->get('event/{id}/ticket_attendees', 'CheckinController@getTicketsAttendees');

        $router->post('event/{id}/checkin', 'CheckinController@checkInAttendees');

        $router->post('event/{id}/book', 'CheckoutController@offline_book');

        $router->post('event/{id}/book_cancel', 'CheckoutController@offline_cancel');
    }
);

/** CATCH-ALL ROUTE for Backpack/PageManager - needs to be at the end of your routes.php file  **/
$router->get('page/{slug}', 'PageController@index');
