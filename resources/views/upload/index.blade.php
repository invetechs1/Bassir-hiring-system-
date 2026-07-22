@extends('layouts.app')
@section('title', 'Upload CV')
@section('content')
<form method="post" action="{{ route('upload.store') }}" enctype="multipart/form-data" class="card">
    @csrf
    <h2>Upload PDF, DOCX, DOC, or Image CV</h2>
    <p class="muted">Files are stored privately, parsed into structured records, and OCR is used for scanned image/PDF CVs when OCR API is configured.</p>
    <input type="file" name="cv" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required>
    <button class="btn" style="margin-top:18px">Parse CV</button>
</form>
<form method="post" action="{{ route('candidates.import') }}" enctype="multipart/form-data" class="card" style="margin-top:18px">
    @csrf
    <h2>Bulk Import Candidates</h2>
    <p class="muted">Upload CSV, XLS, or XLSX from hiring agencies or internal HR sheets.</p>
    <input type="file" name="file" accept=".csv,.xls,.xlsx" required>
    <button class="btn btn-dark" style="margin-top:18px">Import Candidates</button>
</form>
@endsection
