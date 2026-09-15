@php
    $selectedIds = collect(old('recommended_product_ids', $selectedProductIds ?? []))->map(fn ($id) => (int) $id);
    $selectedProducts = $selectedIds->map(fn ($id) => $products->firstWhere('id', $id))->filter();
@endphp
<div class="card border-0 shadow-sm rounded-3 mt-4">
    <div class="card-header bg-white py-3">
        <h6 class="mb-0 fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Rekomendasi Produk Aktivitas</h6>
        <small class="text-muted">Pilih produk secara manual dari katalog PIM. Aktivitas baru dimulai tanpa rekomendasi.</small>
    </div>
    <div class="card-body">
        @error('recommended_product_ids')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
        <div class="row g-3" id="activity-product-picker">
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Katalog produk tersedia</label>
                <input type="search" class="form-control form-control-sm mb-2" id="activity-product-search" placeholder="Cari nama / SKU...">
                <div class="border rounded-3 bg-light p-2 overflow-auto" id="activity-product-catalog" style="max-height: 400px;">
                    @foreach($products as $product)
                        <div class="activity-product-option bg-white rounded border p-2 mb-2 d-flex align-items-center gap-2" data-id="{{ $product->id }}" data-search="{{ strtolower($product->name . ' ' . $product->sku) }}">
                            <img src="{{ $product->image ?: 'https://placehold.co/50x50?text=EIGER' }}" alt="" class="rounded object-fit-cover border" style="width: 44px; height: 44px;">
                            <span class="overflow-hidden flex-grow-1"><strong class="small d-block text-truncate">{{ $product->name }}</strong><span class="small text-muted">{{ $product->sku }}</span></span>
                            <button type="button" class="btn btn-sm btn-outline-primary activity-add-product" @disabled($selectedIds->contains($product->id)) aria-label="Tambahkan {{ $product->name }}"><i class="bi bi-plus-lg"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label small fw-semibold">Produk rekomendasi terpilih <span class="badge bg-secondary" id="activity-product-count">{{ $selectedProducts->count() }}</span></label>
                <div class="border rounded-3 p-2 overflow-auto" id="activity-product-selected" style="max-height: 450px; min-height: 90px;">
                    @foreach($selectedProducts as $product)
                        <div class="activity-selected-product bg-light rounded border p-2 mb-2 d-flex align-items-center gap-2" data-id="{{ $product->id }}">
                            <input type="hidden" name="recommended_product_ids[]" value="{{ $product->id }}">
                            <img src="{{ $product->image ?: 'https://placehold.co/50x50?text=EIGER' }}" alt="" class="rounded object-fit-cover border" style="width: 44px; height: 44px;">
                            <span class="overflow-hidden flex-grow-1"><strong class="small d-block text-truncate">{{ $product->name }}</strong><span class="small text-muted">{{ $product->sku }}</span></span>
                            <button type="button" class="btn btn-sm btn-outline-danger activity-remove-product" aria-label="Hapus {{ $product->name }}"><i class="bi bi-x-lg"></i></button>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.getElementById('activity-product-picker');
    if (!picker) return;
    const catalog = picker.querySelector('#activity-product-catalog');
    const selected = picker.querySelector('#activity-product-selected');
    const count = picker.querySelector('#activity-product-count');
    picker.querySelector('#activity-product-search').addEventListener('input', event => {
        const query = event.target.value.trim().toLocaleLowerCase('id-ID');
        catalog.querySelectorAll('.activity-product-option').forEach(row => row.classList.toggle('d-none', !row.dataset.search.includes(query)));
    });
    catalog.addEventListener('click', event => {
        const button = event.target.closest('.activity-add-product');
        if (!button || button.disabled) return;
        const row = button.closest('.activity-product-option');
        const card = row.cloneNode(true);
        card.className = 'activity-selected-product bg-light rounded border p-2 mb-2 d-flex align-items-center gap-2';
        const action = card.querySelector('button');
        action.className = 'btn btn-sm btn-outline-danger activity-remove-product';
        action.innerHTML = '<i class="bi bi-x-lg"></i>';
        const input = document.createElement('input');
        input.type = 'hidden'; input.name = 'recommended_product_ids[]'; input.value = row.dataset.id;
        card.prepend(input);
        selected.appendChild(card);
        button.disabled = true;
        count.textContent = selected.children.length;
    });
    selected.addEventListener('click', event => {
        const button = event.target.closest('.activity-remove-product');
        if (!button) return;
        const card = button.closest('.activity-selected-product');
        const original = catalog.querySelector(`.activity-product-option[data-id="${card.dataset.id}"]`);
        if (original) original.querySelector('button').disabled = false;
        card.remove(); count.textContent = selected.children.length;
    });
});
</script>
@endpush
