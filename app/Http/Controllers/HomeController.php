<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;

class HomeController extends Controller
{
    public function index()
    {
        $featuredItems = MenuItem::with('category')
            ->where('is_featured', true)
            ->where('is_available', true)
            ->limit(6)
            ->get();

        return view('customer.home.index', [
            'featuredItems' => $featuredItems,
        ]);
    }
}
