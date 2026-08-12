<?php

namespace App\Http\Controllers;

use App\Models\CandidateApplication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Aggregate-only diversity/fairness view: pipeline pass-through rates by nationality
 * (the only demographic field this system already collects — no new sensitive data is
 * gathered for this feature). Groups smaller than MIN_GROUP_SIZE are folded into
 * "Other (small groups)" so no individual candidate is identifiable from the breakdown.
 * This is a v1 monitoring aid, not a compliance certification — legal/HR review is
 * recommended before using it to inform hiring policy.
 */
class BiasMonitoringController extends Controller
{
    private const MIN_GROUP_SIZE = 5;

    private const PAST_SCREENING = ['SHORTLISTED', 'PHONE_SCREENING', 'INTERVIEW_SCHEDULED', 'INTERVIEWED', 'OFFER_SENT', 'HIRED'];
    private const PAST_INTERVIEW = ['INTERVIEWED', 'OFFER_SENT', 'HIRED'];

    public function index(): View
    {
        $user = Auth::user();

        $rows = CandidateApplication::query()
            ->join('candidates', 'candidates.id', '=', 'candidate_applications.candidate_id')
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where('candidate_applications.company_id', $user->company_id))
            ->select('candidates.nationality', 'candidate_applications.current_stage', DB::raw('count(*) as cnt'))
            ->groupBy('candidates.nationality', 'candidate_applications.current_stage')
            ->get();

        $byNationality = [];
        foreach ($rows as $row) {
            $key = $row->nationality ?: 'Not specified';
            $byNationality[$key] ??= ['applied' => 0, 'shortlisted' => 0, 'interviewed' => 0, 'hired' => 0];
            $byNationality[$key]['applied'] += $row->cnt;
            if (in_array($row->current_stage, self::PAST_SCREENING, true)) {
                $byNationality[$key]['shortlisted'] += $row->cnt;
            }
            if (in_array($row->current_stage, self::PAST_INTERVIEW, true)) {
                $byNationality[$key]['interviewed'] += $row->cnt;
            }
            if ($row->current_stage === 'HIRED') {
                $byNationality[$key]['hired'] += $row->cnt;
            }
        }

        $visible = [];
        $suppressed = ['applied' => 0, 'shortlisted' => 0, 'interviewed' => 0, 'hired' => 0];
        foreach ($byNationality as $nationality => $counts) {
            if ($counts['applied'] < self::MIN_GROUP_SIZE) {
                foreach ($counts as $k => $v) {
                    $suppressed[$k] += $v;
                }
                continue;
            }
            $visible[$nationality] = $counts;
        }
        if ($suppressed['applied'] > 0) {
            $visible['Other (small groups)'] = $suppressed;
        }

        ksort($visible);

        return view('bias-monitoring.index', ['funnel' => $visible, 'minGroupSize' => self::MIN_GROUP_SIZE]);
    }
}
