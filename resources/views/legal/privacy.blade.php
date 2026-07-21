@extends('layouts.app')
@section('title', 'Privacy Notice')
@section('content')
<section class="card">
    <h1>Privacy Notice</h1>
    <p class="muted">Bassir AI Recruitment System is designed to support lawful recruitment operations and candidate data protection.</p>
    <h2>Candidate Data</h2>
    <p>The system may store candidate profile data, CV documents, skills, experience, education, certifications, interview feedback, application stages, and recruiter notes.</p>
    <h2>Consent and Source Tracking</h2>
    <p>Recruiters must record candidate source, consent status, consent notes, and contact permission before outreach. LinkedIn data is manual URL import only and no protected-profile scraping is performed.</p>
    <h2>AI Assistance</h2>
    <p>AI summaries, scores, and recommendations are decision-support signals only. Human HR review is required before interview, offer, rejection, or hiring decisions.</p>
    <h2>Documents</h2>
    <p>CV files are stored in private storage and may be downloaded only by authorized users. File access is audit logged.</p>
    <h2>Retention</h2>
    <p>Company administrators should configure retention rules according to internal policy and applicable Saudi PDPL/GDPR obligations.</p>
    <p class="muted">Powered by Bassir Technology</p>
</section>
@endsection
