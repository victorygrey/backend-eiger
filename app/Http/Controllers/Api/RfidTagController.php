<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRfidTagRequest;
use App\Http\Requests\UpdateRfidTagRequest;
use App\Http\Resources\RfidTagResource;
use App\Models\RfidTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RfidTagController extends Controller
{
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
