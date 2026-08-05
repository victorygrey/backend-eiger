{{--
    Partial: tabel footer (info + pagination) yang konsisten untuk semua halaman admin.
    Variabel yang diharapkan:
      - $paginator   : instance Illuminate\Pagination\LengthAwarePaginator
      - $resource    : label resource (mis. "produk", "zone", "tag", "log")
--}}
@php
    $paginator = $paginator ?? null;
    $resource  = $resource ?? 'item';
@endphp

@if($paginator)
    <div class="p-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 table-footer">
        <small class="text-muted">
            <i class="bi bi-info-circle me-1"></i>
            @if($paginator->total() > 0)
                Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}
                dari {{ $paginator->total() }} {{ $resource }}
            @else
                Menampilkan 0 dari 0 {{ $resource }}
            @endif
        </small>

        @if($paginator->hasPages())
            {{ $paginator->links('vendor.pagination.bootstrap-5') }}
        @endif
    </div>
@endif
