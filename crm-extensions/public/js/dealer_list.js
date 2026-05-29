/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/js/dealer_list.js
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Frontend voor US-14 (dealerlijst filteren). Dit script filtert de
 *  dealertabel direct in de browser - zonder de pagina te herladen:
 *    - een zoekveld dat op naam filtert;
 *    - een keuzelijst die op status filtert.
 *  De teller bovenaan toont hoeveel dealers er zichtbaar zijn.
 *
 *  Verwachte HTML:
 *    <input id="crm-search">  <select id="crm-status-filter">
 *    <span id="crm-count"></span>
 *    <tr data-name="..." data-status="...">  (per dealer)
 * ============================================================================
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        const search    = document.getElementById('crm-search');
        const statusSel = document.getElementById('crm-status-filter');
        const counter   = document.getElementById('crm-count');
        const rows      = Array.prototype.slice.call(
            document.querySelectorAll('tr[data-name]')
        );
        if (!search || rows.length === 0) return;

        /**
         * Auteur: Khayrallah Issa
         * Loopt alle rijen langs en toont/verbergt ze op basis van de
         * ingevulde zoekterm en de gekozen status.
         */
        function applyFilter() {
            const term   = (search.value || '').trim().toLowerCase();
            const status = statusSel ? statusSel.value : '';
            let zichtbaar = 0;

            rows.forEach(function (row) {
                let toon = true;
                if (term && row.dataset.name.indexOf(term) === -1) {
                    toon = false;
                }
                if (status && row.dataset.status !== status) {
                    toon = false;
                }
                row.style.display = toon ? '' : 'none';
                if (toon) {
                    zichtbaar++;
                }
            });

            if (counter) {
                counter.textContent = zichtbaar + ' van ' + rows.length + ' dealers';
            }
        }

        search.addEventListener('input', applyFilter);
        if (statusSel) {
            statusSel.addEventListener('change', applyFilter);
        }
        applyFilter();   // begintelling zetten
    });
})();
