<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $templates = Template::active()
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        return view('welcome', compact('templates'));
    }
}
