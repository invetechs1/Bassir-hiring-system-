<?php

namespace App\Http\Controllers;

use App\Models\OnboardingRecord;
use App\Services\AuditService;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class OnboardingController extends Controller
{
    public function index(TenantService $tenant): View
    {
        return view('onboarding.index', [
            'records' => $tenant->scope(OnboardingRecord::with(['candidate', 'application.job', 'tasks']), Auth::user())->latest()->get(),
        ]);
    }

    public function completeTask(int $onboardingRecordId, int $taskId, AuditService $audit): RedirectResponse
    {
        $record = OnboardingRecord::findOrFail($onboardingRecordId);
        $this->authorizeTenant($record);
        $task = $record->tasks()->findOrFail($taskId);
        $task->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        if ($record->tasks()->where('status', '!=', 'COMPLETED')->doesntExist()) {
            $record->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        }

        $audit->log(Auth::id(), 'ONBOARDING_TASK_COMPLETE', 'onboarding_tasks', (string) $task->id, [], request());

        return back()->with('status', 'Onboarding task marked complete');
    }

    private function authorizeTenant(OnboardingRecord $record): void
    {
        $user = Auth::user();
        if ($user && ! $user->isSuperAdmin() && $record->company_id !== $user->company_id) {
            abort(404);
        }
    }
}
