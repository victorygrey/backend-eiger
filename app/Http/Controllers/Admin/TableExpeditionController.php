<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoActivity;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TableExpeditionController extends Controller
{
    /**
     * Display Table Expedition Hub configuration (RFID mappings & Standby settings).
     */
    public function index(): View
    {
        $items = TableExpeditionItem::with(['product.zone', 'rfidTag'])
            ->latest('id')
            ->get();

        $products = Product::where('is_discontinued', false)
            ->orderBy('name')
            ->get();

        $activities = FitAndGoActivity::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableRfidTags = RfidTag::with('product')
            ->orderBy('name')
            ->orderBy('uid')
            ->get();

        $standbyTitle = TableExpeditionConfig::get('standby_title', 'EIGER Table Expedition Hub');
        $standbySubtitle = TableExpeditionConfig::get('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat spesifikasi detail dan komparasi.');
        $rawInstructions = TableExpeditionConfig::get('usage_instructions', []);
        $instructions = is_array($rawInstructions) ? $rawInstructions : (json_decode($rawInstructions, true) ?: []);

        return view('admin.table-expedition.index', [
            'items'             => $items,
            'products'          => $products,
            'activities'        => $activities,
            'availableRfidTags' => $availableRfidTags,
            'standbyTitle'      => $standbyTitle,
            'standbySubtitle'   => $standbySubtitle,
            'instructions'      => $instructions,
        ]);
    }

    /**
     * Store a newly created Table Expedition RFID mapping.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_tag'            => 'required|string|max:64|unique:table_expedition_items,rfid_tag',
            'product_id'          => 'required|exists:products,id',
            'activity_slug'       => 'nullable|string|max:50',
            'ideal_for'           => 'nullable|string|max:255',
            'video_url'           => 'nullable|url|max:500',
            'features'            => 'nullable|string',
            'technical_details'   => 'nullable|string',
            'ai_summary'          => 'nullable|string',
            'similar_product_ids' => 'nullable|array|max:5',
            'similar_product_ids.*' => 'integer|exists:products,id',
            'notes'               => 'nullable|string|max:255',
            'is_active'           => 'nullable|boolean',
        ]);

        $validated['rfid_tag'] = trim(strtoupper($validated['rfid_tag']));
        $validated['is_active'] = $request->boolean('is_active', true);

        // Convert features from newline-separated string to array
        if (!empty($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        // Convert technical details from newline-separated string to array
        if (!empty($validated['technical_details'])) {
            $lines = array_filter(array_map('trim', explode("\n", $validated['technical_details'])));
            $details = [];
            foreach ($lines as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $details[trim($k)] = trim($v);
                } else {
                    $details[] = $line;
                }
            }
            $validated['technical_details'] = $details;
        }

        TableExpeditionItem::create($validated);

        return redirect()->route('admin.table-expedition.index')
            ->with('success', "Mapping RFID {$validated['rfid_tag']} berhasil ditambahkan ke Table Expedition.");
    }

    /**
     * Update an existing Table Expedition RFID mapping.
     */
    public function update(Request $request, TableExpeditionItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_tag'            => 'required|string|max:64|unique:table_expedition_items,rfid_tag,' . $item->id,
            'product_id'          => 'required|exists:products,id',
            'activity_slug'       => 'nullable|string|max:50',
            'ideal_for'           => 'nullable|string|max:255',
            'video_url'           => 'nullable|url|max:500',
            'features'            => 'nullable|string',
            'technical_details'   => 'nullable|string',
            'ai_summary'          => 'nullable|string',
            'similar_product_ids' => 'nullable|array|max:5',
            'similar_product_ids.*' => 'integer|exists:products,id',
            'notes'               => 'nullable|string|max:255',
            'is_active'           => 'nullable|boolean',
        ]);

        $validated['rfid_tag'] = trim(strtoupper($validated['rfid_tag']));
        $validated['is_active'] = $request->boolean('is_active');

        // Convert features
        if (isset($validated['features'])) {
            $validated['features'] = array_values(array_filter(array_map('trim', explode("\n", $validated['features']))));
        }

        // Convert technical details
        if (isset($validated['technical_details'])) {
            $lines = array_filter(array_map('trim', explode("\n", $validated['technical_details'])));
            $details = [];
            foreach ($lines as $line) {
                if (str_contains($line, ':')) {
                    [$k, $v] = explode(':', $line, 2);
                    $details[trim($k)] = trim($v);
                } else {
                    $details[] = $line;
                }
            }
            $validated['technical_details'] = $details;
        }

        $item->update($validated);

        return redirect()->route('admin.table-expedition.index')
            ->with('success', "Konfigurasi Table Expedition untuk RFID {$item->rfid_tag} berhasil diperbarui.");
    }

    /**
     * Remove an existing Table Expedition RFID mapping.
     */
    public function destroy(TableExpeditionItem $item): RedirectResponse
    {
        $tag = $item->rfid_tag;
        $item->delete();

        return redirect()->route('admin.table-expedition.index')
            ->with('success', "Mapping RFID {$tag} berhasil dihapus dari Table Expedition.");
    }

    /**
     * Update Table Expedition Standby instructions and welcome message.
     */
    public function updateConfig(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'standby_title'      => 'required|string|max:150',
            'standby_subtitle'   => 'nullable|string|max:255',
            'usage_instructions' => 'nullable|string',
        ]);

        TableExpeditionConfig::set('standby_title', $validated['standby_title']);
        TableExpeditionConfig::set('standby_subtitle', $validated['standby_subtitle']);

        if (isset($validated['usage_instructions'])) {
            $inst = array_values(array_filter(array_map('trim', explode("\n", $validated['usage_instructions']))));
            TableExpeditionConfig::set('usage_instructions', json_encode($inst));
        }

        return redirect()->route('admin.table-expedition.index')
            ->with('success', 'Pengaturan Standby & Petunjuk Penggunaan Table Expedition berhasil diperbarui.');
    }
}
