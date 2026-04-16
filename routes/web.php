<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home');
Route::view('/contenedor', 'contenedor');

Route::get('/contenedor/launch', function (Request $request) {
	$url = (string) env('WEBTOP_URL', 'https://example.com');

	return redirect()->away($url);
});
