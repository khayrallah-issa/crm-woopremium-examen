/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/js/dealer_delete.js
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Frontend-script voor US-09 (Dealer verwijderen). Dit script:
 *    1. Zoekt knoppen met het attribuut data-dealer-delete op de pagina.
 *    2. Bij klik laat het een bevestigings-pop-up (modal) zien.
 *    3. Bij bevestiging stuurt het een DELETE-request naar /api/dealers/{id}.
 *    4. Bij succes brengt het de marketeer terug naar de dealerlijst.
 *
 *  Hoe gebruik je het in de bestaande dealerpagina?
 *    <button data-dealer-delete
 *            data-dealer-id="42"
 *            data-dealer-name="De Wit Interieurs"
 *            data-dealer-email="joost@dewit.nl">
 *      Verwijder dealer
 *    </button>
 *  Voeg dit script en de bijhorende CSS (crm_extensions.css) toe aan de
 *  dealerpagina.
 * ============================================================================
 */

(function () {
    'use strict';

    // De basis-URL waar onze API onder draait.
    // Aanpasbaar als de map ergens anders staat.
    const API = '/crm-extensions/public/api/index.php?route=';

    /**
     * Toont een modaal venster met de naam en het e-mailadres van de dealer
     * en twee knoppen: Annuleren / Ja, verwijder dealer.
     * onConfirm wordt aangeroepen als de gebruiker bevestigt.
     */
    function showConfirmDialog(dealer, onConfirm) {
        // Verwijder een eventuele oude dialoog die nog op de pagina staat,
        // anders krijgen we dubbele overlays.
        document.getElementById('crm-delete-dialog')?.remove();

        const overlay = document.createElement('div');
        overlay.id = 'crm-delete-dialog';
        overlay.className = 'crm-modal-overlay';
        overlay.innerHTML = `
            <div class="crm-modal" role="dialog" aria-labelledby="crm-dlg-title">
                <h2 id="crm-dlg-title">Dealer verwijderen?</h2>
                <p>Weet je zeker dat je deze dealer wilt verwijderen?</p>
                <div class="crm-modal-card">
                    <strong>${escapeHtml(dealer.name)}</strong><br>
                    <span>${escapeHtml(dealer.email || '')}</span>
                </div>
                <p class="crm-modal-note">De dealer wordt 30 dagen bewaard in de prullenbak.</p>
                <div class="crm-modal-buttons">
                    <button type="button" data-action="cancel">Annuleren</button>
                    <button type="button" data-action="confirm" class="crm-danger">Ja, verwijder dealer</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        // Annuleren-knop sluit gewoon de dialoog.
        overlay.querySelector('[data-action="cancel"]').addEventListener('click', closeDialog);

        // Bevestigen-knop: roep onConfirm() aan, maak knop disabled zodat
        // dubbel-klikken niet leidt tot dubbel verwijderen.
        overlay.querySelector('[data-action="confirm"]').addEventListener('click', async (e) => {
            e.target.disabled = true;
            try { await onConfirm(); } finally { closeDialog(); }
        });

        // Escape-toets sluit de dialoog ook (toegankelijkheid).
        document.addEventListener('keydown', escClose);

        function closeDialog() {
            overlay.remove();
            document.removeEventListener('keydown', escClose);
        }
        function escClose(ev) { if (ev.key === 'Escape') closeDialog(); }
    }

    /**
     * Stuurt een DELETE-request naar de API. Bij succes brengt het de
     * gebruiker terug naar de hoofdlijst. Bij een fout een alert().
     */
    async function deleteDealer(id) {
        const res = await fetch(`${API}/dealers/${id}`, { method: 'DELETE' });
        if (!res.ok) {
            const err = await res.json().catch(() => ({}));
            alert('Verwijderen mislukt: ' + (err.error || res.statusText));
            return;
        }
        // Stuur de marketeer terug naar de dealerlijst.
        window.location.href = '/wp-admin/admin.php?page=crm-dealers';
    }

    /**
     * Beveiligt user input tegen HTML/XSS bij het tonen in de dialoog.
     * Bijv. een dealer-naam met < > & wordt netjes geescaped.
     */
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    /**
     * Event-delegatie: we luisteren naar elke klik op de pagina en kijken
     * of er op een knop met data-dealer-delete is geklikt. Dat is robuuster
     * dan elke knop apart binden, omdat het ook werkt voor knoppen die
     * later door de pagina worden toegevoegd.
     */
    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-dealer-delete]');
        if (!btn) return;
        ev.preventDefault();
        const dealer = {
            id:    Number(btn.dataset.dealerId),
            name:  btn.dataset.dealerName  || 'deze dealer',
            email: btn.dataset.dealerEmail || '',
        };
        showConfirmDialog(dealer, () => deleteDealer(dealer.id));
    });
})();
