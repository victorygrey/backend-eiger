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
    <div class="col-12">
        <label class="form-label fw-semibold">Produk EIGER / SKU Utama <span class="text-muted">(Opsional)</span></label>
        <select name="product_id" class="form-select @error('product_id') is-invalid @enderror">
            <option value="">— Belum dipetakan ke produk —</option>
            @foreach($products as $product)
                <option value="{{ $product->id }}" @selected((string) old('product_id', $rfidTag->product_id ?? '') === (string) $product->id)>{{ $product->name }} — {{ $product->sku }}</option>
            @endforeach
        </select>
        <div class="form-text">Pilih produk berdasarkan SKU utama 9 digit. Satu produk boleh mempunyai lebih dari satu tag fisik. Tag yang belum diketahui produknya dapat disimpan tanpa pemetaan.</div>
        @error('product_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <div class="col-12">
        <div class="alert alert-light border d-flex gap-3 mb-0">
            <i class="bi bi-info-circle text-primary fs-5"></i>
            <div class="small"><div class="fw-semibold text-dark mb-1">Satu master RFID untuk dua wahana</div><div class="text-muted">Table Expedition dan LED Ambience membaca UID serta produk dari master ini. Konten khusus seperti video, audio, scene, dan konfigurasi wahana tetap diatur pada modul masing-masing.</div></div>
        </div>
    </div>
</div>
