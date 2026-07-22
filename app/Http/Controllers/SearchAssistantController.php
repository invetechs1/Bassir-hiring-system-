<?php

namespace App\Http\Controllers;

use App\Services\TalentSearchAssistantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SearchAssistantController extends Controller
{
    public function index(Request $request, TalentSearchAssistantService $assistant): View
    {
        $query = (string) $request->input('q', '');
        $candidates = trim($query) !== '' ? $assistant->search($query, Auth::user()) : collect();

        return view('search-assistant.index', [
            'query' => $query,
            'candidates' => $candidates,
        ]);
    }
}
