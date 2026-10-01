<div class="row g-4">
    <div class="col-lg-6">
        <label class="form-label fw-semibold">UID / EPC RFID <span class="text-danger">*</span></label>
        <input type="text" name="uid" value="{{ old('uid', $rfidTag->uid ?? '') }}" class="form-control font-monospace @error('uid') is-invalid @enderror" placeholder="Contoh: E280116060000204..." required autofocus>
        <div class="form-text">Kode fisik tag. Strip, titik dua, dan spasi akan dibersihkan otomatis.</div>
        @error('uid') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-lg-6">
        <label class="form-label fw-semibold">Nama / Label RFID <span class="text-muted">(Opsional)</span></label>
        <input type="text" name="name" value="{{ old('name', $rfidTag->name ?? '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="Contoh: Tag Jaket Display Lantai 1">
        <div class="form-text">Penanda lokasi atau nama fisik agar tag mudah dikenali.</div>
        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    @php
        $selectedProductId = (int) old('product_id', $rfidTag->product_id ?? 0);
        $selectedProduct = $products->firstWhere('id', $selectedProductId);
    @endphp
    <div class="col-12" data-rfid-product-picker>
        <div class="d-flex align-items-start justify-content-between gap-3 mb-2">
            <div>
                <label class="form-label fw-semibold mb-1">Produk EIGER / SKU Utama <span class="text-muted">(Opsional)</span></label>
                <div class="form-text mt-0">Cari produk berdasarkan nama, SKU, zone, atau kategori.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary flex-shrink-0" data-bs-toggle="modal" data-bs-target="#rfidProductModal">
                <i class="bi bi-search me-1"></i>Pilih Produk
            </button>
        </div>

        <input type="hidden" name="product_id" id="rfid_product_id" value="{{ $selectedProductId ?: '' }}">

        <div class="rfid-selected-product {{ $selectedProduct ? '' : 'd-none' }}">
            <div class="p-3 border rounded-3 bg-light d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div class="d-flex align-items-center gap-3 overflow-hidden">
                    <img class="rfid-selected-image rounded border object-fit-cover shadow-sm bg-white {{ $selectedProduct?->image ? '' : 'd-none' }}"
                         @if($selectedProduct?->image) src="{{ $selectedProduct->image }}" @endif alt="Foto produk terpilih" style="width: 72px; height: 72px;">
                    <div class="rfid-selected-image-empty rounded border bg-white d-flex align-items-center justify-content-center text-muted {{ $selectedProduct?->image ? 'd-none' : '' }}" style="width: 72px; height: 72px;">
                        <i class="bi bi-image fs-3"></i>
                    </div>
                    <div class="overflow-hidden">
                        <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                            <h6 class="rfid-selected-name mb-0 fw-bold text-dark text-truncate">{{ $selectedProduct?->name }}</h6>
                            <span class="rfid-selected-zone badge bg-primary-subtle text-primary border border-primary-subtle">
                                {{ $selectedProduct?->zone?->name ?? $selectedProduct?->category ?? 'Kategori belum tersedia' }}
                            </span>
                        </div>
                        <div class="small text-muted font-monospace mb-1">SKU: <strong class="rfid-selected-sku">{{ $selectedProduct?->sku }}</strong></div>
                        <div class="small fw-semibold text-dark">
                            <span class="rfid-selected-price">Rp {{ number_format($selectedProduct?->price ?? 0, 0, ',', '.') }}</span>
                            <span class="text-muted fw-normal ms-2">| Stok: <span class="rfid-selected-stock">{{ $selectedProduct?->stock ?? 0 }}</span></span>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#rfidProductModal">
                        <i class="bi bi-arrow-repeat me-1"></i>Ganti
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-danger rfid-clear-product">
                        <i class="bi bi-x-lg me-1"></i>Lepaskan
                    </button>
                </div>
            </div>
        </div>

        <div class="rfid-product-empty text-center py-4 border border-dashed rounded-3 bg-light {{ $selectedProduct ? 'd-none' : '' }}">
            <i class="bi bi-box-seam fs-2 text-secondary d-block mb-2"></i>
            <p class="text-muted small mb-2">RFID belum dihubungkan ke produk.</p>
            <button type="button" class="btn btn-sm btn-eiger" data-bs-toggle="modal" data-bs-target="#rfidProductModal">
                <i class="bi bi-plus-lg me-1"></i>Pilih Produk dari Katalog
            </button>
        </div>
        <div class="form-text">Satu produk boleh mempunyai lebih dari satu tag fisik. Tag yang belum diketahui produknya dapat disimpan tanpa pemetaan.</div>
        @error('product_id') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

        <div class="modal fade" id="rfidProductModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold"><i class="bi bi-box-seam text-primary me-2"></i>Pilih Produk RFID</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="search" class="form-control rfid-product-search" placeholder="Ketik nama, SKU, zone, atau kategori produk...">
                        </div>
                        <div class="table-responsive" style="max-height: 420px;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 60px;">Foto</th>
                                        <th>Nama & SKU</th>
                                        <th>Zone / Kategori</th>
                                        <th>Harga & Stok</th>
                                        <th class="text-end" style="width: 110px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($products as $product)
                                        @php($productZone = $product->zone?->name ?? $product->category ?? 'Kategori belum tersedia')
                                        <tr class="rfid-product-row"
                                            data-id="{{ $product->id }}"
                                            data-search="{{ strtolower($product->name.' '.$product->sku.' '.$productZone) }}"
                                            data-name="{{ $product->name }}"
                                            data-sku="{{ $product->sku }}"
                                            data-zone="{{ $productZone }}"
                                            data-image="{{ $product->image }}"
                                            data-price="Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}"
                                            data-stock="{{ $product->stock ?? 0 }}">
                                            <td>
                                                @if($product->image)
                                                    <img src="{{ $product->image }}" alt="{{ $product->name }}" class="rounded border object-fit-cover shadow-sm" style="width: 44px; height: 44px;">
                                                @else
                                                    <div class="rounded border bg-light d-flex align-items-center justify-content-center text-muted" style="width: 44px; height: 44px;"><i class="bi bi-image"></i></div>
                                                @endif
                                            </td>
                                            <td><div class="fw-bold text-dark">{{ $product->name }}</div><div class="small text-muted font-monospace">{{ $product->sku }}</div></td>
                                            <td><span class="badge bg-light text-dark border">{{ $productZone }}</span></td>
                                            <td><div class="fw-bold text-dark">Rp {{ number_format($product->price ?? 0, 0, ',', '.') }}</div><div class="small text-muted">Stok: {{ $product->stock ?? 0 }}</div></td>
                                            <td class="text-end"><button type="button" class="btn btn-sm btn-primary rfid-choose-product"><i class="bi bi-check2"></i> Pilih</button></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center text-muted py-4">Belum ada produk yang dapat dipilih.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <div class="rfid-product-no-results text-center text-muted py-4 d-none">Produk tidak ditemukan.</div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Tutup</button></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="alert alert-light border d-flex gap-3 mb-0">
            <i class="bi bi-info-circle text-primary fs-5"></i>
            <div class="small"><div class="fw-semibold text-dark mb-1">Satu master RFID untuk dua wahana</div><div class="text-muted">Table Expedition dan LED Ambience membaca UID serta produk dari master ini. Konten khusus seperti video, audio, scene, dan konfigurasi wahana tetap diatur pada modul masing-masing.</div></div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('[data-rfid-product-picker]');
    if (!picker) return;

    const field = picker.querySelector('#rfid_product_id');
    const selected = picker.querySelector('.rfid-selected-product');
    const empty = picker.querySelector('.rfid-product-empty');
    const image = picker.querySelector('.rfid-selected-image');
    const imageEmpty = picker.querySelector('.rfid-selected-image-empty');
    const rows = [...picker.querySelectorAll('.rfid-product-row')];
    const noResults = picker.querySelector('.rfid-product-no-results');

    function choose(row) {
        field.value = row.dataset.id;
        picker.querySelector('.rfid-selected-name').textContent = row.dataset.name;
        picker.querySelector('.rfid-selected-sku').textContent = row.dataset.sku;
        picker.querySelector('.rfid-selected-zone').textContent = row.dataset.zone;
        picker.querySelector('.rfid-selected-price').textContent = row.dataset.price;
        picker.querySelector('.rfid-selected-stock').textContent = row.dataset.stock;

        const hasImage = Boolean(row.dataset.image);
        if (hasImage) image.src = row.dataset.image;
        else image.removeAttribute('src');
        image.classList.toggle('d-none', !hasImage);
        imageEmpty.classList.toggle('d-none', hasImage);
        selected.classList.remove('d-none');
        empty.classList.add('d-none');

        bootstrap.Modal.getOrCreateInstance(picker.querySelector('#rfidProductModal')).hide();
    }

    picker.querySelectorAll('.rfid-choose-product').forEach(button => {
        button.addEventListener('click', () => choose(button.closest('.rfid-product-row')));
    });

    picker.querySelector('.rfid-clear-product').addEventListener('click', () => {
        field.value = '';
        selected.classList.add('d-none');
        empty.classList.remove('d-none');
    });

    picker.querySelector('.rfid-product-search').addEventListener('input', event => {
        const query = event.target.value.trim().toLocaleLowerCase('id-ID');
        let visible = 0;
        rows.forEach(row => {
            const matches = row.dataset.search.toLocaleLowerCase('id-ID').includes(query);
            row.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        noResults.classList.toggle('d-none', visible > 0);
    });
});
</script>
@endpush
