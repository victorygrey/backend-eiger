<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\SyncLog;
use App\Services\CareSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CareController extends Controller
{
    public function __construct(protected CareSyncService $careSync) {}

    /**
     * Display the CARE Integration management page.
     */
    public function index(): View
    {
        $connection = $this->careSync->testConnection();

        $totalProducts = Product::count();
        $pricedProducts = Product::whereNotNull('price')->where('price', '>', 0)->count();
        $stockedProducts = Product::whereNotNull('stock')->where('stock', '>', 0)->count();

        $latestLogs = SyncLog::where('source', 'like', 'care-%')
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        $lastSync = SyncLog::where('source', 'like', 'care-%')
            ->where('status', 'success')
            ->orderBy('id', 'desc')
            ->first();

        // Sample products synced with CARE
        $recentProducts = Product::whereNotNull('price')
            ->orderBy('updated_at', 'desc')
            ->limit(8)
            ->get();

        return view('admin.care.index', [
            'connection' => $connection,
            'totalProducts' => $totalProducts,
            'pricedProducts' => $pricedProducts,
            'stockedProducts' => $stockedProducts,
            'latestLogs' => $latestLogs,
            'lastSync' => $lastSync,
            'recentProducts' => $recentProducts,
            'storeCode' => config('services.care.store_code', '2022'),
            'storeName' => 'Toko Flagship Setiabudi (Bandung)',
            'careUrl' => $connection['url'],
        ]);
    }

    /**
     * Trigger manual CARE synchronization from web interface.
     */
    public function sync(Request $request): RedirectResponse
    {
        $result = $this->careSync->sync('web');

        if ($result['success']) {
            return redirect()->route('admin.care.index')
                ->with('success', $result['message']);
        }

        return redirect()->route('admin.care.index')
            ->with('error', $result['message']);
    }
}
