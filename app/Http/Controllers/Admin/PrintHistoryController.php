<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoDevice;
use App\Models\PhotoCapture;
use App\Models\PrintJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PrintHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $captures = PhotoCapture::query()
            ->with(['device', 'latestPrintJob'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim($request->string('search')->toString());
                $query->where(function ($captures) use ($search): void {
                    $captures->where('capture_code', 'like', "%{$search}%")
                        ->orWhere('session_reference', 'like', "%{$search}%")
                        ->orWhereHas('latestPrintJob', fn ($jobs) => $jobs->where('print_code', 'like', "%{$search}%"));
                });
            })
            ->when($request->integer('device_id'), fn ($query, $deviceId) => $query->where('fit_and_go_device_id', $deviceId))
            ->when($request->filled('status'), function ($query) use ($request): void {
                $query->whereHas('latestPrintJob', fn ($jobs) => $jobs->where('status', $request->string('status')->toString()));
            })
            ->when($request->filled('date_from'), function ($query) use ($request): void {
                $query->whereDate('captured_at', '>=', $request->string('date_from')->toString());
            })
            ->when($request->filled('date_to'), function ($query) use ($request): void {
                $query->whereDate('captured_at', '<=', $request->string('date_to')->toString());
            })
            ->latest('captured_at')
            ->paginate(15)
            ->withQueryString();

        $summary = [
            'captures' => PhotoCapture::count(),
            'queued' => PrintJob::whereIn('status', ['queued', 'printing'])->count(),
            'success' => PrintJob::where('status', 'success')->count(),
            'failed' => PrintJob::where('status', 'failed')->count(),
        ];

        $devices = FitAndGoDevice::query()->orderBy('location')->orderBy('name')->get(['id', 'name', 'location']);

        return view('admin.print-history.index', compact('captures', 'devices', 'summary'));
    }

    public function show(PhotoCapture $photoCapture): View
    {
        $photoCapture->load(['device', 'printJobs' => fn ($query) => $query->latest('requested_at')]);

        return view('admin.print-history.show', compact('photoCapture'));
    }
}
