<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\TenantService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, TenantService $tenant): View
    {
        $logs = $tenant->scope(AuditLog::query(), Auth::user())
            ->with('actor')
            ->when($request->action, fn ($query) => $query->where('action', 'like', '%'.$request->action.'%'))
            ->when($request->entity, fn ($query) => $query->where('entity', 'like', '%'.$request->entity.'%'))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('audit-logs.index', compact('logs'));
    }
}
