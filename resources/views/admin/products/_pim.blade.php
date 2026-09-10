<div class="border rounded p-3 mb-4" id="pim-product-form" data-lookup="{{ route('admin.products.pim-lookup') }}">
    <h6>Data produk PIM</h6>
    <p class="text-muted small">Ambil produk berdasarkan kode artikel. Produk draft atau belum siap publish tetap dapat dibaca. Satu form menyimpan satu SKU; harga, stok, dan zone tetap dikelola di CMS.</p>
    <div class="d-flex gap-2 mb-2">
        <input id="pim-code" class="form-control" placeholder="Kode artikel PIM, contoh 910009029" aria-label="Kode artikel PIM">
        <button type="button" id="pim-fetch" class="btn btn-outline-primary text-nowrap">Ambil dari PIM</button>
    </div>
    <div id="pim-feedback" role="status" class="small mb-2"></div>
    <label for="pim-variant" class="form-label">SKU dari PIM</label>
    <select id="pim-variant" class="form-select mb-3" disabled><option>Ambil produk terlebih dahulu</option></select>
    <div class="row g-3" id="pim-fields"></div>
    <div id="pim-gallery" class="d-flex gap-2 overflow-auto my-3"></div>
    <details>
        <summary class="mb-2">Payload lengkap sesuai dokumen PIM</summary>
        <label class="form-label" for="pim-payload">Detail produk (JSON)</label>
        <textarea id="pim-payload" name="pim_payload_json" class="form-control font-monospace mb-3" rows="10">{{ old('pim_payload_json', isset($product) && $product->pim_payload ? json_encode($product->pim_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
        <label class="form-label" for="pim-images">Image to Channel (JSON)</label>
        <textarea id="pim-images" name="pim_image_payload_json" class="form-control font-monospace" rows="8">{{ old('pim_image_payload_json', isset($product) && $product->pim_image_payload ? json_encode($product->pim_image_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    </details>
    @error('pim_payload_json') <div class="text-danger">{{ $message }}</div> @enderror
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const root=document.getElementById('pim-product-form'), form=root.closest('form');
    const el=id=>document.getElementById(id), field=name=>form.elements.namedItem(name);
    let product=null, images=null;
    const attrs={short_description:'Deskripsi singkat',long_description:'Deskripsi lengkap',tag_product:'Tag produk',gender:'Gender',dimension:'Dimensi',category:'Kategori',product_group:'Grup produk',activity:'Kode aktivitas',waterproof:'Waterproof',breathability:'Breathability'};
    const write=()=>{el('pim-payload').value=JSON.stringify(product,null,2);el('pim-images').value=JSON.stringify(images,null,2)};
    function preview(){
        el('pim-gallery').replaceChildren();
        const rows=images.variant?.find(v=>v.sku===el('pim-variant').value)?.image || [];
        rows.forEach(asset=>{try {const url=new URL(asset.url);if(!['http:','https:'].includes(url.protocol))return;const img=document.createElement('img');img.src=url.href;img.alt=product.name;img.loading='lazy';img.style.cssText='width:100px;height:110px;object-fit:contain';el('pim-gallery').append(img)}catch{}});
    }
    function select(){
        const v=product.variant.find(v=>v.sku===el('pim-variant').value);if(!v)return;
        field('sku').value=v.sku;field('name').value=v.name;
        const a=Object.fromEntries((product.customAtributes||[]).map(a=>[a.attributeCode,a.value]));
        field('description').value=a.long_description || a.short_description || '';
        field('image').value=images.variant?.find(x=>x.sku===v.sku)?.image?.find(x=>x.type==='main_image')?.url || product.mainImage || '';
        preview();
    }
    function render(apply){
        el('pim-variant').replaceChildren();
        product.variant.forEach(v=>el('pim-variant').add(new Option(`${v.sku} · ${v.color} · ${v.size}`,v.sku)));
        el('pim-variant').disabled=false;
        if(product.variant.some(v=>v.sku===field('sku').value))el('pim-variant').value=field('sku').value;
        el('pim-code').value=product.generic;
        el('pim-fields').replaceChildren();
        function input(label,value,change,type='text'){
            const col=document.createElement('div');col.className='col-md-6';const l=document.createElement('label');l.className='form-label';l.textContent=label;
            const i=document.createElement('input');i.className='form-control';i.type=type;i.value=value??'';i.setAttribute('aria-label',label);if(type==='number'){i.min='0';i.step='any'}
            i.addEventListener('input',()=>{change(i.value);write()});col.append(l,i);el('pim-fields').append(col);
        }
        input('Berat (gram)',product.weight,v=>product.weight=Number(v),'number');
        for(const [code,label] of Object.entries(attrs)){
            input(label,product.customAtributes?.find(a=>a.attributeCode===code)?.value,v=>{product.customAtributes ||= [];let a=product.customAtributes.find(a=>a.attributeCode===code);if(!a){a={attributeCode:code,value:''};product.customAtributes.push(a)}a.value=v});
        }
        if(apply)select();else preview();
    }
    el('pim-variant').onchange=select;
    el('pim-fetch').onclick=async()=>{
        const code=el('pim-code').value.trim();if(!code)return;
        el('pim-fetch').disabled=true;el('pim-feedback').textContent='Mengambil detail dan gambar…';
        try{const res=await fetch(root.dataset.lookup+'?code='+encodeURIComponent(code),{headers:{Accept:'application/json'}});const data=await res.json();if(!res.ok)throw Error(data.message||'PIM tidak dapat dihubungi');product=data.product;images=data.image;write();render(true);el('pim-feedback').textContent='Data PIM dimuat. Tinjau dan simpan produk untuk menyalin gambar ke CMS.'}
        catch(e){el('pim-feedback').textContent=e.message}finally{el('pim-fetch').disabled=false}
    };
    for(const id of ['pim-payload','pim-images'])el(id).addEventListener('change',()=>{try{product=JSON.parse(el('pim-payload').value);images=JSON.parse(el('pim-images').value);render(false)}catch{el('pim-feedback').textContent='JSON belum valid.'}});
    if(el('pim-payload').value){try{product=JSON.parse(el('pim-payload').value);images=JSON.parse(el('pim-images').value);render(false)}catch{}}
});
</script>
