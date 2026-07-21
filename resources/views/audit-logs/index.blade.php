@extends('layouts.app')
@section('title', 'Audit Logs')
@section('content')
<div style="display:flex;gap:12px;margin-bottom:16px">
    <form class="card" style="display:flex;gap:8px;align-items:flex-end;padding:14px">
        <div class="field"><label>Action</label><input name="action" value="{{ request('action') }}"></div>
        <div class="field"><label>Entity</label><input name="entity" value="{{ request('entity') }}"></div>
        <button class="btn">Filter</button>
    </form>
</div>
<div class="card" style="padding:0;overflow:auto">
    <table>
        <thead><tr><th>Timestamp</th><th>Actor</th><th>Action</th><th>Entity</th><th>Metadata</th><th>IP</th></tr></thead>
        <tbody>
        @foreach($logs as $log)
            <tr>
                <td>{{ $log->created_at }}</td>
                <td>{{ $log->actor?->name ?? 'System' }}</td>
                <td>{{ $log->action }}</td>
                <td>{{ $log->entity }} {{ $log->entity_id ? '#'.$log->entity_id : '' }}</td>
                <td><code>{{ json_encode($log->metadata ?? [], JSON_UNESCAPED_UNICODE) }}</code></td>
                <td>{{ $log->ip_address }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:14px">{{ $logs->links() }}</div>
@endsection
