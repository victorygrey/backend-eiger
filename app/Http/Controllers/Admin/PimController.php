<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PimController extends Controller
{
    public function index()
    {
        return view('admin.pim.index', [
            'imports' => \Illuminate\Support\Facades\DB::table('pim_imports')->orderByDesc('updated_at')->paginate(20),
            'folder' => config('pim.folder'),
            'readable' => is_dir(config('pim.folder')) && is_readable(config('pim.folder')),
            'counts' => \Illuminate\Support\Facades\DB::table('pim_imports')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
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
