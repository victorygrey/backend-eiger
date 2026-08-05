<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintRule;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\SyncLog;
use App\Models\Zone;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard with summary statistics.
     */
    public function index()
    {
        $stats = [
            'total_products'  => Product::count(),
            'total_zones'     => Zone::count(),
            'total_rfid_tags' => RfidTag::count(),
            'total_rules'     => PrintRule::count(),
        ];

        $lastSync     = SyncLog::latest('synced_at')->first();
        $recentLogs   = SyncLog::latest()->take(5)->get();

        return view('admin.dashboard', compact('stats', 'lastSync', 'recentLogs'));
    }
}
