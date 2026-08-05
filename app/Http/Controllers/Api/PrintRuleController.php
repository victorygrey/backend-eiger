<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePrintRuleRequest;
use App\Http\Resources\PrintRuleResource;
use App\Models\PrintRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PrintRuleController extends Controller
{
    /**
     * Display a list of all print rules.
     */
    public function index(): AnonymousResourceCollection
    {
        $rules = PrintRule::all();

        return PrintRuleResource::collection($rules);
    }

    /**
     * Update the specified print rule.
     */
    public function update(UpdatePrintRuleRequest $request, PrintRule $printRule): JsonResponse
    {
        $printRule->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Print rule updated successfully.',
            'data'    => new PrintRuleResource($printRule->fresh()),
        ]);
    }
}
