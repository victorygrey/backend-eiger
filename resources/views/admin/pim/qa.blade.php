@extends('layouts.admin')
@section('title', 'PIM Integration')
@section('page-title', 'PIM Integration')
@section('content')
<p class="text-muted">Pilih artikel yang siap dipublikasikan. Setelah antrean selesai, produk varian masuk ke katalog CMS melalui channel ATOM.</p>
<div id="pim-message" class="alert alert-info" role="status">Menghubungi PIM...</div>
<div class="card p-3 mb-4">
    <form id="pim-search" class="d-flex gap-2 mb-3">
        <input id="search" class="form-control" placeholder="Cari nama atau SAP ID" aria-label="Cari artikel">
        <button class="btn btn-outline-primary">Cari</button>
    </form>
    <div class="d-flex gap-2 mb-3">
        <select id="channel" class="form-select" aria-label="Channel publish"></select>
        <button id="publish" class="btn btn-primary" disabled>Publish terpilih</button>
        <button id="refresh" class="btn btn-outline-secondary">Refresh</button>
    </div>
    <div class="table-responsive"><table class="table"><thead><tr><th>Pilih</th><th>SAP ID</th><th>Artikel</th><th>Kelengkapan</th></tr></thead><tbody id="articles"></tbody></table></div>
    <div class="d-flex gap-3 align-items-center"><button id="prev" class="btn btn-sm btn-outline-secondary">Sebelumnya</button><span id="page"></span><button id="next" class="btn btn-sm btn-outline-secondary">Berikutnya</button></div>
</div>
<div class="card p-3 mb-4"><h5>Riwayat publish</h5><p class="text-muted">Status diperbarui setiap 5 detik. Respons publish awal berarti masuk antrean.</p><div class="table-responsive"><table class="table"><thead><tr><th>Artikel</th><th>Channel</th><th>Status</th><th>Diperbarui</th></tr></thead><tbody id="history"></tbody></table></div></div>
<div class="card p-3"><h5>Log PIM terbaru</h5><div class="table-responsive"><table class="table"><thead><tr><th>Artikel</th><th>Channel</th><th>Status</th><th>Pesan</th></tr></thead><tbody id="logs"></tbody></table></div></div>
@endsection
@push('scripts')
<script>
(() => {
    const base = @json(url('/admin/pim'));
    const csrf = @json(csrf_token());
    const el = id => document.getElementById(id);
    let articles = [], page = 1, totalPages = 1, busy = false, polling = false;
    function message(text, error = false) {
        el('pim-message').textContent = text;
        el('pim-message').className = 'alert ' + (error ? 'alert-danger' : 'alert-info');
    }
    async function api(path, body) {
        const response = await fetch(base + '/' + path, {
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
            ...(body ? {method: 'POST', body: JSON.stringify(body)} : {}),
        });
        const data = await response.json();
        if (!response.ok || data.status === false) throw new Error(data.message || 'Permintaan PIM gagal');
        return data;
    }
    function rows(id, data, fields) {
        el(id).replaceChildren();
        for (const item of data) {
            const row = document.createElement('tr');
            for (const field of fields) {
                const cell = document.createElement('td');
                cell.textContent = item[field] ?? '-';
                row.append(cell);
            }
            el(id).append(row);
        }
        if (!data.length) {
            const row = document.createElement('tr'), cell = document.createElement('td');
            cell.colSpan = fields.length; cell.textContent = 'Belum ada data'; row.append(cell); el(id).append(row);
        }
    }
    async function loadArticles() {
        const data = await api('publish-list?' + new URLSearchParams({page, limit: 10, search: el('search').value}));
        articles = data.data.data;
        totalPages = data.data.pagination.total_pages;
        rows('articles', articles, ['sap_id', 'sap_id', 'name', 'completeness_percentages']);
        [...el('articles').children].forEach((row, i) => {
            if (!articles[i]) return;
            const box = document.createElement('input'); box.type = 'checkbox'; box.value = i;
            box.setAttribute('aria-label', 'Pilih ' + articles[i].name);
            row.firstChild.replaceChildren(box);
        });
        el('page').textContent = `Halaman ${page} / ${totalPages}`;
        el('prev').disabled = page <= 1; el('next').disabled = page >= totalPages;
    }
    async function loadStatus() {
        if (polling) return;
        polling = true;
        try {
            const [history, logs] = await Promise.all([api('history'), api('logs?limit=20')]);
            rows('history', history.data, ['article_generic', 'channel', 'status', 'updated_at']);
            rows('logs', logs.data, ['article_generic', 'channel_code', 'status', 'error_message']);
        } finally { polling = false; }
    }
    async function refresh() {
        try {
            const channels = await api('channel-list');
            const selected = el('channel').value;
            el('channel').replaceChildren();
            channels.data.forEach(channel => {
                const option = document.createElement('option'); option.value = channel.code; option.textContent = channel.name;
                el('channel').append(option);
            });
            if (channels.data.some(c => c.code === selected)) el('channel').value = selected;
            await Promise.all([loadArticles(), loadStatus()]);
            message('Terhubung ke PIM. Gunakan channel ATOM untuk mengirim ke CMS.');
            el('publish').disabled = busy || !channels.data.length;
        } catch (error) { message(error.message, true); }
    }
    el('publish').onclick = async () => {
        if (busy) return;
        const selected = [...el('articles').querySelectorAll('input:checked')].map(box => articles[box.value]);
        if (!selected.length) return message('Pilih minimal satu artikel.', true);
        busy = true; el('publish').disabled = true;
        try {
            const response = await api('publish', {articles: selected.map(article => ({
                articles_parent: article.sap_id, article_enrichment_id: article.article_enrichment_id,
                articles_child: [], channels: [{code: el('channel').value}],
            }))});
            message(response.message); await loadStatus();
        } catch (error) { message(error.message, true); }
        finally { busy = false; el('publish').disabled = false; }
    };
    el('pim-search').onsubmit = event => { event.preventDefault(); page = 1; loadArticles().catch(error => message(error.message, true)); };
    el('prev').onclick = () => { page--; loadArticles().catch(error => message(error.message, true)); };
    el('next').onclick = () => { page++; loadArticles().catch(error => message(error.message, true)); };
    el('refresh').onclick = refresh;
    setInterval(() => { if (!document.hidden) loadStatus().catch(error => message(error.message, true)); }, 5000);
    refresh();
})();
</script>
@endpush
