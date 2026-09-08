<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BatchStoreRfidTagsRequest;
use App\Http\Requests\ResolveRfidTagsRequest;
use App\Http\Requests\StoreRfidTagRequest;
use App\Http\Requests\UpdateRfidTagRequest;
use App\Http\Resources\RfidTagResource;
use App\Models\RfidTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RfidTagController extends Controller
{
    /**
     * Resolve tag UIDs without creating anything.
     */
    public function resolve(ResolveRfidTagsRequest $request): JsonResponse
    {
        $uids = $request->uids();
        $tags = $this->findByCanonicalUid($uids);

        $data = collect($uids)->map(function (string $uid) use ($tags): array {
            $tag = $tags->get($uid);

            return [
                'uid' => $uid,
                'exists' => $tag !== null,
                'tag' => $tag ? new RfidTagResource($tag) : null,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Register any missing UIDs as unassigned tags. Existing mappings are preserved.
     */
    public function batchStore(BatchStoreRfidTagsRequest $request): JsonResponse
    {
        $created = collect();
        $existing = collect();
        $known = $this->findByCanonicalUid($request->uids());

        foreach ($request->uids() as $uid) {
            $tag = $known->get($uid);
            if ($tag === null) {
                $tag = RfidTag::firstOrCreate(
                    ['uid' => $uid],
                    ['product_id' => null],
                );
                $known->put($uid, $tag);
            }

            ($tag->wasRecentlyCreated ? $created : $existing)->push($tag);
        }

        $created->each->load('product');
        $existing->each->load('product');

        return response()->json([
            'success' => true,
            'message' => sprintf(
                '%d RFID tag(s) created; %d already existed.',
                $created->count(),
                $existing->count(),
            ),
            'created_count' => $created->count(),
            'existing_count' => $existing->count(),
            'created' => RfidTagResource::collection($created)->resolve(),
            'existing' => RfidTagResource::collection($existing)->resolve(),
        ], $created->isNotEmpty() ? 201 : 200);
    }

    /** @param list<string> $uids */
    private function findByCanonicalUid(array $uids): Collection
    {
        $canonicalUid = DB::raw(
            "UPPER(REPLACE(REPLACE(REPLACE(uid, '-', ''), ':', ''), ' ', ''))",
        );

        return RfidTag::with('product')
            ->whereIn($canonicalUid, $uids)
            ->get()
            ->keyBy(fn (RfidTag $tag): string => self::canonicalUid($tag->uid));
    }

    private static function canonicalUid(string $uid): string
    {
        return strtoupper(str_replace(['-', ':', ' '], '', trim($uid)));
    }

    /**
     * Display a list of all RFID tags with their associated products.
     */
    public function index(): AnonymousResourceCollection
    {
        $tags = RfidTag::with('product')->latest()->paginate(15);

        return RfidTagResource::collection($tags);
    }

    /**
     * Store a newly created RFID tag.
     */
    public function store(StoreRfidTagRequest $request): JsonResponse
    {
        $tag = RfidTag::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'RFID tag created successfully.',
            'data'    => new RfidTagResource($tag->load('product')),
        ], 201);
    }

    /**
     * Display the specified RFID tag.
     */
    public function show(RfidTag $rfidTag): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'RFID tag retrieved successfully.',
            'data'    => new RfidTagResource($rfidTag->load('product')),
        ]);
    }

    /**
     * Update the specified RFID tag.
     */
    public function update(UpdateRfidTagRequest $request, RfidTag $rfidTag): JsonResponse
    {
        $rfidTag->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'RFID tag updated successfully.',
            'data'    => new RfidTagResource($rfidTag->fresh()->load('product')),
        ]);
    }

    /**
     * Remove the specified RFID tag.
     */
    public function destroy(RfidTag $rfidTag): JsonResponse
    {
        $rfidTag->delete();

        return response()->json([
            'success' => true,
            'message' => 'RFID tag deleted successfully.',
            'data'    => null,
        ]);
    }
}
