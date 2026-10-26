<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoanProductRequest;
use App\Http\Requests\SimulationRequest;
use App\Models\LoanProduct;
use App\Services\Credit\LoanProductService;
use App\Services\Credit\LoanSimulationService;
use App\Support\ProductCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LoanProductController extends Controller
{
    public function __construct(
        private readonly LoanProductService $products,
        private readonly LoanSimulationService $simulation,
    ) {}

    public function index(): View
    {
        return view('admin.products.index', [
            'products' => LoanProduct::with('currentVersion')->withCount(['loans', 'applications', 'versions'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', ['product' => null, 'version' => null]);
    }

    public function store(LoanProductRequest $request): RedirectResponse
    {
        $product = $this->products->create($request->validated(), $request->user());

        return redirect()->route('admin.products.show', $product)->with('success', 'Produto criado (versão 1 das condições).');
    }

    public function show(LoanProduct $product): View
    {
        $product->load('currentVersion', 'versions');

        return view('admin.products.show', [
            'product' => $product,
            'catalog' => $product->currentVersion ? ProductCatalog::simulatorData(collect([$product])) : [],
            'usage' => $product->versions->mapWithKeys(fn ($v) => [$v->id => [
                'applications' => $v->applications()->count(),
                'loans' => \App\Models\Loan::where('loan_product_version_id', $v->id)->count(),
            ]]),
        ]);
    }

    public function edit(LoanProduct $product): View
    {
        return view('admin.products.form', ['product' => $product, 'version' => $product->currentVersion]);
    }

    public function update(LoanProductRequest $request, LoanProduct $product): RedirectResponse
    {
        $result = $this->products->update($product, $request->validated(), $request->user());

        return redirect()->route('admin.products.show', $product)->with('success', $result['new_version']
            ? 'Produto actualizado. Foi criada uma nova versão das condições; os contratos existentes mantêm as condições anteriores.'
            : 'Produto actualizado (condições financeiras inalteradas).');
    }

    public function toggle(LoanProduct $product): RedirectResponse
    {
        $product->update(['status' => $product->status === 'active' ? 'inactive' : 'active']);

        return back()->with('success', $product->status === 'active' ? 'Produto activado.' : 'Produto desactivado (deixa de aceitar novos pedidos).');
    }

    /** Simulação interna (também para produtos inactivos). */
    public function simulate(SimulationRequest $request): JsonResponse
    {
        $product = LoanProduct::with('currentVersion.product')->findOrFail($request->integer('product_id'));
        abort_unless($product->currentVersion, 422, 'Produto sem condições definidas.');
        $result = $this->simulation->simulate($product->currentVersion, (string) $request->input('amount'), $request->integer('term'),
            $request->input('frequency'), $request->input('method'));

        return response()->json($this->simulation->toJson($result));
    }
}
