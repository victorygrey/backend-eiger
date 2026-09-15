<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PimController extends Controller
{
    public function index()
    {
        $folder = config('pim.folder');
        if (!is_dir($folder) && $folder === storage_path('app/pim-drop')) {
            @mkdir($folder, 0775, true);
        }

        $connection = [
            'online' => false,
            'url' => config('pim.url', 'http://192.168.18.31:8001'),
            'article_count' => 0,
            'message' => 'Belum diperiksa',
        ];

        if (!app()->runningUnitTests() || config('pim.test_connection', false)) {
            try {
                $resp = Http::timeout(3)->get(rtrim(config('pim.url', 'http://192.168.18.31:8001'), '/').'/api/articles/channel-list');
                if ($resp->successful()) {
                    $connection['online'] = true;
                    $connection['message'] = 'PIM Server terhubung dan aktif.';
                    try {
                        $countResp = Http::timeout(3)->get(rtrim(config('pim.url', 'http://192.168.18.31:8001'), '/').'/api/articles/publish-list?limit=1');
                        if ($countResp->successful()) {
                            $connection['article_count'] = (int) ($countResp->json('data.pagination.total') ?? 0);
                        }
                    } catch (\Throwable $e) {
                        // ignore count failure
                    }
                } else {
                    $connection['message'] = 'PIM merespons HTTP ' . $resp->status();
                }
            } catch (\Throwable $e) {
                $connection['message'] = 'Tidak dapat menghubungi PIM: ' . $e->getMessage();
            }
        }

        $totalCmsProducts = Product::count();
        $enrichedProducts = Product::whereNotNull('image')->where('image', '!=', '')->count();

        $latestLogs = SyncLog::where('source', 'like', 'pim-%')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get();

        return view('admin.pim.index', [
            'connection' => $connection,
            'imports' => DB::table('pim_imports')->orderByDesc('updated_at')->paginate(20),
            'folder' => $folder,
            'readable' => is_dir($folder) && is_readable($folder),
            'counts' => DB::table('pim_imports')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'totalCmsProducts' => $totalCmsProducts,
            'enrichedProducts' => $enrichedProducts,
            'latestLogs' => $latestLogs,
        ]);
    }

    public function sync(Request $request)
    {
        $result = app(\App\Services\PimCatalogSync::class)->run('web');
        return redirect()->route('admin.pim.index')
            ->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function scan(Request $request, \App\Services\PimFolderImporter $importer)
    {
        try {
            $result = $importer->scan($request->boolean('retry_failed'));
            return redirect()->route('admin.pim.index')->with($result['failed'] ? 'error' : 'success',
                "Impor: {$result['imported']} batch, gagal: {$result['failed']}, dilewati: {$result['skipped']}.");
        } catch (\Throwable $error) {
            return redirect()->route('admin.pim.index')->with('error', $error->getMessage());
        }
    }

    public function qa()
    {
        abort_unless(config('pim.legacy_http_enabled'), 404);
        return view('admin.pim.qa');
    }

    public function proxy(Request $request, string $endpoint)
    {
        abort_unless(config('pim.legacy_http_enabled'), 404);
        $paths = [
            'publish-list' => '/api/articles/publish-list',
            'channel-list' => '/api/articles/channel-list',
            'publish' => '/api/articles/publish',
            'history' => '/api/articles/list-index-publish',
            'logs' => '/api/publish-logs',
        ];
        abort_unless(isset($paths[$endpoint]), 404);
        abort_unless($request->isMethod($endpoint === 'publish' ? 'POST' : 'GET'), 405);

        try {
            $client = Http::acceptJson()->connectTimeout(3)->timeout(config('pim.timeout'));
            $url = rtrim(config('pim.url'), '/').$paths[$endpoint];
            // Never retry publish: a timed-out request may already have queued a job.
            $response = $endpoint === 'publish'
                ? $client->post($url, $request->only('articles'))
                : $client->get($url, $request->only('page', 'limit', 'search', 'channel', 'status'));
            $data = $response->json();
            if (!is_array($data)) {
                return response()->json(['status' => false, 'message' => 'Respons PIM tidak valid.'], 502);
            }
            return response()->json($data, $response->status());
        } catch (ConnectionException $e) {
            return response()->json([
                'status' => false,
                'message' => 'PIM tidak dapat dihubungi. Periksa koneksi dan PIM_SIMULATOR_URL. Jika sedang publish, periksa riwayat sebelum mencoba kembali.',
            ], 502);
        }
    }
}
