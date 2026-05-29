/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/js/dealer_notes.js
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Frontend voor US-13 (Notitie toevoegen bij een dealer). Dit script:
 *    1. Vangt het versturen van het notitie-formulier op.
 *    2. Controleert dat de notitie niet leeg is.
 *    3. POST de notitie naar de API: /dealers/{id}/notes.
 *    4. Bij succes: de pagina verversen zodat de nieuwe notitie bovenaan
 *       in de lijst verschijnt.
 *
 *  Verwachte HTML op de pagina:
 *    <form id="crm-note-form" data-dealer-id="42">
 *      <textarea name="content"></textarea>
 *      <button type="submit">Notitie opslaan</button>
 *    </form>
 *    <div id="crm-note-status"></div>
 * ============================================================================
 */

(function () {
    'use strict';

    // Basis-URL van de API.
    const API = '/crm-extensions/public/api/index.php?route=';

    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('crm-note-form');
        if (!form) return;

        const textarea = form.querySelector('textarea[name="content"]');
        const button   = form.querySelector('button[type="submit"]');
        const statusEl = document.getElementById('crm-note-status');

        function showStatus(text, color) {
            if (!statusEl) return;
            statusEl.textContent = text;
            statusEl.style.color = color;
        }

        form.addEventListener('submit', async function (ev) {
            ev.preventDefault();

            // Controle vooraf: de notitie mag niet leeg zijn.
            const content = (textarea.value || '').trim();
            if (content === '') {
                showStatus('De notitie mag niet leeg zijn.', '#C00');
                textarea.focus();
                return;
            }

            const dealerId = form.dataset.dealerId;
            button.disabled = true;
            showStatus('Bezig met opslaan...', '#666');

            try {
                const res = await fetch(`${API}/dealers/${dealerId}/notes`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ content: content }),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) {
                    showStatus(data.error || 'Opslaan mislukt.', '#C00');
                    button.disabled = false;
                    return;
                }
                // Succes: pagina verversen zodat de notitie in de lijst komt.
                showStatus('Notitie opgeslagen. Pagina ververst...', '#008B4F');
                setTimeout(function () { window.location.reload(); }, 500);
            } catch (err) {
                showStatus('Netwerkfout: ' + err.message, '#C00');
                button.disabled = false;
            }
        });
    });
})();
