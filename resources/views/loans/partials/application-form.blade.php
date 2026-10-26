{{-- Formulário de pedido de crédito (portal do cliente e balcão). Parâmetros: $client, $products, $catalog, $selected, $documents, $action, $simulateUrl --}}
@php
    $emp = $client->employment;
    $first = $products->firstWhere('id', (int) ($selected ?? 0)) ?? $products->first();
    $fv = $first?->currentVersion;
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" data-simulator="#app_sim_result" data-submit-mode="native"
      data-url="{{ $simulateUrl }}" data-products="{{ json_encode($catalog) }}" novalidate>
    @csrf
    <div class="row g-4">
        <div class="col-xl-7">
            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title"><span class="badge bg-navy me-2">1</span>Crédito pretendido</h2></div>
                <div class="card-body">
                    <x-select name="loan_product_id" label="Produto" :options="$products->pluck('name', 'id')->all()" :value="$first?->id" required />
                    <div class="small text-muted mb-3" data-product-info></div>
                    <div class="row">
                        <x-input name="amount" type="number" step="0.01" label="Montante (MZN)" required col="col-md-6 mb-3" :value="$fv ? (int) $fv->min_amount : null" />
                        <x-input name="term" type="number" label="Número de prestações" required col="col-md-6 mb-3" :value="$fv?->min_term" />
                        <div class="col-md-6 sim-optional"><x-select name="frequency" label="Periodicidade" :options="$fv ? collect($fv->frequencies())->mapWithKeys(fn ($f) => [$f->value => $f->label()])->all() : []" required /></div>
                        <div class="col-md-6 sim-optional"><x-select name="method" label="Método de amortização" :options="$fv ? collect($fv->methods())->mapWithKeys(fn ($m) => [$m->value => $m->shortLabel()])->all() : []" required /></div>
                    </div>
                    <button type="button" class="btn btn-outline-success" data-simulate-trigger><i class="bi bi-calculator me-1"></i>Simular condições</button>
                    <x-textarea name="purpose" label="Finalidade do crédito" required rows="3" placeholder="Ex.: compra de stock para a banca do mercado, pagamento de propinas…" col="mt-3" />
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title"><span class="badge bg-navy me-2">2</span>Situação financeira e profissional</h2></div>
                <div class="card-body">
                    <div class="row">
                        <x-select name="employment_type" label="Situação profissional" :options="\App\Models\Client::EMPLOYMENT_TYPES" :value="$emp?->employment_type" placeholder="Seleccione" required col="col-md-6 mb-3" />
                        <x-input name="occupation" label="Profissão / actividade" :value="$client->occupation" col="col-md-6 mb-3" />
                        <x-input name="employer_name" label="Entidade empregadora / negócio" :value="$emp?->employer_name" col="col-md-12 mb-3" />
                        <x-input name="monthly_income" type="number" step="0.01" min="0" label="Rendimento mensal líquido (MZN)" :value="$emp?->monthly_income ?? $client->monthly_income" required col="col-md-6 mb-3" />
                        <x-input name="other_income" type="number" step="0.01" min="0" label="Outros rendimentos mensais (MZN)" :value="$emp?->other_income ?? 0" col="col-md-6 mb-3" />
                        <x-input name="monthly_expenses" type="number" step="0.01" min="0" label="Despesas mensais do agregado (MZN)" :value="$emp?->monthly_expenses ?? 0" col="col-md-6 mb-3" />
                        <x-input name="existing_obligations" type="number" step="0.01" min="0" label="Prestações de outros créditos (MZN/mês)" value="0" col="col-md-6 mb-3" />
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title"><span class="badge bg-navy me-2">3</span>Fiadores e garantias <small class="text-muted fw-normal">(quando aplicável)</small></h2></div>
                <div class="card-body">
                    <div data-repeater data-max="3">
                        <div class="d-flex justify-content-between align-items-center mb-2"><strong class="small">Fiadores</strong><button type="button" class="btn btn-sm btn-outline-primary" data-repeater-add><i class="bi bi-plus"></i> Adicionar fiador</button></div>
                        <div data-repeater-list>
                            @foreach (old('guarantors', []) as $i => $g)
                                @include('loans.partials.guarantor-row', ['i' => $i, 'g' => $g])
                            @endforeach
                        </div>
                        <template>@include('loans.partials.guarantor-row', ['i' => '__INDEX__', 'g' => []])</template>
                    </div>
                    <hr>
                    <div data-repeater data-max="5">
                        <div class="d-flex justify-content-between align-items-center mb-2"><strong class="small">Garantias</strong><button type="button" class="btn btn-sm btn-outline-primary" data-repeater-add><i class="bi bi-plus"></i> Adicionar garantia</button></div>
                        <div data-repeater-list>
                            @foreach (old('collaterals', []) as $i => $c)
                                @include('loans.partials.collateral-row', ['i' => $i, 'c' => $c])
                            @endforeach
                        </div>
                        <template>@include('loans.partials.collateral-row', ['i' => '__INDEX__', 'c' => []])</template>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h2 class="card-title"><span class="badge bg-navy me-2">4</span>Documentos</h2></div>
                <div class="card-body">
                    @if ($documents->isNotEmpty())
                        <div class="small-label mb-2">Associar documentos já carregados</div>
                        @foreach ($documents as $doc)
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="document_ids[]" value="{{ $doc->id }}" id="doc{{ $doc->id }}" @checked(in_array($doc->id, old('document_ids', $documents->pluck('id')->all())))>
                                <label class="form-check-label small" for="doc{{ $doc->id }}">{{ $doc->typeLabel() }} — {{ \Illuminate\Support\Str::limit($doc->original_name, 40) }} <x-badge :label="$doc->statusLabel()" :color="$doc->statusColor()" /></label>
                            </div>
                        @endforeach
                        <hr>
                    @endif
                    <div data-repeater data-max="6">
                        <div class="d-flex justify-content-between align-items-center mb-2"><strong class="small">Carregar novos documentos</strong><button type="button" class="btn btn-sm btn-outline-primary" data-repeater-add><i class="bi bi-plus"></i> Adicionar ficheiro</button></div>
                        <div data-repeater-list>@include('loans.partials.document-row', ['i' => 0])</div>
                        <template>@include('loans.partials.document-row', ['i' => '__INDEX__'])</template>
                    </div>
                    <div class="form-text">PDF, JPG, PNG ou WEBP. Os ficheiros ficam guardados de forma privada.</div>
                </div>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="position-sticky" style="top:80px">
                <div class="card mb-4 position-relative">
                    <div class="loading-overlay"><div class="spinner-border text-success" role="status"></div></div>
                    <div class="card-header"><h2 class="card-title">Simulação</h2></div>
                    <div class="card-body" id="app_sim_result">
                        <x-empty-state icon="calculator" title="Simule antes de submeter" text="Carregue em «Simular condições» para ver a prestação, os juros, os encargos e o calendário." />
                    </div>
                </div>
                <div class="card">
                    <div class="card-header"><h2 class="card-title"><span class="badge bg-navy me-2">5</span>Declarações e consentimentos</h2></div>
                    <div class="card-body">
                        @foreach ([
                            'declaration' => 'Declaro, sob compromisso de honra, que as informações prestadas são verdadeiras e completas.',
                            'consent_data_processing' => 'Autorizo o tratamento dos meus dados pessoais para análise e gestão do pedido de crédito.',
                            'consent_credit_check' => 'Autorizo a análise da minha capacidade de pagamento, incluindo o histórico interno na instituição.',
                        ] as $name => $text)
                            <div class="form-check mb-2">
                                <input class="form-check-input @error($name) is-invalid @enderror" type="checkbox" name="{{ $name }}" id="{{ $name }}" value="1" @checked(old($name)) required>
                                <label class="form-check-label small" for="{{ $name }}">{{ $text }}</label>
                            </div>
                        @endforeach
                        <button class="btn btn-success w-100 mt-3 py-2" type="submit" data-requires-simulation><i class="bi bi-send me-1"></i>Submeter pedido</button>
                        <div class="form-text text-center">É necessário simular com os valores actuais antes de submeter.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
