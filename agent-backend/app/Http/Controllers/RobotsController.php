<?php

namespace App\Http\Controllers;

class RobotsController extends Controller
{
    /**
     * Generate robots.txt
     */
    public function index()
    {
        return response()
            ->view('robots.index')
            ->header('Content-Type', 'text/plain');
    }
}