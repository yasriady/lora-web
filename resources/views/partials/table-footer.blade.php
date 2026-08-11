@php
    /** @var \Illuminate\Pagination\LengthAwarePaginator $paginator */
@endphp
@if ($paginator->total() > 0)
    <div class="table-footer">
        <div class="table-footer-meta">
            {{ __('ui.pagination.showing', [
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'total' => number_format($paginator->total()),
            ]) }}
        </div>
        @if ($paginator->hasPages())
            <div class="table-footer-links">
                {{ $paginator->onEachSide(1)->links() }}
            </div>
        @endif
    </div>
@endif
