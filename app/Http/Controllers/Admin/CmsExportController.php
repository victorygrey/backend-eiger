<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoCapture;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\SyncLog;

class CmsExportController extends Controller
{
    public function export(string $module)
    {
        [$query, $fields] = match ($module) {
            'products' => [Product::query(), ['id', 'sku', 'name', 'category', 'price', 'stock', 'ai_fit_and_go_active', 'interactive_tablet_active']],
            'rfid-tags' => [RfidTag::query(), ['id', 'uid', 'name', 'product_id', 'last_scanned_at']],
            'print-history' => [PhotoCapture::query(), ['id', 'capture_code', 'fit_and_go_device_id', 'captured_at', 'expires_at']],
            'sync-logs' => [SyncLog::query(), ['id', 'source', 'status', 'created_at']],
            default => abort(404),
        };

        return response()->streamDownload(function () use ($query, $fields) {
            $stream = fopen('php://output', 'w');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $fields, ',', '"', '');
            foreach ($query->select($fields)->orderBy('id')->cursor() as $row) {
                $values = [];
                foreach ($fields as $field) {
                    $value = (string) $row->getRawOriginal($field);
                    $values[] = preg_match('/^[\s]*[=+@-]/u', $value) ? "'".$value : $value;
                }
                fputcsv($stream, $values, ',', '"', '');
            }
            fclose($stream);
        }, $module.'-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
