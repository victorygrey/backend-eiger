    @php
        $plainPimText = static function ($value): string {
            $html = preg_replace('~<br\s*/?>|</(?:p|div|li|h[1-6])>~i', ' ', (string) $value);
            return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        };
        $pimMediaKind = static function ($url): string {
            $path = strtolower((string) parse_url((string) $url, PHP_URL_PATH));
            if (preg_match('/\.(?:jpe?g|png|webp|gif|svg)$/', $path)) return 'image';
            if (preg_match('/\.(?:mp4|webm|ogg)$/', $path)) return 'video';
            return 'link';
        };
    @endphp
    <div id="pim-enrichment-card" class="col-12 {{ (isset($product) && !empty($product->pim_payload)) ? '' : 'd-none' }}">
        <div class="d-flex align-items-center justify-content-between border-bottom pb-2 mb-3">
            <span class="fw-bold small text-dark"><i class="bi bi-stars text-warning me-2"></i>PIM Enrichment Master Data</span>
            <span class="badge bg-primary bg-opacity-10 text-primary small" id="pim-enrichment-status">Terverifikasi 1:1 PIM</span>
        </div>
        <div class="px-1">
            <div class="row g-3">
                <!-- Column 1: Technology & Activities -->
                <div class="col-md-6 border-end">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-cpu me-1 text-primary"></i>Teknologi Produk (Technologies)</label>
                        <div id="pim-tech-container" class="d-flex flex-column gap-2">
                            @if(isset($product) && !empty($product->technologies))
                                @foreach($product->technologies as $tech)
                                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                                        <div class="d-flex gap-2 align-items-start">
                                            @if(!empty($tech['image']))
                                                <a href="{{ $tech['image'] }}" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                                                    <img src="{{ $tech['image'] }}" alt="{{ $tech['name'] ?? 'Teknologi produk' }}" class="rounded border bg-white object-fit-contain" style="width: 72px; height: 72px;">
                                                </a>
                                            @endif
                                            <div>
                                                <span class="badge bg-primary text-white fw-bold">{{ $tech['name'] ?? 'TEKNOLOGI' }}</span>
                                                <div class="small text-muted mt-1">{{ $plainPimText($tech['description'] ?? '') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted small fst-italic">Belum ada data teknologi.</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-activity me-1 text-danger"></i>Aktivitas & Ketahanan (Activities & Ratings)</label>
                        <div id="pim-activity-container" class="d-flex flex-column gap-2">
                            @if(isset($product) && !empty($product->activities))
                                @foreach($product->activities as $act)
                                    <div class="p-2 border rounded-2 bg-light bg-opacity-50">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <span class="fw-semibold small text-dark">{{ $act['name'] ?? 'Aktivitas' }}</span>
                                            @if(isset($act['rating']) && $act['rating'] > 0)
                                                <span class="badge bg-warning text-dark"><i class="bi bi-star-fill text-warning me-1"></i>{{ $plainPimText($act['desc_rating'] ?? ($act['rating'] . '/5')) }}</span>
                                            @endif
                                        </div>
                                        <div class="small text-muted">{{ $plainPimText($act['description'] ?? '') }}</div>
                                    </div>
                                @endforeach
                            @else
                                <span class="text-muted small fst-italic">Belum ada data aktivitas.</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Column 2: Specifications & Custom Attributes -->
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-rulers me-1 text-success"></i>Spesifikasi Fisik (Specifications)</label>
                        <div id="pim-spec-container" class="row g-2">
                            @if(isset($product) && !empty($product->specifications))
                                @foreach($product->specifications as $spec)
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light bg-opacity-50 text-center">
                                            <div class="text-muted text-uppercase" style="font-size: 0.72rem;">{{ $spec['name'] ?? $spec['code'] }}</div>
                                            <div class="fw-bold small text-dark">{{ $spec['value'] ?? '-' }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="col-12"><span class="text-muted small fst-italic">Belum ada data spesifikasi.</span></div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <label class="form-label small fw-bold text-muted mb-1"><i class="bi bi-tags me-1 text-info"></i>Atribut Tambahan (Custom Attributes)</label>
                        <div id="pim-attr-container" class="table-responsive border rounded-2 bg-light bg-opacity-25" style="max-height: 180px;">
                            <table class="table table-sm table-borderless mb-0 small">
                                <tbody>
                                    @if(isset($product) && !empty($product->custom_attributes_list))
                                        @foreach($product->custom_attributes_list as $ca)
                                            <tr class="border-bottom border-light">
                                                <th class="text-muted ps-2 py-1" style="width: 40%;">{{ $ca['attributeCode'] ?? '' }}</th>
                                                <td class="text-dark pe-2 py-1 fw-semibold">{{ $plainPimText($ca['value'] ?? '-') }}</td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr><td class="text-muted ps-2 py-1 fst-italic">Belum ada atribut kustom.</td></tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="mt-3 border-top pt-3">
                <label class="form-label small fw-bold text-muted mb-2"><i class="bi bi-images me-1 text-primary"></i>Media tambahan PIM</label>
                <div id="pim-extra-media" class="small">
                    @if(isset($product) && !empty($product->pim_payload['media']))
                        @foreach($product->pim_payload['media'] as $group)
                            @foreach($group['files'] ?? [] as $file)
                                @php($mediaUrl = (string) ($file['value'] ?? ''))
                                <div class="p-2 mb-2 border rounded-2 bg-light bg-opacity-50">
                                    <div class="d-flex gap-3 align-items-start">
                                        @if($pimMediaKind($mediaUrl) === 'image')
                                            <a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer" class="flex-shrink-0">
                                                <img src="{{ $mediaUrl }}" alt="{{ $group['name'] ?? 'Media PIM' }}" class="rounded border bg-white object-fit-contain" style="width: 112px; height: 82px;">
                                            </a>
                                        @elseif($pimMediaKind($mediaUrl) === 'video')
                                            <video controls preload="metadata" class="rounded border bg-dark flex-shrink-0" style="width: 180px; max-height: 110px;">
                                                <source src="{{ $mediaUrl }}">
                                            </video>
                                        @endif
                                        <div class="min-w-0">
                                            <strong>{{ $group['name'] ?? $group['attributeCode'] ?? 'Media' }}</strong>
                                            <span class="badge bg-light text-dark ms-1">{{ $group['attributeCode'] ?? '' }}</span>
                                    <div class="text-muted">{{ $plainPimText($file['description'] ?? '') }}</div>
                                            @if($mediaUrl !== '')
                                                <a href="{{ $mediaUrl }}" target="_blank" rel="noopener noreferrer" class="small text-break">Buka media asli <i class="bi bi-box-arrow-up-right"></i></a>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        @endforeach
                    @else
                        <span class="text-muted fst-italic">Belum ada media tambahan.</span>
                    @endif
                </div>
            </div>
            <div class="mt-3 border-top pt-3">
                <label class="form-label small fw-bold text-muted mb-2"><i class="bi bi-diagram-3 me-1 text-primary"></i>Metadata varian PIM</label>
                <div id="pim-variant-data" class="row g-2">
                    @if(isset($product) && !empty($product->pim_payload['variant']))
                        @foreach($product->pim_payload['variant'] as $variant)
                            <div class="col-md-6">
                                <div class="p-2 border rounded-2 bg-light bg-opacity-50 h-100 small">
                                    <div class="fw-bold text-dark">{{ $variant['name'] ?? $variant['sku'] ?? 'Varian' }}</div>
                                    <div class="font-monospace">{{ $variant['sku'] ?? '' }}</div>
                                    <div>ECM SKU: {{ $variant['ecmsku'] ?? '—' }} · MOQ: {{ $variant['moq'] ?? '—' }}</div>
                                    <div>Warna: {{ $variant['color'] ?? '—' }} · Ukuran: {{ $variant['size'] ?? '—' }}</div>
                                    @foreach($variant['customAttributes'] ?? [] as $attribute)
                                        <div><span class="text-muted">{{ $attribute['attributeCode'] ?? '' }}:</span> {{ $plainPimText($attribute['value'] ?? '—') }}</div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="col-12 text-muted fst-italic small">Belum ada metadata varian.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <details class="col-12 mt-2">
        <summary class="small text-muted mb-2">Lihat payload mentah PIM (Opsional / Debugging)</summary>
        <label class="form-label small" for="pim-payload">Detail produk (JSON)</label>
        <textarea id="pim-payload" name="pim_payload_json" class="form-control font-monospace mb-2 small" rows="6">{{ old('pim_payload_json', isset($product) && $product->pim_payload ? json_encode($product->pim_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
        <label class="form-label small" for="pim-images">Image to Channel (JSON)</label>
        <textarea id="pim-images" name="pim_image_payload_json" class="form-control font-monospace small" rows="5">{{ old('pim_image_payload_json', isset($product) && $product->pim_image_payload ? json_encode($product->pim_image_payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) : '') }}</textarea>
    </details>
    @error('pim_payload_json') <div class="text-danger small">{{ $message }}</div> @enderror
