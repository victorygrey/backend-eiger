<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FitAndGoActivity;
use App\Models\LedAmbienceItem;
use App\Models\LedAmbienceScene;
use App\Models\Product;
use App\Models\RfidTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LedAmbienceController extends Controller
{
    /**
     * Display LED Ambience configuration dashboard (RFID Mappings & Scenes).
     */
    public function index(Request $request): View
    {
        $currentTab = $request->query('tab', 'rfid');

        $rfidItems = LedAmbienceItem::with(['product.zone', 'scene', 'rfidTag'])
            ->latest('id')
            ->get();

        $scenes = LedAmbienceScene::withCount('items')
            ->orderBy('sort_order')
            ->get();

        $products = Product::where('is_discontinued', false)
            ->orderBy('name')
            ->get();

        $activities = FitAndGoActivity::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $availableRfidTags = RfidTag::with('product')->orderBy('name')->orderBy('uid')->get();

        return view('admin.led-ambience.index', [
            'currentTab'        => $currentTab,
            'rfidItems'         => $rfidItems,
            'scenes'            => $scenes,
            'products'          => $products,
            'activities'        => $activities,
            'availableRfidTags' => $availableRfidTags,
        ]);
    }

    // ==========================================
    // RFID ITEM MAPPINGS (Full CRUD for LED Ambience)
    // ==========================================

    public function storeRfidItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_tag'      => 'required|string|max:64|unique:led_ambience_items,rfid_tag',
            'product_id'    => 'required|exists:products,id',
            'activity_slug' => 'nullable|string|max:50',
            'scene_id'      => 'nullable|exists:led_ambience_scenes,id',
            'notes'         => 'nullable|string|max:255',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['rfid_tag'] = trim(strtoupper($validated['rfid_tag']));
        $validated['is_active'] = $request->boolean('is_active', true);

        LedAmbienceItem::create($validated);

        return redirect()->route('admin.led-ambience.index', ['tab' => 'rfid'])
            ->with('success', "Mapping RFID {$validated['rfid_tag']} berhasil ditambahkan ke LED Ambience.");
    }

    public function updateRfidItem(Request $request, LedAmbienceItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'rfid_tag'      => 'required|string|max:64|unique:led_ambience_items,rfid_tag,' . $item->id,
            'product_id'    => 'required|exists:products,id',
            'activity_slug' => 'nullable|string|max:50',
            'scene_id'      => 'nullable|exists:led_ambience_scenes,id',
            'notes'         => 'nullable|string|max:255',
            'is_active'     => 'nullable|boolean',
        ]);

        $validated['rfid_tag'] = trim(strtoupper($validated['rfid_tag']));
        $validated['is_active'] = $request->boolean('is_active');

        $item->update($validated);

        return redirect()->route('admin.led-ambience.index', ['tab' => 'rfid'])
            ->with('success', "Konfigurasi RFID {$item->rfid_tag} berhasil diperbarui.");
    }

    public function destroyRfidItem(LedAmbienceItem $item): RedirectResponse
    {
        $tag = $item->rfid_tag;
        $item->delete();

        return redirect()->route('admin.led-ambience.index', ['tab' => 'rfid'])
            ->with('success', "Mapping RFID {$tag} berhasil dihapus dari LED Ambience.");
    }

    // ==========================================
    // AMBIENCE SCENES (Video, Audio, Lighting)
    // ==========================================

    public function storeScene(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:150',
            'scene_type'     => 'required|in:idle,active,default',
            'activity_slug'  => 'nullable|string|max:50',
            'video_url'      => 'nullable|url|max:500',
            'audio_url'      => 'nullable|url|max:500',
            'lighting_color' => 'nullable|string|max:20',
            'description'    => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['lighting_color'] = $validated['lighting_color'] ?: '#e8500a';

        LedAmbienceScene::create($validated);

        return redirect()->route('admin.led-ambience.index', ['tab' => 'scenes'])
            ->with('success', "Scene Ambience '{$validated['name']}' berhasil ditambahkan.");
    }

    public function updateScene(Request $request, LedAmbienceScene $scene): RedirectResponse
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:150',
            'scene_type'     => 'required|in:idle,active,default',
            'activity_slug'  => 'nullable|string|max:50',
            'video_url'      => 'nullable|url|max:500',
            'audio_url'      => 'nullable|url|max:500',
            'lighting_color' => 'nullable|string|max:20',
            'description'    => 'nullable|string|max:500',
            'sort_order'     => 'nullable|integer|min:0',
            'is_active'      => 'nullable|boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['lighting_color'] = $validated['lighting_color'] ?: '#e8500a';

        $scene->update($validated);

        return redirect()->route('admin.led-ambience.index', ['tab' => 'scenes'])
            ->with('success', "Scene Ambience '{$scene->name}' berhasil diperbarui.");
    }

    public function destroyScene(LedAmbienceScene $scene): RedirectResponse
    {
        $name = $scene->name;
        $scene->delete();

        return redirect()->route('admin.led-ambience.index', ['tab' => 'scenes'])
            ->with('success', "Scene Ambience '{$name}' berhasil dihapus.");
    }
}
