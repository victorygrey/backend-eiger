@php
    $selectedRecommendationIds = collect(old('recommendation_ids', isset($tablet) ? $tablet->recommendations->pluck('id')->all() : []))->map(fn ($id) => (int) $id);
    $orderedProducts = $selectedRecommendationIds
        ->map(fn ($id) => $products->firstWhere('id', $id))
        ->filter()
        ->concat($products->reject(fn ($product) => $selectedRecommendationIds->contains($product->id)));
@endphp

<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card h-100">
            <div class="card-header bg-white py-3"><strong>Identitas & Produk</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label">Nama Tablet</label>
                        <input name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $tablet->name ?? '') }}" required>
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Slug URL</label>
                        <input name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $tablet->slug ?? '') }}" placeholder="lobby-01" required>
                        @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label">Lokasi</label>
                        <input name="location" class="form-control" value="{{ old('location', $tablet->location ?? '') }}" placeholder="Main Lobby">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Featured Product</label>
                        <select name="featured_product_id" class="form-select @error('featured_product_id') is-invalid @enderror" required>
                            <option value="">-- Pilih produk utama --</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}" @selected((int) old('featured_product_id', $tablet->featured_product_id ?? 0) === $product->id)>
                                    {{ $product->name }} — {{ $product->sku }}
                                </option>
                            @endforeach
                        </select>
                        @error('featured_product_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <label class="form-label mb-0">Recommendation</label>
                            <small class="text-muted">Ctrl/Cmd untuk memilih beberapa. Urutkan dengan tombol.</small>
                        </div>
                        <select id="recommendations" name="recommendation_ids[]" class="form-select @error('recommendation_ids') is-invalid @enderror" multiple size="10">
                            @foreach($orderedProducts as $product)
                                <option value="{{ $product->id }}" @selected($selectedRecommendationIds->contains($product->id))>
                                    {{ $product->name }} — {{ $product->sku }}
                                </option>
                            @endforeach
                        </select>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="up"><i class="bi bi-arrow-up"></i> Naik</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-move="down"><i class="bi bi-arrow-down"></i> Turun</button>
                        </div>
                        @error('recommendation_ids')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        @error('recommendation_ids.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card">
            <div class="card-header bg-white py-3"><strong>Aktivasi Perangkat</strong></div>
            <div class="card-body">
                <label class="form-label">{{ isset($tablet) ? 'Kode Aktivasi Baru (opsional)' : 'Kode Aktivasi' }}</label>
                <input name="activation_code" class="form-control @error('activation_code') is-invalid @enderror" autocomplete="new-password" placeholder="Contoh: LOBBY-01" {{ isset($tablet) ? '' : 'required' }}>
                @error('activation_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <p class="form-text">Kode hanya dipakai saat memasangkan perangkat. Mengisi kode baru akan memutus token perangkat lama.</p>

                <div class="form-check form-switch mt-4">
                    <input type="hidden" name="is_active" value="0">
                    <input class="form-check-input" type="checkbox" role="switch" id="is-active" name="is_active" value="1" @checked(old('is_active', $tablet->is_active ?? true))>
                    <label class="form-check-label" for="is-active">Tablet aktif</label>
                </div>

                @isset($tablet)
                    <hr>
                    <dl class="row small mb-0">
                        <dt class="col-5 text-muted">Config version</dt><dd class="col-7">v{{ $tablet->config_version }}</dd>
                        <dt class="col-5 text-muted">Terakhir online</dt><dd class="col-7">{{ $tablet->last_seen_at?->diffForHumans() ?? 'Belum pernah' }}</dd>
                        <dt class="col-5 text-muted">Media</dt><dd class="col-7">{{ $tablet->media_status ?? 'Belum dilaporkan' }}</dd>
                    </dl>
                @endisset
            </div>
        </div>
    </div>
</div>

<div class="d-flex justify-content-end gap-2 mt-4">
    <a href="{{ route('admin.tablets.index') }}" class="btn btn-outline-secondary">Batal</a>
    <button class="btn btn-eiger"><i class="bi bi-cloud-upload me-1"></i>{{ isset($tablet) ? 'Publikasikan Konfigurasi' : 'Buat Tablet' }}</button>
</div>

@push('scripts')
<script>
    const recommendationSelect = document.getElementById('recommendations');
    document.querySelectorAll('[data-move]').forEach((button) => {
        button.addEventListener('click', () => {
            const selected = Array.from(recommendationSelect.selectedOptions);
            if (button.dataset.move === 'up') {
                selected.forEach((option) => {
                    const previous = option.previousElementSibling;
                    if (previous && !previous.selected) recommendationSelect.insertBefore(option, previous);
                });
            } else {
                selected.reverse().forEach((option) => {
                    const next = option.nextElementSibling;
                    if (next && !next.selected) recommendationSelect.insertBefore(next, option);
                });
            }
        });
    });
</script>
@endpush
