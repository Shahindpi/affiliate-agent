<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\RssFeedController;

Route::get('/', function () {
    return view('welcome');
});


/*
|--------------------------------------------------------------------------
| Sitemap
|--------------------------------------------------------------------------
*/

Route::get('/sitemap.xml', [
    SitemapController::class,
    'index',
]);

/*
|--------------------------------------------------------------------------
| Robots.txt
|--------------------------------------------------------------------------
*/

Route::get('/robots.txt', [
    RobotsController::class,
    'index',
]);

/*
|--------------------------------------------------------------------------
| RSS Feed
|--------------------------------------------------------------------------
*/

Route::get('/feed.xml', [
    RssFeedController::class,
    'index',
]);