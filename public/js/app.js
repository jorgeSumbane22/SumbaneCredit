/* ==========================================================================
   SumbaneCredit — JavaScript da aplicação (sem frameworks)
   ========================================================================== */
(function () {
    'use strict';

    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    /* ---------- Pedidos assíncronos (Fetch API) ---------- */
    window.scFetch = async function (url, options = {}) {
        const opts = Object.assign({ method: 'GET', headers: {} }, options);
        opts.headers = Object.assign({
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrf,
        }, opts.headers);
        if (opts.body && !(opts.body instanceof FormData) && typeof opts.body !== 'string') {
            opts.headers['Content-Type'] = 'application/json';
            opts.body = JSON.stringify(opts.body);
        }
        const response = await fetch(url, opts);
        let data = null;
        try { data = await response.json(); } catch (e) { data = null; }
        if (!response.ok) {
            let message = (data && data.message) || 'Ocorreu um erro. Tente novamente.';
            if (data && data.errors) {
                message = Object.values(data.errors).flat().join('\n');
            }
            const error = new Error(message);
            error.status = response.status;
            error.data = data;
            throw error;
        }
        return data;
    };

    /* ---------- Notificações (toast) ---------- */
    const Toast = window.Swal ? Swal.mixin({
        toast: true, position: 'top-end', showConfirmButton: false, timer: 4500, timerProgressBar: true,
    }) : null;
    window.scToast = function (icon, title) {
        if (Toast) { Toast.fire({ icon, title }); } else { alert(title); }
    };

    document.querySelectorAll('[data-flash]').forEach(function (el) {
        const map = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
        scToast(map[el.dataset.flash] || 'info', el.dataset.message);
    });

    /* ---------- Confirmações (SweetAlert2) ---------- */
    document.addEventListener('submit', function (event) {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;

        if (form.dataset.confirm && !form.dataset.confirmed) {
            event.preventDefault();
            const needsReason = form.dataset.reason !== undefined;
            Swal.fire({
                title: form.dataset.confirmTitle || 'Confirmar operação',
                text: form.dataset.confirm,
                icon: form.dataset.confirmIcon || 'question',
                input: needsReason ? 'textarea' : undefined,
                inputLabel: needsReason ? (form.dataset.reasonLabel || 'Motivo') : undefined,
                inputPlaceholder: needsReason ? 'Descreva o motivo…' : undefined,
                inputValidator: needsReason ? (v) => (!v || v.trim().length < 5) ? 'Indique um motivo (mín. 5 caracteres).' : undefined : undefined,
                showCancelButton: true,
                confirmButtonText: form.dataset.confirmButton || 'Confirmar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: form.dataset.confirmColor || '#159A74',
                cancelButtonColor: '#6b7280',
                reverseButtons: true,
            }).then(function (result) {
                if (!result.isConfirmed) return;
                if (needsReason) {
                    let input = form.querySelector('input[name="' + (form.dataset.reason || 'reason') + '"]');
                    if (!input) {
                        input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = form.dataset.reason || 'reason';
                        form.appendChild(input);
                    }
                    input.value = result.value;
                }
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') { form.requestSubmit(); } else { form.submit(); }
            });
            return;
        }

        // Estado de carregamento e protecção contra duplo envio.
        if (form.dataset.noLoading === undefined) {
            if (form.dataset.submitting) { event.preventDefault(); return; }
            form.dataset.submitting = '1';
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
                btn.disabled = true;
                if (btn.tagName === 'BUTTON' && !btn.querySelector('.spinner-border')) {
                    btn.insertAdjacentHTML('afterbegin', '<span class="spinner-border me-1" role="status" aria-hidden="true"></span>');
                }
            });
            // Os downloads não recarregam a página: reactivar após alguns segundos.
            if (form.target === '_blank' || form.dataset.download !== undefined) {
                setTimeout(function () { resetForm(form); }, 2500);
            }
        }
    }, true);

    function resetForm(form) {
        delete form.dataset.submitting;
        delete form.dataset.confirmed;
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.disabled = false;
            btn.querySelector('.spinner-border')?.remove();
        });
    }
    window.addEventListener('pageshow', function () { document.querySelectorAll('form[data-submitting]').forEach(resetForm); });

    /* ---------- Sidebar móvel ---------- */
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.querySelector('.sidebar-backdrop');
    document.querySelectorAll('[data-toggle-sidebar]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            sidebar?.classList.toggle('show');
            backdrop?.classList.toggle('show');
        });
    });
    backdrop?.addEventListener('click', function () { sidebar?.classList.remove('show'); backdrop.classList.remove('show'); });

    /* ---------- Tooltips ---------- */
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });

    /* ---------- Linhas dinâmicas (fiadores, garantias, encargos, documentos) ---------- */
    document.querySelectorAll('[data-repeater]').forEach(function (wrap) {
        const template = wrap.querySelector('template');
        const list = wrap.querySelector('[data-repeater-list]');
        const max = parseInt(wrap.dataset.max || '10', 10);
        let index = list.querySelectorAll('[data-repeater-item]').length;
        wrap.querySelector('[data-repeater-add]')?.addEventListener('click', function () {
            if (list.querySelectorAll('[data-repeater-item]').length >= max) {
                scToast('warning', 'Número máximo de linhas atingido.');
                return;
            }
            const html = template.innerHTML.replace(/__INDEX__/g, String(index++));
            list.insertAdjacentHTML('beforeend', html);
        });
        list.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-repeater-remove]');
            if (btn) btn.closest('[data-repeater-item]').remove();
        });
    });

    /* ---------- Gráficos (Chart.js) ---------- */
    if (window.Chart) {
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = '#6b7280';
        Chart.defaults.plugins.legend.labels.boxWidth = 12;
        const moneyFmt = new Intl.NumberFormat('pt-PT', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
        document.querySelectorAll('canvas[data-chart]').forEach(function (canvas) {
            try {
                const config = JSON.parse(canvas.dataset.chart);
                if (canvas.dataset.money !== undefined) {
                    config.options = config.options || {};
                    config.options.plugins = config.options.plugins || {};
                    config.options.plugins.tooltip = {
                        callbacks: {
                            label: function (ctx) {
                                const v = ctx.parsed.y !== undefined ? ctx.parsed.y : ctx.parsed;
                                return (ctx.dataset.label ? ctx.dataset.label + ': ' : (ctx.label ? ctx.label + ': ' : '')) + moneyFmt.format(v) + ' MZN';
                            },
                        },
                    };
                    if (config.type !== 'doughnut' && config.type !== 'pie') {
                        config.options.scales = config.options.scales || {};
                        config.options.scales.y = Object.assign({ beginAtZero: true, ticks: { callback: (v) => moneyFmt.format(v) } }, config.options.scales.y || {});
                    }
                }
                config.options = Object.assign({ responsive: true, maintainAspectRatio: false }, config.options || {});
                new Chart(canvas, config);
            } catch (e) {
                console.error('Gráfico inválido', e);
            }
        });
    }

    /* ---------- Simulador de crédito (site público, portal e painel) ---------- */
    document.querySelectorAll('form[data-simulator]').forEach(function (form) {
        const result = document.querySelector(form.dataset.simulator);
        const productSelect = form.querySelector('[name="product_id"], [name="loan_product_id"]');
        const products = JSON.parse(form.dataset.products || '{}');

        function applyProductLimits() {
            if (!productSelect) return;
            const p = products[productSelect.value];
            if (!p) return;
            const amount = form.querySelector('[name="amount"]');
            const term = form.querySelector('[name="term"]');
            if (amount) { amount.min = p.min_amount; amount.max = p.max_amount; amount.placeholder = p.min_amount_label + ' – ' + p.max_amount_label; }
            if (term) { term.min = p.min_term; term.max = p.max_term; term.placeholder = p.min_term + ' – ' + p.max_term; }
            fillSelect(form.querySelector('[name="frequency"]'), p.frequencies);
            fillSelect(form.querySelector('[name="method"]'), p.methods);
            const info = form.querySelector('[data-product-info]');
            if (info) info.innerHTML = '<i class="bi bi-info-circle me-1"></i>Taxa: <strong>' + p.rate + '</strong> · Montante: ' + p.min_amount_label + ' a ' + p.max_amount_label + ' · ' + p.min_term + ' a ' + p.max_term + ' prestações';
        }
        function fillSelect(select, options) {
            if (!select || !options) return;
            const current = select.value;
            select.innerHTML = '';
            Object.entries(options).forEach(function ([value, label]) {
                const opt = document.createElement('option');
                opt.value = value; opt.textContent = label;
                if (value === current) opt.selected = true;
                select.appendChild(opt);
            });
            select.closest('.sim-optional')?.classList.toggle('d-none', Object.keys(options).length < 2);
        }
        productSelect?.addEventListener('change', applyProductLimits);
        applyProductLimits();

        const nativeSubmit = form.dataset.submitMode === 'native';
        async function simulate(e) {
            if (e) e.preventDefault();
            if (nativeSubmit) {
                const fields = ['amount', 'term'].map((n) => form.querySelector('[name="' + n + '"]')).filter(Boolean);
                if (!fields.every((f) => f.reportValidity())) return;
            } else if (!form.reportValidity()) return;
            const card = result?.closest('.card') || result;
            card?.classList.add('is-loading');
            try {
                let body = new FormData(form);
                if (nativeSubmit) {
                    body = new FormData();
                    ['loan_product_id', 'product_id', 'amount', 'term', 'frequency', 'method'].forEach(function (n) {
                        const el = form.querySelector('[name="' + n + '"]');
                        if (el) body.append(n, el.value);
                    });
                }
                const data = await scFetch(form.dataset.url, { method: 'POST', body: body });
                renderSimulation(result, data);
                document.dispatchEvent(new CustomEvent('sc:simulated', { detail: data }));
            } catch (err) {
                result.innerHTML = '<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle me-1"></i>' + escapeHtml(err.message) + '</div>';
                document.dispatchEvent(new CustomEvent('sc:simulation-failed'));
            } finally {
                card?.classList.remove('is-loading');
            }
        }
        if (!nativeSubmit) form.addEventListener('submit', simulate);
        if (nativeSubmit) {
            // O pedido só pode ser submetido depois de uma simulação válida com os valores actuais.
            const submitBtn = form.querySelector('[data-requires-simulation]');
            const lock = () => { if (submitBtn) submitBtn.disabled = true; };
            document.addEventListener('sc:simulated', () => { if (submitBtn) submitBtn.disabled = false; });
            document.addEventListener('sc:simulation-failed', lock);
            ['amount', 'term', 'frequency', 'method', 'loan_product_id', 'product_id'].forEach((n) => form.querySelector('[name="' + n + '"]')?.addEventListener('change', lock));
            lock();
        }
        form.querySelectorAll('[data-simulate-trigger]').forEach((btn) => btn.addEventListener('click', simulate));
        if (form.dataset.autorun !== undefined) simulate();
    });

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function renderSimulation(container, d) {
        if (!container) return;
        const rows = d.rows.map((r) => '<tr><td>' + r.number + '</td><td>' + r.due_date + '</td><td class="num">' + r.principal + '</td><td class="num">' + r.interest + '</td><td class="num">' + r.fee + '</td><td class="num fw-semibold">' + r.total + '</td><td class="num">' + r.closing_balance + '</td></tr>').join('');
        const fees = d.fees.length ? d.fees.map((f) => '<div class="sim-row"><span>' + escapeHtml(f.name) + ' <small class="text-muted">(' + f.charge + ')</small></span><span>' + f.amount + '</span></div>').join('') : '<div class="sim-row"><span>Encargos</span><span>Sem encargos</span></div>';
        container.innerHTML =
            '<div class="sim-result">' +
            '<div class="small-label">Prestação estimada (' + escapeHtml(d.conditions.frequency_label.toLowerCase()) + ')</div>' +
            '<div class="sim-main mb-2">' + d.installment + '</div>' +
            '<div class="sim-row"><span>Montante solicitado</span><span>' + formatAmount(d.conditions.amount) + '</span></div>' +
            '<div class="sim-row"><span>Número de prestações</span><span>' + d.conditions.term + '</span></div>' +
            '<div class="sim-row"><span>Taxa de juro</span><span>' + escapeHtml(d.conditions.rate_label) + '</span></div>' +
            '<div class="sim-row"><span>Método</span><span>' + escapeHtml(d.conditions.method_label) + '</span></div>' +
            '<div class="sim-row"><span>Juros estimados</span><span>' + d.total_interest + '</span></div>' + fees +
            '<div class="sim-row"><span>Valor líquido a receber</span><span>' + d.net_disbursed + '</span></div>' +
            '<div class="sim-row"><span>Total estimado a pagar</span><span class="text-navy">' + d.total_payable + '</span></div>' +
            '<div class="sim-row"><span>1.ª prestação / última</span><span>' + d.first_due_date + ' / ' + d.maturity_date + '</span></div>' +
            '<div class="alert alert-warning small mt-3 mb-2"><i class="bi bi-info-circle me-1"></i>' + escapeHtml(d.disclaimer) + '</div>' +
            '<button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#sim-schedule"><i class="bi bi-table me-1"></i>Calendário de amortização</button>' +
            '<div class="collapse mt-3" id="sim-schedule"><div class="table-responsive"><table class="table table-sm table-striped"><thead><tr><th>N.º</th><th>Vencimento</th><th class="num">Capital</th><th class="num">Juros</th><th class="num">Encargos</th><th class="num">Prestação</th><th class="num">Saldo</th></tr></thead><tbody>' + rows + '</tbody></table></div></div>' +
            '</div>';
    }
    function formatAmount(v) {
        const n = Number(v);
        return n.toLocaleString('pt-PT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).replace(/\s/g, '.') + ' MZN';
    }

    /* ---------- Formulários com campos dependentes ---------- */
    document.querySelectorAll('[data-show-when]').forEach(function (el) {
        const [name, value] = el.dataset.showWhen.split('=');
        const inputs = document.querySelectorAll('[name="' + name + '"]');
        function update() {
            let current = '';
            inputs.forEach((i) => { if ((i.type !== 'radio' && i.type !== 'checkbox') || i.checked) current = i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value; });
            el.classList.toggle('d-none', !value.split('|').includes(current));
        }
        inputs.forEach((i) => i.addEventListener('change', update));
        update();
    });
})();
