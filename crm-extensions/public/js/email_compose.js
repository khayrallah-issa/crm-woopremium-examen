/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/js/email_compose.js
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Frontend voor US-05 (E-mail versturen). Dit script:
 *    1. Zoekt knoppen met het attribuut data-email-compose.
 *    2. Bij klik opent een modaal venster met onderwerp + bericht.
 *    3. Bij Verzenden: POST naar /api/emails met dealer_id, subject, body.
 *    4. Bij succes: pagina vernieuwen zodat de nieuwe mail in de
 *       geschiedenis verschijnt (US-07).
 *    5. Bij sluiten met tekst: vraagt of we de inhoud willen weggooien
 *       (concept-bescherming).
 *
 *  Hoe te gebruiken op een dealerpagina:
 *    <button data-email-compose
 *            data-dealer-id="42"
 *            data-dealer-name="De Wit Interieurs"
 *            data-dealer-email="joost@dewit.nl">
 *      Stuur e-mail
 *    </button>
 * ============================================================================
 */

(function () {
    'use strict';

    // Basis-URL van onze API.
    const API = '/crm-extensions/public/api/index.php?route=';

    /**
     * Bouwt het compose-venster op en plakt het in de pagina.
     * dealer: {id, name, email}
     */
    function showComposeDialog(dealer) {
        // Verwijder eerst een eventueel oud open venster.
        document.getElementById('crm-compose-dialog')?.remove();

        const overlay = document.createElement('div');
        overlay.id = 'crm-compose-dialog';
        overlay.className = 'crm-modal-overlay';
        overlay.innerHTML = `
            <div class="crm-modal" role="dialog" style="width:640px;max-width:95vw;border-color:#1F4E79;">
                <h2 style="color:#1F4E79;margin-top:0;">Nieuwe e-mail</h2>
                <div class="crm-modal-card">
                    <strong>Aan: ${escapeHtml(dealer.name)}</strong><br>
                    <span>${escapeHtml(dealer.email)}</span>
                </div>
                <p style="margin:8px 0 4px;font-weight:bold;">Onderwerp *</p>
                <input type="text" id="crm-mail-subject" maxlength="255" autofocus
                       style="width:100%;padding:8px;border:1px solid #ccc;font-family:inherit;font-size:14px;">

                <p style="margin:14px 0 4px;font-weight:bold;">Bericht *</p>
                <textarea id="crm-mail-body" rows="9" maxlength="5000"
                          style="width:100%;padding:8px;border:1px solid #ccc;font-family:inherit;font-size:14px;resize:vertical;"
                          placeholder="Beste ${escapeHtml(dealer.name)},&#10;&#10;"></textarea>

                <p style="font-size:12px;color:#666;margin:6px 0 0;">* verplichte velden</p>

                <div class="crm-modal-buttons">
                    <button type="button" data-action="cancel">Annuleren</button>
                    <button type="button" data-action="send" class="crm-danger" disabled
                            style="background:#1F4E79 !important;border-color:#1F4E79 !important;">
                        Verzenden
                    </button>
                </div>
                <p id="crm-mail-status" style="margin-top:10px;font-size:13px;"></p>
            </div>
        `;
        document.body.appendChild(overlay);

        const subjectEl = overlay.querySelector('#crm-mail-subject');
        const bodyEl    = overlay.querySelector('#crm-mail-body');
        const sendBtn   = overlay.querySelector('[data-action="send"]');
        const statusEl  = overlay.querySelector('#crm-mail-status');

        // Verzend-knop alleen actief als beide velden gevuld zijn.
        const updateBtn = () => {
            sendBtn.disabled = subjectEl.value.trim() === '' || bodyEl.value.trim() === '';
        };
        subjectEl.addEventListener('input', updateBtn);
        bodyEl.addEventListener('input', updateBtn);

        // Annuleren - met 'concept opslaan' bevestiging als er al tekst staat.
        overlay.querySelector('[data-action="cancel"]').addEventListener('click', () => {
            const hasContent = subjectEl.value.trim() !== '' || bodyEl.value.trim() !== '';
            if (hasContent && !confirm('Je hebt al tekst getypt. Weet je zeker dat je wilt sluiten?')) {
                return;
            }
            closeDialog();
        });

        // Escape sluit ook (na bevestiging).
        document.addEventListener('keydown', escClose);
        function escClose(e) { if (e.key === 'Escape') overlay.querySelector('[data-action="cancel"]').click(); }

        function closeDialog() {
            overlay.remove();
            document.removeEventListener('keydown', escClose);
        }

        // Verzenden: POST naar /emails met dealer_id, subject, body.
        sendBtn.addEventListener('click', async () => {
            sendBtn.disabled = true;
            statusEl.textContent = 'Bezig met verzenden...';
            statusEl.style.color = '#666';
            try {
                const res = await fetch(`${API}/emails`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        dealer_id: dealer.id,
                        subject:   subjectEl.value.trim(),
                        body:      bodyEl.value.trim(),
                    }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    statusEl.textContent = data.error || 'Versturen mislukt.';
                    statusEl.style.color = '#C00';
                    sendBtn.disabled = false;
                    return;
                }
                // Succes: pagina vernieuwen zodat de nieuwe mail in de geschiedenis komt.
                statusEl.textContent = 'Verzonden! Pagina ververst zo...';
                statusEl.style.color = '#008B4F';
                setTimeout(() => window.location.reload(), 600);
            } catch (err) {
                statusEl.textContent = 'Netwerkfout: ' + err.message;
                statusEl.style.color = '#C00';
                sendBtn.disabled = false;
            }
        });
    }

    /**
     * Beveilig user input tegen HTML/XSS bij het tonen in de modal.
     */
    function escapeHtml(str) {
        return String(str).replace(/[&<>"']/g, c => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        }[c]));
    }

    // Event-delegatie: koppel aan elke knop met data-email-compose.
    document.addEventListener('click', (ev) => {
        const btn = ev.target.closest('[data-email-compose]');
        if (!btn) return;
        ev.preventDefault();
        showComposeDialog({
            id:    Number(btn.dataset.dealerId),
            name:  btn.dataset.dealerName  || 'deze dealer',
            email: btn.dataset.dealerEmail || '',
        });
    });
})();
