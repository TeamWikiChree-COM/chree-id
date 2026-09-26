<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('example::Index', ['greeting' => config('example.greeting')]));
