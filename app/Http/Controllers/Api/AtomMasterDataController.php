<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AtomProductActivityGroup;
use App\Models\AtomProductCategory;
use Illuminate\Http\JsonResponse;

class AtomMasterDataController extends Controller
{
    public function categories(): JsonResponse
    {
        $categories = AtomProductCategory::query()
            ->with(['subCategories' => fn ($query) => $query->orderBy('sequence_number')->orderBy('name')])
            ->orderBy('sequence_number')->orderBy('name')->get();

        return response()->json(['status' => true, 'data' => $categories]);
    }

    public function activities(): JsonResponse
    {
        $groups = AtomProductActivityGroup::query()
            ->with(['activities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('sequence_number')->orderBy('name')->get();

        return response()->json(['status' => true, 'data' => $groups]);
    }
}
