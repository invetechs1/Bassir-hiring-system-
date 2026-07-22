<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(): View
    {
        return view('integrations.index', [
            'keys' => ApiKey::select('id', 'provider', 'status', 'last_used_at', 'created_at', 'updated_at')->orderBy('provider')->get(),
            'providers' => $this->providers(),
        ]);
    }

    public function store(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', 'string', 'in:'.implode(',', array_keys($this->providers()))],
            'value' => ['required', 'string', 'max:4000'],
            'status' => ['required', 'in:ACTIVE,PAUSED'],
        ]);
        $key = ApiKey::updateOrCreate(
            ['provider' => $data['provider']],
            ['encrypted_value' => Crypt::encryptString($data['value']), 'status' => $data['status']]
        );
        $audit->log(Auth::id(), 'API_KEY_UPSERT', 'api_keys', (string) $key->id, ['provider' => $data['provider']], $request);

        return back()->with('status', 'Integration key saved securely');
    }

    private function providers(): array
    {
        return [
            'openai' => 'OpenAI API Key',
            'google_cse_key' => 'Google Custom Search API Key',
            'google_cse_id' => 'Google Custom Search Engine ID (CX)',
            'bing_search' => 'Bing Search API Key',
            'serpapi' => 'SerpAPI Key',
            'agency_feed_url' => 'Agency Feed URL',
            'agency_feed_token' => 'Agency Feed Token',
            'ocr_space' => 'OCR Space API Key',
            // Auto-sourcing official partner connectors (token + endpoint required to activate).
            'linkedin_talent' => 'LinkedIn Talent API Token (official partner)',
            'linkedin_talent_endpoint' => 'LinkedIn Talent API Endpoint',
            'indeed_partner' => 'Indeed Partner API Key (official)',
            'indeed_endpoint' => 'Indeed Partner API Endpoint',
            'smtp_password' => 'SMTP Password',
            'whatsapp_token' => 'WhatsApp Token',
        ];
    }
}
