@php $selectedProductId = (int) old('product_id', $item->product_id ?? 0); @endphp
<div class="product-mapping-picker" data-preview-url="{{ route('admin.products.mapping-preview', ['product' => '__ID__']) }}">
    <input type="hidden" name="product_id" id="main_product_select" value="{{ $selectedProductId ?: '' }}">
    @error('product_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    <div class="row g-3">
        <div class="col-12 col-lg-5">
            <div class="input-group input-group-sm mb-2">
                <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                <input type="search" class="form-control mapping-product-search" placeholder="Cari nama atau SKU produk..." aria-label="Cari produk">
            </div>
            <div class="mapping-product-list border rounded-3 bg-light p-2 overflow-auto" style="max-height: 450px;">
                @foreach($products as $prod)
                    <button type="button" class="mapping-product-option btn w-100 text-start bg-white border rounded-3 p-2 mb-2 d-flex align-items-center gap-2 {{ $selectedProductId === $prod->id ? 'border-primary shadow-sm' : '' }}"
                            data-id="{{ $prod->id }}" data-search="{{ strtolower($prod->name . ' ' . $prod->sku) }}" aria-pressed="{{ $selectedProductId === $prod->id ? 'true' : 'false' }}">
                        <img src="{{ $prod->image ?: 'https://placehold.co/60x60?text=EIGER' }}" alt="" class="rounded object-fit-cover border" style="width: 50px; height: 50px;">
                        <span class="overflow-hidden">
                            <span class="d-block fw-semibold small text-truncate">{{ $prod->name }}</span>
                            <span class="d-block text-muted small font-monospace">{{ $prod->sku }}</span>
                            <span class="d-block text-muted small">{{ $prod->category ?: $prod->zone?->name }}</span>
                        </span>
                    </button>
                @endforeach
            </div>
        </div>
        <div class="col-12 col-lg-7">
            <div class="border rounded-3 p-3 h-100 bg-white mapping-product-preview" aria-live="polite">
                <div class="text-muted small mapping-product-empty">Pilih produk dari katalog untuk melihat data master PIM.</div>
                <div class="mapping-product-detail d-none">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <img class="mapping-preview-image rounded border object-fit-cover" alt="Foto produk" style="width: 86px; height: 86px;">
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="fw-bold mapping-preview-name"></div>
                            <div class="small font-monospace text-muted mapping-preview-sku"></div>
                            <div class="small mapping-preview-summary"></div>
                            <a class="small mapping-preview-edit" href="#">Edit data master di Products <i class="bi bi-box-arrow-up-right"></i></a>
                        </div>
                    </div>
                    <div class="small mapping-preview-sections overflow-auto" style="max-height: 550px;"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const picker = document.querySelector('.product-mapping-picker');
    if (!picker) return;
    const field = picker.querySelector('#main_product_select');
    const options = [...picker.querySelectorAll('.mapping-product-option')];
    const search = picker.querySelector('.mapping-product-search');
    const detail = picker.querySelector('.mapping-product-detail');
    const empty = picker.querySelector('.mapping-product-empty');
    const sections = picker.querySelector('.mapping-preview-sections');
    const makeText = (value) => value == null ? '' : String(value);
    let requestedId = '';

    function section(title, values) {
        if (!values || (Array.isArray(values) && !values.length)) return;
        const block = document.createElement('div');
        block.className = 'border-top pt-2 mt-2';
        const heading = document.createElement('div');
        heading.className = 'fw-semibold mb-1';
        heading.textContent = title;
        block.appendChild(heading);
        const entries = Array.isArray(values) ? values : Object.entries(values).map(([name, value]) => ({name, value}));
        entries.forEach(item => {
            const row = document.createElement('div');
            row.className = 'text-muted mb-1';
            if (typeof item === 'string') row.textContent = item;
            else if (item && typeof item === 'object') {
                const label = item.name || item.attributeCode || item.code || item.sku || item.url || item.type || '';
                const value = item.value ?? item.description ?? item.size ?? item.color ?? '';
                row.textContent = [label, typeof value === 'object' ? JSON.stringify(value) : value].filter(Boolean).join(': ');
            }
            block.appendChild(row);
        });
        sections.appendChild(block);
    }
    function photoSection(images) {
        if (!Array.isArray(images) || !images.length) return;
        const block = document.createElement('div');
        block.className = 'border-top pt-2 mt-2';
        const heading = document.createElement('div');
        heading.className = 'fw-semibold mb-2'; heading.textContent = 'Foto Produk';
        const gallery = document.createElement('div');
        gallery.className = 'd-flex flex-wrap gap-2';
        images.slice(0, 12).forEach(url => {
            if (typeof url !== 'string') return;
            const image = document.createElement('img');
            image.src = url; image.alt = 'Foto produk PIM';
            image.className = 'rounded border object-fit-cover';
            image.style.width = '68px'; image.style.height = '68px';
            gallery.appendChild(image);
        });
        block.append(heading, gallery); sections.appendChild(block);
    }
    async function showProduct(id) {
        if (!id) { detail.classList.add('d-none'); empty.classList.remove('d-none'); return; }
        requestedId = String(id);
        empty.textContent = 'Memuat detail produk PIM...';
        empty.classList.remove('d-none');
        detail.classList.add('d-none');
        try {
            const response = await fetch(picker.dataset.previewUrl.replace('__ID__', encodeURIComponent(id)), {credentials: 'same-origin'});
            if (!response.ok) throw new Error('Produk tidak dapat dimuat');
            const product = await response.json();
            if (requestedId !== String(id)) return;
            picker.querySelector('.mapping-preview-image').src = product.image || product.images?.[0] || 'https://placehold.co/86x86?text=EIGER';
            picker.querySelector('.mapping-preview-name').textContent = product.name;
            picker.querySelector('.mapping-preview-sku').textContent = product.sku;
            picker.querySelector('.mapping-preview-summary').textContent = [product.category, product.zone, 'Rp ' + Number(product.price).toLocaleString('id-ID'), 'Stok ' + product.stock].filter(Boolean).join(' · ');
            picker.querySelector('.mapping-preview-edit').href = product.edit_url;
            sections.replaceChildren();
            section('Deskripsi', product.description ? [product.description] : []);
            section('Material & Berat', [product.material, product.weight ? product.weight + ' gram' : ''].filter(Boolean));
            photoSection(product.images);
            section('Media PIM', product.media);
            section('Teknologi / Fitur', product.technologies);
            section('Spesifikasi Teknis', product.specifications);
            section('Custom Attributes', product.custom_attributes);
            section('Aktivitas', product.activities);
            section('Varian', product.variants);
            empty.classList.add('d-none'); detail.classList.remove('d-none');
        } catch (error) {
            if (requestedId === String(id)) empty.textContent = error.message;
        }
    }
    options.forEach(option => option.addEventListener('click', () => {
        field.value = option.dataset.id;
        field.dispatchEvent(new Event('change', {bubbles: true}));
    }));
    field.addEventListener('change', () => {
        options.forEach(option => {
            const chosen = option.dataset.id === field.value;
            option.classList.toggle('border-primary', chosen);
            option.classList.toggle('shadow-sm', chosen);
            option.setAttribute('aria-pressed', chosen ? 'true' : 'false');
        });
        showProduct(field.value);
    });
    search.addEventListener('input', () => {
        const query = search.value.trim().toLocaleLowerCase('id-ID');
        options.forEach(option => option.classList.toggle('d-none', !option.dataset.search.includes(query)));
    });
    if (field.value) showProduct(field.value);
});
</script>
@endpush
