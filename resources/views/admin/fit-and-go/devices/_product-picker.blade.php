@php $selectedIds = $selectedProducts->pluck('id')->map(fn ($id) => (int) $id)->all(); @endphp
<form action="{{ $formAction }}" method="POST" class="card border-0 shadow-sm" id="{{ $pickerId }}">
    @csrf @method('PUT')
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
        <div><h6 class="fw-bold mb-1"><i class="bi bi-grid-fill text-primary me-2"></i>{{ $pickerTitle }}</h6><small class="text-muted">{{ $pickerDescription }}</small></div>
        <span class="badge bg-secondary picker-count">{{ count($selectedIds) }} / 30 Terpilih</span>
    </div>
    <div class="card-body"><div class="row g-3">
        <div class="col-12 col-lg-6 border-end-lg">
            <div class="d-flex justify-content-between mb-2"><span class="small fw-semibold text-uppercase text-muted">Katalog Produk Tersedia</span><span class="small text-muted picker-available-count">{{ $pickerProducts->count() }} produk</span></div>
            <div class="input-group input-group-sm mb-2"><span class="input-group-text bg-white"><i class="bi bi-search"></i></span><input type="search" class="form-control picker-search" placeholder="Cari nama / SKU produk..."></div>
            <div class="border rounded-3 p-2 overflow-auto picker-available" style="height:430px">
                @forelse($pickerProducts as $product)
                    <div class="picker-product p-2 mb-2 border rounded bg-white d-flex align-items-center gap-2 {{ in_array($product->id, $selectedIds, true) ? 'd-none' : '' }}" data-id="{{ $product->id }}" data-search="{{ Str::lower($product->name . ' ' . $product->sku . ' ' . ($product->category ?? '')) }}">
                        <img src="{{ $product->image_url ?: 'https://placehold.co/60x60?text=EIGER' }}" class="rounded border object-fit-cover" style="width:46px;height:46px" alt="">
                        <div class="overflow-hidden flex-grow-1"><div class="fw-semibold small text-truncate">{{ $product->name }}</div><div class="small text-muted font-monospace">{{ $product->sku }}</div><div class="small text-muted">Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}</div></div>
                        <button type="button" class="btn btn-sm btn-outline-primary picker-add"><i class="bi bi-plus-lg"></i> Tambah</button>
                    </div>
                @empty
                    <div class="text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block"></i>Tidak ada produk yang sesuai.</div>
                @endforelse
            </div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="small fw-semibold text-uppercase text-muted mb-2">Urutan Produk Terpilih</div>
            <div class="border rounded-3 p-2 bg-light overflow-auto picker-selected" style="height:468px">
                <div class="picker-empty text-center text-muted py-5 {{ $selectedProducts->isNotEmpty() ? 'd-none' : '' }}"><i class="bi bi-hand-index-thumb fs-2 d-block mb-2"></i>Belum ada produk dipilih.</div>
                @foreach($selectedProducts as $index => $product)
                    <div class="picker-selection p-2 mb-2 border rounded bg-white d-flex align-items-center gap-2" data-id="{{ $product->id }}">
                        <span class="badge bg-secondary picker-rank">#{{ $index + 1 }}</span><img src="{{ $product->image_url ?: 'https://placehold.co/60x60?text=EIGER' }}" class="rounded border object-fit-cover" style="width:44px;height:44px" alt="">
                        <div class="overflow-hidden flex-grow-1"><div class="fw-semibold small text-truncate">{{ $product->name }}</div><div class="small text-muted font-monospace">{{ $product->sku }}</div></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary picker-up"><i class="bi bi-arrow-up"></i></button><button type="button" class="btn btn-sm btn-outline-secondary picker-down"><i class="bi bi-arrow-down"></i></button><button type="button" class="btn btn-sm btn-outline-danger picker-remove"><i class="bi bi-x-lg"></i></button>
                    </div>
                @endforeach
            </div>
            <div class="picker-inputs">@foreach($selectedProducts as $product)<input type="hidden" name="product_ids[]" value="{{ $product->id }}">@endforeach</div>
        </div>
    </div></div>
    <div class="card-footer bg-white d-flex justify-content-end"><button class="btn btn-eiger fw-bold px-4"><i class="bi bi-check-lg me-1"></i>{{ $saveLabel }}</button></div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById(@json($pickerId)); if (!root) return;
    const available = root.querySelector('.picker-available'), selected = root.querySelector('.picker-selected'), inputs = root.querySelector('.picker-inputs'), empty = root.querySelector('.picker-empty');
    function refresh() {
        const cards = [...selected.querySelectorAll('.picker-selection')];
        cards.forEach((card, index) => card.querySelector('.picker-rank').textContent = `#${index + 1}`); inputs.innerHTML = '';
        cards.forEach(card => { const input = document.createElement('input'); input.type = 'hidden'; input.name = 'product_ids[]'; input.value = card.dataset.id; inputs.appendChild(input); });
        empty.classList.toggle('d-none', cards.length > 0); root.querySelector('.picker-count').textContent = `${cards.length} / 30 Terpilih`;
    }
    function selectedCard(source) {
        const card = document.createElement('div'); card.className = 'picker-selection p-2 mb-2 border rounded bg-white d-flex align-items-center gap-2'; card.dataset.id = source.dataset.id;
        card.innerHTML = `<span class="badge bg-secondary picker-rank">#</span>${source.querySelector('img').outerHTML}<div class="overflow-hidden flex-grow-1">${source.querySelector('.flex-grow-1').innerHTML}</div><button type="button" class="btn btn-sm btn-outline-secondary picker-up"><i class="bi bi-arrow-up"></i></button><button type="button" class="btn btn-sm btn-outline-secondary picker-down"><i class="bi bi-arrow-down"></i></button><button type="button" class="btn btn-sm btn-outline-danger picker-remove"><i class="bi bi-x-lg"></i></button>`; return card;
    }
    available.addEventListener('click', event => { const button = event.target.closest('.picker-add'); if (!button) return; if (selected.querySelectorAll('.picker-selection').length >= 30) return alert('Maksimal 30 produk dapat dipilih.'); const source = button.closest('.picker-product'); selected.appendChild(selectedCard(source)); source.classList.add('d-none'); refresh(); });
    selected.addEventListener('click', event => { const card = event.target.closest('.picker-selection'); if (!card) return; if (event.target.closest('.picker-remove')) { available.querySelector(`[data-id="${card.dataset.id}"]`)?.classList.remove('d-none'); card.remove(); } else if (event.target.closest('.picker-up') && card.previousElementSibling && !card.previousElementSibling.classList.contains('picker-empty')) selected.insertBefore(card, card.previousElementSibling); else if (event.target.closest('.picker-down') && card.nextElementSibling) selected.insertBefore(card.nextElementSibling, card); refresh(); });
    root.querySelector('.picker-search').addEventListener('input', event => { const q = event.target.value.toLowerCase().trim(); let count = 0; available.querySelectorAll('.picker-product').forEach(card => { const show = !card.classList.contains('d-none') && card.dataset.search.includes(q); card.style.display = show ? '' : 'none'; if (show) count++; }); root.querySelector('.picker-available-count').textContent = `${count} produk`; }); refresh();
});
</script>
@endpush
