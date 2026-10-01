<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRfidTagRequest;
use App\Http\Requests\UpdateRfidTagRequest;
use App\Models\LedAmbienceItem;
use App\Models\Product;
use App\Models\RfidTag;
use App\Models\TableExpeditionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RfidTagController extends Controller
{
    public function index(Request $request)
    {
        $tags = RfidTag::query()
            ->with(['product', 'ledAmbienceItem', 'tableExpeditionItem'])
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = "%{$request->search}%";
                $q->where(function ($sub) use ($term) {
                    $sub->where('uid', 'like', $term)
                        ->orWhere('name', 'like', $term)
                        ->orWhereHas('product', function ($products) use ($term) {
                            $products->where('sku', 'like', $term)
                                ->orWhere('name', 'like', $term);
                        });
                });
            })
            ->when($request->input('mapping') === 'mapped', fn ($query) => $query->whereNotNull('product_id'))
            ->when($request->input('mapping') === 'unassigned', fn ($query) => $query->whereNull('product_id'))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $summary = [
            'total' => RfidTag::count(),
            'mapped' => RfidTag::whereNotNull('product_id')->count(),
            'unassigned' => RfidTag::whereNull('product_id')->count(),
            'scanned' => RfidTag::whereNotNull('last_scanned_at')->count(),
        ];

        $channelSettingsUnlocked = $this->channelSettingsUnlocked($request);

        return view('admin.rfid-tags.index', compact('tags', 'summary', 'channelSettingsUnlocked'));
    }

    public function create()
    {
        return view('admin.rfid-tags.create', ['products' => $this->products()]);
    }

    public function store(StoreRfidTagRequest $request)
    {
        RfidTag::create($request->validated());

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil ditambahkan.');
    }

    public function edit(RfidTag $rfidTag)
    {
        return view('admin.rfid-tags.edit', [
            'rfidTag' => $rfidTag->load('product'),
            'products' => $this->products(),
        ]);
    }

    public function update(UpdateRfidTagRequest $request, RfidTag $rfidTag)
    {
        DB::transaction(function () use ($request, $rfidTag): void {
            $originalUid = $rfidTag->uid;
            $rfidTag->update($request->validated());

            if ($rfidTag->product_id) {
                $mappingUpdate = ['rfid_tag' => $rfidTag->uid, 'product_id' => $rfidTag->product_id];
                LedAmbienceItem::where('rfid_tag', $originalUid)->update($mappingUpdate);
                TableExpeditionItem::where('rfid_tag', $originalUid)->update($mappingUpdate);
            } else {
                $mappingUpdate = ['rfid_tag' => $rfidTag->uid, 'is_active' => false];
                LedAmbienceItem::where('rfid_tag', $originalUid)->update($mappingUpdate);
                TableExpeditionItem::where('rfid_tag', $originalUid)->update($mappingUpdate);
            }
        });

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil diperbarui.');
    }

    public function destroy(RfidTag $rfidTag)
    {
        DB::transaction(function () use ($rfidTag): void {
            LedAmbienceItem::where('rfid_tag', $rfidTag->uid)->delete();
            TableExpeditionItem::where('rfid_tag', $rfidTag->uid)->delete();
            $rfidTag->delete();
        });

        return redirect()->route('admin.rfid-tags.index')
            ->with('success', 'RFID Tag berhasil dihapus.');
    }

    public function updateChannelLock(Request $request)
    {
        $data = $request->validate(['unlocked' => ['required', 'boolean']]);
        $request->session()->put('rfid_tags.channel_settings_unlocked', $data['unlocked']);

        return back()->with('success', $data['unlocked']
            ? 'Pengaturan wahana RFID berhasil dibuka.'
            : 'Pengaturan wahana RFID berhasil dikunci.');
    }

    public function updateChannel(Request $request, RfidTag $rfidTag)
    {
        abort_unless($this->channelSettingsUnlocked($request), 423, 'Pengaturan wahana RFID masih terkunci.');

        $data = $request->validate([
            'channel' => ['required', Rule::in(['led_ambience', 'table_expedition'])],
            'active' => ['required', 'boolean'],
        ]);

        if ($data['active'] && ! $rfidTag->product_id) {
            throw ValidationException::withMessages([
                'channel' => 'Hubungkan RFID ke produk terlebih dahulu sebelum mengaktifkan wahana.',
            ]);
        }

        $model = $data['channel'] === 'led_ambience' ? LedAmbienceItem::class : TableExpeditionItem::class;
        $mapping = $model::where('rfid_tag', $rfidTag->uid)->first();

        if ($data['active']) {
            $model::updateOrCreate(
                ['rfid_tag' => $rfidTag->uid],
                ['product_id' => $rfidTag->product_id, 'is_active' => true],
            );
        } elseif ($mapping) {
            $mapping->update(['is_active' => false]);
        }

        $channelName = $data['channel'] === 'led_ambience' ? 'LED Ambience' : 'Table Expedition';

        return back()->with('success', sprintf(
            'RFID %s berhasil %s untuk %s.',
            $rfidTag->uid,
            $data['active'] ? 'diaktifkan' : 'dinonaktifkan',
            $channelName,
        ));
    }

    private function channelSettingsUnlocked(Request $request): bool
    {
        return filter_var(
            $request->session()->get('rfid_tags.channel_settings_unlocked', false),
            FILTER_VALIDATE_BOOLEAN,
        );
    }

    private function products()
    {
        return Product::query()
            ->with('zone')
            ->whereRaw('LENGTH(sku) = 9')
            ->where('is_discontinued', false)
            ->where(function ($query) {
                $query->whereDoesntHave('pimRecord')
                    ->orWhere('pim_catalog_active', true);
            })
            ->orderBy('name')
            ->get();
    }
}
