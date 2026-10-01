<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\TableExpeditionConfig;
use App\Models\TableExpeditionItem;
use App\Services\TableExpeditionMediaStorage;
use App\Services\TableExpeditionReadiness;
use App\Support\PimMediaUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TableExpeditionController extends Controller
{
    public function index(TableExpeditionReadiness $readiness): View
    {
        $items = TableExpeditionItem::query()
            ->where('is_active', true)
            ->with([
                'rfidTag',
                'product.zone',
                'product.atomCategory',
                'product.atomSubCategory',
                'product.variants',
                'product.technologiesRelation',
                'product.activitiesRelation.atomActivity.group',
                'product.specificationsRelation',
                'product.customAttributesRelation',
                'product.mediaRelation',
                'product.pimRecord',
            ])
            ->latest('last_scanned_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (TableExpeditionItem $item) use ($readiness): TableExpeditionItem {
                $item->setAttribute('readiness', $readiness->inspect($item->product));

                return $item;
            });

        $standby = [
            'title' => TableExpeditionConfig::get('standby_title', 'EIGER Table Expedition Hub'),
            'subtitle' => TableExpeditionConfig::get('standby_subtitle', 'Letakkan produk ber-tag RFID di atas meja untuk melihat detail produk.'),
            'instructions' => $this->instructions(),
            'media_type' => TableExpeditionConfig::get('standby_media_type'),
            'media_url' => TableExpeditionConfig::get('standby_media_url'),
        ];

        return view('admin.table-expedition.index', [
            'items' => $items,
            'comparisonProducts' => $this->comparisonProducts(),
            'standby' => $standby,
            'summary' => [
                'active' => $items->count(),
                'ready' => $items->filter(fn (TableExpeditionItem $item): bool => (bool) data_get($item->readiness, 'ready'))->count(),
                'incomplete' => $items->reject(fn (TableExpeditionItem $item): bool => (bool) data_get($item->readiness, 'ready'))->count(),
                'scanned' => $items->whereNotNull('last_scanned_at')->count(),
            ],
        ]);
    }

    public function updateConfig(
        Request $request,
        TableExpeditionMediaStorage $mediaStorage,
    ): RedirectResponse {
        $validated = $request->validate([
            'standby_title' => ['required', 'string', 'max:150'],
            'standby_subtitle' => ['nullable', 'string', 'max:255'],
            'usage_instructions' => ['nullable', 'string', 'max:2000'],
            'remove_standby_media' => ['nullable', 'boolean'],
            'standby_media_file' => [
                'nullable',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm',
                'max:'.((int) max(
                    config('table_expedition.max_image_mb', 15),
                    config('table_expedition.max_video_mb', 500),
                ) * 1024),
            ],
        ], [
            'standby_media_file.mimetypes' => 'Media standby harus berformat JPG, PNG, WebP, MP4, atau WebM.',
        ]);

        if ($request->hasFile('standby_media_file')) {
            $file = $request->file('standby_media_file');
            $previousMediaUrl = TableExpeditionConfig::get('standby_media_url');
            $isVideo = str_starts_with((string) $file->getMimeType(), 'video/');
            $maxBytes = (int) config($isVideo
                ? 'table_expedition.max_video_mb'
                : 'table_expedition.max_image_mb') * 1024 * 1024;

            if ($file->getSize() > $maxBytes) {
                return back()->withErrors([
                    'standby_media_file' => $isVideo
                        ? 'Ukuran video standby melebihi batas yang diizinkan.'
                        : 'Ukuran gambar standby melebihi batas yang diizinkan.',
                ])->withInput();
            }

            $stored = $mediaStorage->store($file);
            TableExpeditionConfig::set('standby_media_type', $stored['type']);
            TableExpeditionConfig::set('standby_media_url', $stored['url']);
            if ($previousMediaUrl !== $stored['url']) {
                $mediaStorage->delete(is_string($previousMediaUrl) ? $previousMediaUrl : null);
            }
        } elseif ($request->boolean('remove_standby_media')) {
            $previousMediaUrl = TableExpeditionConfig::get('standby_media_url');
            $mediaStorage->delete(is_string($previousMediaUrl) ? $previousMediaUrl : null);
            TableExpeditionConfig::set('standby_media_type', null);
            TableExpeditionConfig::set('standby_media_url', null);
        }

        TableExpeditionConfig::set('standby_title', $validated['standby_title']);
        TableExpeditionConfig::set('standby_subtitle', $validated['standby_subtitle'] ?? null);
        TableExpeditionConfig::set(
            'usage_instructions',
            array_values(array_filter(array_map(
                'trim',
                preg_split('/\r\n|\r|\n/', (string) ($validated['usage_instructions'] ?? '')) ?: [],
            ))),
        );

        return redirect()->route('admin.table-expedition.index')
            ->with('success', 'Konfigurasi standby Table Expedition berhasil diperbarui.');
    }

    public function updateComparisons(Request $request, TableExpeditionItem $item): RedirectResponse
    {
        abort_unless($item->is_active, 404);

        $validated = $request->validate([
            'similar_product_ids' => ['nullable', 'array', 'max:5'],
            'similar_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
        ]);
        $selectedIds = collect($validated['similar_product_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        if ($selectedIds->contains((int) $item->product_id)) {
            throw ValidationException::withMessages([
                'similar_product_ids' => 'Produk utama tidak dapat dipilih sebagai produk komparasi.',
            ]);
        }

        $activeIds = TableExpeditionItem::query()
            ->where('is_active', true)
            ->whereIn('product_id', $selectedIds)
            ->pluck('product_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique();

        if ($selectedIds->diff($activeIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'similar_product_ids' => 'Semua produk komparasi harus aktif pada Table Expedition.',
            ]);
        }

        $item->update(['similar_product_ids' => $selectedIds->all()]);

        return redirect()->route('admin.table-expedition.index')
            ->with('success', 'Pilihan produk komparasi berhasil disimpan.');
    }

    /** @return list<string> */
    private function instructions(): array
    {
        $instructions = TableExpeditionConfig::get('usage_instructions', []);

        return is_array($instructions) ? array_values($instructions) : [];
    }

    /** @return list<array{id:int,name:string,sku:string,image:?string}> */
    private function comparisonProducts(): array
    {
        $productIds = TableExpeditionItem::query()
            ->where('is_active', true)
            ->pluck('product_id')
            ->unique();

        return Product::query()
            ->whereIn('id', $productIds)
            ->where('is_discontinued', false)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'image'])
            ->map(fn (Product $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'image' => PimMediaUrl::toPublicUrl($product->image),
            ])
            ->values()
            ->all();
    }
}
