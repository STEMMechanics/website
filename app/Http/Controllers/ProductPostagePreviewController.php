<?php

namespace App\Http\Controllers;

use App\Services\StoreShippingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductPostagePreviewController extends Controller
{
    public function __invoke(Request $request, StoreShippingService $shipping): JsonResponse
    {
        $measurements = $request->validate([
            'length_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'width_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'height_mm' => ['required', 'integer', 'min:1', 'max:10000'],
            'weight_grams' => ['required', 'integer', 'min:1', 'max:100000000'],
            'box_only' => ['sometimes', 'boolean'],
        ]);

        return response()->json(['options' => $shipping->previewPackedItem($measurements)]);
    }
}
