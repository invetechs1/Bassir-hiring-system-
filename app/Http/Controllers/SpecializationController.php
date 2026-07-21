<?php

namespace App\Http\Controllers;

use App\Models\Specialization;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class SpecializationController extends Controller
{
    public function index(): View
    {
        return view('specializations.index', [
            'specializations' => Specialization::orderBy('category')->orderBy('name')->paginate(30),
        ]);
    }

    public function create(): View
    {
        return view('specializations.create', ['specialization' => new Specialization(['category' => 'Engineering', 'is_active' => true])]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $specialization = Specialization::create($this->validated($request));
        $audit->log(Auth::id(), 'SPECIALIZATION_CREATE', 'specializations', (string) $specialization->id, $specialization->toArray(), $request);

        return redirect()->route('specializations.index')->with('status', 'Specialization created');
    }

    public function edit(Specialization $specialization): View
    {
        return view('specializations.create', compact('specialization'));
    }

    public function update(Request $request, Specialization $specialization, AuditService $audit): RedirectResponse
    {
        $specialization->update($this->validated($request));
        $audit->log(Auth::id(), 'SPECIALIZATION_UPDATE', 'specializations', (string) $specialization->id, $specialization->toArray(), $request);

        return redirect()->route('specializations.index')->with('status', 'Specialization updated');
    }

    public function destroy(Specialization $specialization, AuditService $audit, Request $request): RedirectResponse
    {
        $specialization->update(['is_active' => false]);
        $audit->log(Auth::id(), 'SPECIALIZATION_DISABLE', 'specializations', (string) $specialization->id, [], $request);

        return back()->with('status', 'Specialization disabled');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active')];
    }
}
