<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'sodars.portal-shell', ['portal' => 'Website Marketplace', 'domain' => 'www.sodars.com']);
Route::view('/admin', 'sodars.portal-shell', ['portal' => 'Admin ERP Portal', 'domain' => 'admin.sodars.com']);
Route::view('/business', 'sodars.portal-shell', ['portal' => 'Business Provider Portal', 'domain' => 'business.sodars.com']);
Route::view('/agents', 'sodars.portal-shell', ['portal' => 'Agents CRM Portal', 'domain' => 'agents.sodars.com']);
