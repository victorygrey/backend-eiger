<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SyncLog;
use App\Services\CareSyncService;
use App\Services\PimFolderImporter;
use App\Services\PimSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function __construct(
        protected PimSyncService $pimSync,
        protected CareSyncService $careSync,
        protected PimFolderImporter $folderImporter
    ) {}

    /**
     * Display the Unified Integrations Management Page (PIM & CARE).
     */
    public function index(): View
    {
        $folder = config('pim.folder');
        if (!is_dir($folder) && $folder === storage_path('app/pim-drop')) {
            @mkdir($folder, 0775, true);
        }

        $pimConnection = $this->pimSync->testConnection();
        $careConnection = $this->careSync->testConnection();

        $totalProducts = Product::count();
        $totalVariants = ProductVariant::count();
        $pricedProducts = Product::whereNotNull('price')->where('price', '>', 0)->count();
        $stockedProducts = Product::whereNotNull('stock')->where('stock', '>', 0)->count();
        $enrichedProducts = Product::whereNotNull('image')->where('image', '!=', '')->count();

        $latestLogs = SyncLog::where('source', 'like', 'pim-%')
            ->orWhere('source', 'like', 'care-%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $lastSync = SyncLog::where(function ($q) {
                $q->where('source', 'like', 'pim-%')
                  ->orWhere('source', 'like', 'care-%');
            })
            ->where('status', 'success')
            ->orderBy('id', 'desc')
            ->first();

        $imports = DB::table('pim_imports')->orderByDesc('updated_at')->paginate(10);
        $importCounts = DB::table('pim_imports')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return view('admin.integrations.index', [
            'pimConnection' => $pimConnection,
            'careConnection' => $careConnection,
            'totalProducts' => $totalProducts,
            'totalVariants' => $totalVariants,
            'pricedProducts' => $pricedProducts,
            'stockedProducts' => $stockedProducts,
            'enrichedProducts' => $enrichedProducts,
            'latestLogs' => $latestLogs,
            'lastSync' => $lastSync,
            'folder' => $folder,
            'readable' => is_dir($folder) && is_readable($folder),
            'imports' => $imports,
            'importCounts' => $importCounts,
            'storeCode' => config('services.care.store_code', '2022'),
            'storeName' => 'Toko Flagship Setiabudi (Bandung)',
            'careUrl' => $careConnection['url'],
            'pimUrl' => config('pim.url', 'http://192.168.18.31:8001'),
        ]);
    }

    /**
     * Trigger combined one-click synchronization for BOTH PIM and CARE.
     */
    public function syncAll(Request $request): RedirectResponse
    {
        $messages = [];
        $hasError = false;

        // 1. Sync PIM Master Catalog
        $pimResult = $this->pimSync->sync('web');
        if ($pimResult['success']) {
            $messages[] = "PIM: {$pimResult['message']}";
        } else {
            $messages[] = "PIM: {$pimResult['message']}";
            $hasError = true;
        }

        // 2. Sync CARE Omni Pricing & Stock
        $careResult = $this->careSync->sync('web');
        if ($careResult['success']) {
            $messages[] = "CARE: {$careResult['message']}";
        } else {
            $messages[] = "CARE: {$careResult['message']}";
            $hasError = true;
        }

        $fullMessage = implode(' | ', $messages);

        if ($hasError) {
            return redirect()->route('admin.integrations.index')->with('warning', $fullMessage);
        }

        return redirect()->route('admin.integrations.index')->with('success', "✓ Sinkronisasi PIM & CARE Berhasil! {$fullMessage}");
    }

    /**
     * Trigger PIM catalog synchronization only.
     */
    public function syncPim(Request $request): RedirectResponse
    {
        $result = $this->pimSync->sync('web');
        return redirect()->route('admin.integrations.index')->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Trigger CARE Omni synchronization only.
     */
    public function syncCare(Request $request): RedirectResponse
    {
        $result = $this->careSync->sync('web');
        return redirect()->route('admin.integrations.index')->with(
            $result['success'] ? 'success' : 'error',
            $result['message']
        );
    }

    /**
     * Scan PIM shared file-drop folder.
     */
    public function scanFolder(Request $request): RedirectResponse
    {
        try {
            $result = $this->folderImporter->scan($request->boolean('retry_failed'));
            return redirect()->route('admin.integrations.index')->with(
                $result['failed'] ? 'error' : 'success',
                "Pemindaian File-Drop: {$result['imported']} batch diimpor, {$result['failed']} gagal, {$result['skipped']} dilewati."
            );
        } catch (\Throwable $error) {
            return redirect()->route('admin.integrations.index')->with('error', $error->getMessage());
        }
    }
}
