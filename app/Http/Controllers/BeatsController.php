<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BeatsController extends Controller
{
    public function index()
    {
    //     DB::unprepared("

    // ");
        return view('welcome');
    }
}
