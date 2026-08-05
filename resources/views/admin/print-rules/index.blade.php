@extends('layouts.admin')

@section('title', 'Print Rules')
@section('page-title', 'Print Rules')
@section('breadcrumb-items')
    <li class="breadcrumb-item active">Konfigurasi</li>
    <li class="breadcrumb-item active">Print Rules</li>
@endsection

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold"><i class="bi bi-printer-fill text-info me-2"></i>Print Photo Rules</h4>
        <p class="text-muted small mb-0">Aturan minimum transaksi dan membership untuk fitur cetak foto.</p>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Minimum Transaksi</th>
                        <th>Require Membership</th>
                        <th>Status</th>
                        <th class="pe-3 text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rules as $i => $rule)
                        <tr>
                            <td class="ps-3 text-muted">{{ $rules->firstItem() + $i }}</td>
                            <td class="fw-semibold">
                                <i class="bi bi-cash-coin text-success me-1"></i>
                                Rp {{ number_format($rule->minimum_transaction, 0, ',', '.') }}
                            </td>
                            <td>
                                @if($rule->require_membership)
                                    <span class="badge-soft badge-warning-soft">
                                        <i class="bi bi-person-badge-fill"></i>Ya
                                    </span>
                                @else
                                    <span class="badge-soft badge-gray-soft">
                                        <i class="bi bi-x-circle"></i>Tidak
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($rule->enabled)
                                    <span class="badge-soft badge-success-soft">
                                        <i class="bi bi-check-circle-fill"></i>Aktif
                                    </span>
                                @else
                                    <span class="badge-soft badge-gray-soft">
                                        <i class="bi bi-pause-circle-fill"></i>Non-aktif
                                    </span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <a href="{{ route('admin.print-rules.edit', $rule) }}" class="btn btn-sm btn-warning">
                                    <i class="bi bi-pencil-fill me-1"></i>Edit Rule
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            @include('admin.partials.empty-state', [
                                'icon'  => 'bi-printer',
                                'title' => 'Belum ada print rule',
                                'sub'   => 'Jalankan "php artisan db:seed --class=PrintRuleSeeder".',
                            ])
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @include('admin.partials.table-footer', [
            'paginator' => $rules,
            'resource'  => 'rule',
        ])
    </div>
</div>
@endsection
