<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\SimulationRequest;
use App\Models\LoanProduct;
use App\Services\Credit\LoanSimulationService;
use App\Support\ProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class SimulatorController extends Controller
{
    public function __construct(private readonly LoanSimulationService $simulation) {}

    public function show(): View
    {
        $products = LoanProduct::published()->with('currentVersion')->orderBy('name')->get();

        return view('public.simulator', [
            'products' => $products,
            'catalog' => ProductCatalog::simulatorData($products),
            'selected' => request('produto'),
        ]);
    }

    /** Simulação pública: apenas produtos activos e publicados, com o mesmo motor financeiro. */
    public function calculate(SimulationRequest $request): JsonResponse
    {
        $product = LoanProduct::published()->with('currentVersion.product')->findOrFail($request->integer('product_id'));
        $result = $this->simulation->simulate($product->currentVersion, (string) $request->input('amount'), $request->integer('term'),
            $request->input('frequency'), $request->input('method'));

        return response()->json($this->simulation->toJson($result));
    }
}
