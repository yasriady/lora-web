@extends('layouts.app')
@section('content')
<h1 class="h3">Gateway Log</h1><div class="table-responsive"><table class="table table-striped bg-white"><thead><tr><th>Waktu</th><th>Gateway</th><th>Level</th><th>Event</th><th>Pesan</th></tr></thead><tbody>@forelse($logs as $log)<tr><td>{{ $log->created_at }}</td><td>{{ $log->gateway_id ?? '—' }}</td><td>{{ $log->level }}</td><td>{{ $log->event }}</td><td>{{ $log->message }}</td></tr>@empty<tr><td colspan="5">Belum ada log.</td></tr>@endforelse</tbody></table></div>{{ $logs->links() }}
@endsection
