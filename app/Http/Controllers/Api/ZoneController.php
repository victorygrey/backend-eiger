<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreZoneRequest;
use App\Http\Requests\UpdateZoneRequest;
use App\Http\Resources\ZoneResource;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ZoneController extends Controller
{
    /**
     * Display a list of all zones with product count.
     */
    public function index(): AnonymousResourceCollection
    {
        $zones = Zone::withCount('products')->get();

        return ZoneResource::collection($zones);
    }

    /**
     * Store a newly created zone.
     */
    public function store(StoreZoneRequest $request): JsonResponse
    {
        $zone = Zone::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Zone created successfully.',
            'data'    => new ZoneResource($zone),
        ], 201);
    }

    /**
     * Display the specified zone.
     */
    public function show(Zone $zone): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Zone retrieved successfully.',
            'data'    => new ZoneResource($zone->loadCount('products')),
        ]);
    }

    /**
     * Update the specified zone.
     */
    public function update(UpdateZoneRequest $request, Zone $zone): JsonResponse
    {
        $zone->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Zone updated successfully.',
            'data'    => new ZoneResource($zone->fresh()->loadCount('products')),
        ]);
    }

    /**
     * Remove the specified zone.
     */
    public function destroy(Zone $zone): JsonResponse
    {
        $zone->delete();

        return response()->json([
            'success' => true,
            'message' => 'Zone deleted successfully.',
            'data'    => null,
        ]);
    }
}
