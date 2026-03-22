/**
 * FP Discount Gift — Admin scripts
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const checkAll = document.querySelector('.fpdgift-checkall');
        if (checkAll) {
            checkAll.addEventListener('change', function () {
                document.querySelectorAll('input[name="rule_ids[]"]').forEach(function (el) {
                    el.checked = checkAll.checked;
                });
            });
        }

        const form = document.getElementById('fpdgift-rule-form');
        const previewBtn = document.getElementById('fpdgift-preview-btn');
        const previewSubtotal = document.getElementById('fpdgift-preview-subtotal');
        const previewResult = document.getElementById('fpdgift-preview-result');

        if (form && previewBtn && previewSubtotal && previewResult) {
            const ruleId = form.getAttribute('data-rule-id');
            if (ruleId) {
                previewBtn.addEventListener('click', function () {
                    const subtotal = parseFloat(previewSubtotal.value) || 0;
                    if (subtotal <= 0) {
                        previewResult.textContent = (window.fpDiscountGiftAdmin?.i18n?.importoCarrello || 'Importo carrello') + ' richiesto.';
                        previewResult.hidden = false;
                        previewResult.style.color = 'var(--fpdms-danger, #ef4444)';
                        return;
                    }

                    const cfg = window.fpDiscountGiftAdmin || {};
                    const xhr = new XMLHttpRequest();
                    const fd = new FormData();
                    fd.append('action', 'fp_discountgift_preview');
                    fd.append('nonce', cfg.nonce || '');
                    fd.append('rule_id', ruleId);
                    fd.append('subtotal', String(subtotal));

                    xhr.open('POST', cfg.ajaxUrl || '/wp-admin/admin-ajax.php');
                    xhr.onload = function () {
                        try {
                            const r = JSON.parse(xhr.responseText);
                            if (r.success && r.data) {
                                const d = r.data.discount;
                                const t = r.data.total;
                                const sconto = cfg.i18n?.sconto || 'Sconto';
                                const totale = cfg.i18n?.totale || 'Totale';
                                const fmt = function (n) { return Number(n).toFixed(2).replace('.', ','); };
                                previewResult.innerHTML = sconto + ': <strong>' + fmt(d) + ' €</strong> — ' + totale + ': <strong>' + fmt(t) + ' €</strong>';
                                previewResult.style.color = '';
                                previewResult.hidden = false;
                            } else {
                                previewResult.textContent = 'Errore nel calcolo.';
                                previewResult.style.color = 'var(--fpdms-danger, #ef4444)';
                                previewResult.hidden = false;
                            }
                        } catch (e) {
                            previewResult.textContent = 'Errore di risposta.';
                            previewResult.style.color = 'var(--fpdms-danger, #ef4444)';
                            previewResult.hidden = false;
                        }
                    };
                    xhr.onerror = function () {
                        previewResult.textContent = 'Errore di rete.';
                        previewResult.style.color = 'var(--fpdms-danger, #ef4444)';
                        previewResult.hidden = false;
                    };
                    xhr.send(fd);
                });
            }
        }
    });
})();
