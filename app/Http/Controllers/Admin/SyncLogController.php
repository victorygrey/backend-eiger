<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SyncLog;
use Illuminate\Http\Request;

class SyncLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = SyncLog::query()
            ->when($request->filled('source'), fn($q) => $q->where('source', $request->source))
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->latest('synced_at')
            ->paginate(10)
            ->withQueryString();

        $sources = SyncLog::select('source')->distinct()->pluck('source');
        $statuses = SyncLog::select('status')->distinct()->pluck('status');

        return view('admin.sync-logs.index', compact('logs', 'sources', 'statuses'));
    }

    public function show(SyncLog $syncLog)
    {
        return view('admin.sync-logs.show', compact('syncLog'));
    }
}
