/**
 * ============================================================================
 *  Auteur:   Khayrallah Issa
 *  Project:  CRM WooPremium uitbreiding
 *  Bestand:  public/js/route_planner.js
 *
 *  Wat doet dit bestand?
 *  ---------------------
 *  Frontend voor US-01 t/m US-04 (routeplanning op de kaart).
 *    - Toont alle dealers als markers op een Leaflet-kaart.
 *    - Klikken op een marker selecteert die dealer (rood + volgnummer).
 *    - 'Plan route'-knop roept de backend aan (POST /routes/calculate)
 *      die OSRM bevraagt en de route + afstand + tijd teruggeeft.
 *    - De route wordt als blauwe lijn op de kaart getekend.
 *    - In de zijbalk een lijst die je kunt herordenen door knoppen
 *      'omhoog' / 'omlaag' (US-03).
 *    - 'Wis selectie' maakt alles leeg.
 *    - 'Route opslaan' bewaart de berekende route onder een naam via
 *      POST /routes (US-04).
 *
 *  Verwachte HTML op de pagina:
 *    <div id="crm-map" data-dealers='[...]'></div>
 *    <ul id="crm-selected"></ul>
 *    <div id="crm-route-info"></div>
 *    <button id="crm-plan-btn">Plan route</button>
 *    <button id="crm-clear-btn">Wis selectie</button>
 *    <div id="crm-save-box"> (US-04, verborgen tot een route berekend is)
 *      <input id="crm-route-name"> <button id="crm-save-btn">
 *      <div id="crm-save-msg"></div>
 * ============================================================================
 */

(function () {
    'use strict';

    const API = '/crm-extensions/public/api/index.php?route=';
    const MAX_DEALERS = 25;

    // State: lijst van geselecteerde dealer-ids in volgorde
    let selectedIds = [];
    // Lookup van marker-objecten per dealer-id, voor styling
    const markers = {};
    // De Leaflet-kaart. Auteur: Khayrallah Issa - in init()
    // gevuld zodat planRoute() de routelijn op DEZELFDE kaart kan tekenen.
    let map = null;
    // Khayrallah Issa: de marker-clustergroep. Bundelt nabije
    // dealer-markers tot 1 genummerde cluster, net als de normale Kaart-pagina.
    let clusterGroup = null;
    // De route-polyline op de kaart (kan null zijn)
    let routePolyline = null;
    // US-04 (Khayrallah Issa): het laatst berekende routeresultaat
    // (afstand + tijd). Nodig om de route te kunnen opslaan.
    let lastRoute = null;
    // Khayrallah Issa: actief postcode/straal-filter ({lat,lng,km}),
    // of null als er niet op afstand gefilterd wordt.
    let radiusFilter = null;

    // Wacht tot HTML klaar is
    document.addEventListener('DOMContentLoaded', init);

    function init() {
        const mapEl = document.getElementById('crm-map');
        if (!mapEl) return;
        const dealers = JSON.parse(mapEl.dataset.dealers || '[]');

        // Leaflet-kaart opzetten, gecentreerd op Nederland (Utrecht).
        // Auteur: Khayrallah Issa - 'map' is module-breed zodat
        // planRoute() de route op deze kaart kan tekenen.
        map = L.map('crm-map').setView([52.09, 5.12], 8);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap',
            maxZoom: 18,
        }).addTo(map);

        // Khayrallah Issa: marker-clustergroep zoals op de normale
        // Kaart-pagina. Nabije dealers worden tot 1 genummerde cluster gebundeld,
        // zodat de kaart overzichtelijk en snel blijft met duizenden dealers.
        clusterGroup = L.markerClusterGroup({
            chunkedLoading: true,
            maxClusterRadius: 50,
        });

        // Voor elke dealer met een lat/lng een marker plaatsen (in de cluster).
        // Auteur: Khayrallah Issa
        // Klik op marker = popup met dealer-info (zoals op de oude WP-admin
        // kaart) met daarin een knop "Toevoegen aan route" / "Uit route halen".
        dealers.forEach(d => {
            if (!d.lat || !d.lng) return;
            const marker = L.circleMarker([d.lat, d.lng], {
                radius: 8,
                color: '#1F4E79',
                fillColor: '#1F4E79',
                fillOpacity: 0.9,
                weight: 2,
            });
            marker.bindTooltip(d.name, { permanent: false });
            // Popup wordt dynamisch opgebouwd bij elke open omdat de tekst
            // van de knop afhangt van of de dealer al geselecteerd is.
            marker.bindPopup(() => buildDealerPopup(d), {
                maxWidth: 280, minWidth: 220, autoClose: true,
            });
            // Wanneer de gebruiker in de popup op de knop klikt,
            // moet die de toggleSelect doen en de popup verversen/sluiten.
            marker.on('popupopen', (ev) => {
                const popupEl = ev.popup.getElement();
                const btn = popupEl ? popupEl.querySelector('.crm-popup-select-btn') : null;
                if (!btn) return;
                btn.addEventListener('click', () => {
                    toggleSelect(d);
                    marker.closePopup();
                });
            });
            markers[d.id] = { marker, dealer: d };
            clusterGroup.addLayer(marker);
        });
        map.addLayer(clusterGroup);

        // Knoppen koppelen
        document.getElementById('crm-plan-btn')?.addEventListener('click', planRoute);
        document.getElementById('crm-clear-btn')?.addEventListener('click', clearSelection);
        // US-04 (Khayrallah Issa): knop om de route op te slaan.
        document.getElementById('crm-save-btn')?.addEventListener('click', saveRoute);
        // Auteur: Khayrallah Issa
        // Knop om de geplande route in Google Maps te openen.
        document.getElementById('crm-gmaps-btn')?.addEventListener('click', openInGoogleMaps);

        // Khayrallah Issa: filters op merk, status en postcode/straal.
        document.getElementById('crm-filter-brand')?.addEventListener('change', applyFilters);
        document.getElementById('crm-filter-status')?.addEventListener('change', applyFilters);
        document.getElementById('crm-filter-search')?.addEventListener('click', searchPostcode);
        document.getElementById('crm-filter-reset')?.addEventListener('click', resetFilters);

        // US-04 (Khayrallah Issa): klik op een opgeslagen route om
        // 'm opnieuw te laden. Event-delegatie, want de lijst wordt herbouwd.
        document.getElementById('crm-saved-routes')?.addEventListener('click', onSavedRouteClick);

        applyFilters();      // begintelling tonen
        loadSavedRoutes();   // US-04: opgeslagen routes tonen
    }

    /**
     * Voegt een dealer toe of haalt 'm weg uit de selectie.
     * Updatet de marker-styling en de lijst.
     */
    function toggleSelect(d) {
        const idx = selectedIds.indexOf(d.id);
        if (idx >= 0) {
            // Al geselecteerd -> deselecteren
            selectedIds.splice(idx, 1);
        } else {
            if (selectedIds.length >= MAX_DEALERS) {
                alert(`Maximaal ${MAX_DEALERS} dealers per route.`);
                return;
            }
            selectedIds.push(d.id);
        }
        refreshMarkers();
        refreshSidebar();
    }

    /**
     * Tekent de markers opnieuw op basis van de huidige selectie.
     * Geselecteerde dealers krijgen een rode marker met volgnummer.
     */
    function refreshMarkers() {
        Object.values(markers).forEach(({ marker, dealer }) => {
            const idx = selectedIds.indexOf(dealer.id);
            if (idx >= 0) {
                marker.setStyle({ color: '#C00000', fillColor: '#C00000' });
                // Toon het volgnummer als label boven de marker
                marker.bindTooltip(String(idx + 1), {
                    permanent: true, direction: 'center',
                    className: 'crm-marker-num',
                });
            } else {
                marker.setStyle({ color: '#1F4E79', fillColor: '#1F4E79' });
                marker.unbindTooltip();
                marker.bindTooltip(dealer.name, { permanent: false });
            }
        });
    }

    /**
     * Vult de zijbalk met de huidige selectie + omhoog/omlaag knoppen.
     */
    function refreshSidebar() {
        const ul = document.getElementById('crm-selected');
        if (!ul) return;
        if (selectedIds.length === 0) {
            ul.innerHTML = '<li style="color:#888;">Nog niets geselecteerd. Klik op markers.</li>';
            return;
        }
        ul.innerHTML = selectedIds.map((id, i) => {
            const d = markers[id].dealer;
            return `
                <li class="crm-stop">
                    <span class="crm-stop-num">${i + 1}</span>
                    <span class="crm-stop-name">${escapeHtml(d.name)}</span>
                    <span class="crm-stop-buttons">
                        <button type="button" onclick="window._crmMove(${id},-1)" ${i===0?'disabled':''}>&uarr;</button>
                        <button type="button" onclick="window._crmMove(${id},+1)" ${i===selectedIds.length-1?'disabled':''}>&darr;</button>
                        <button type="button" onclick="window._crmRemove(${id})">&times;</button>
                    </span>
                </li>`;
        }).join('');
        document.getElementById('crm-count').textContent =
            `${selectedIds.length} van ${MAX_DEALERS}`;
    }

    // US-03: omhoog/omlaag verschuiven
    window._crmMove = (id, delta) => {
        const idx = selectedIds.indexOf(id);
        const target = idx + delta;
        if (idx < 0 || target < 0 || target >= selectedIds.length) return;
        [selectedIds[idx], selectedIds[target]] = [selectedIds[target], selectedIds[idx]];
        refreshMarkers();
        refreshSidebar();
    };

    window._crmRemove = (id) => {
        selectedIds = selectedIds.filter(x => x !== id);
        refreshMarkers();
        refreshSidebar();
    };

    function clearSelection() {
        selectedIds = [];
        if (routePolyline) {
            routePolyline.remove();
            routePolyline = null;
        }
        document.getElementById('crm-route-info').innerHTML = '';
        // US-04 (Khayrallah Issa): het opslaan-blok verbergen en
        // het vorige routeresultaat vergeten.
        lastRoute = null;
        hideSaveBox();
        // Auteur: Khayrallah Issa
        // Google Maps-knop ook weer verbergen bij wissen van de selectie.
        const gbtn = document.getElementById('crm-gmaps-btn');
        if (gbtn) gbtn.style.display = 'none';
        refreshMarkers();
        refreshSidebar();
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Bouwt de HTML van de marker-popup met dealer-info (naam, adres,
     * telefoon, e-mail, merken, status) plus een knop om de dealer toe
     * te voegen aan / te verwijderen uit de geplande route.
     */
    function buildDealerPopup(d) {
        const esc = (s) => String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

        const isSelected = selectedIds.includes(d.id);
        const lines = [];
        lines.push('<div style="font-size:13px; line-height:1.45;">');
        lines.push('<strong style="font-size:14px; color:#1F4E79;">' + esc(d.name) + '</strong>');

        const adres = [d.street, d.postcode, d.city].filter(Boolean).join(', ');
        if (adres) lines.push('<div>' + esc(adres) + '</div>');

        if (d.contact_person) lines.push('<div><em>Contact: ' + esc(d.contact_person) + '</em></div>');
        if (d.phone) lines.push('<div>Tel: <a href="tel:' + esc(d.phone) + '">' + esc(d.phone) + '</a></div>');
        if (d.email) lines.push('<div>E-mail: <a href="mailto:' + esc(d.email) + '">' + esc(d.email) + '</a></div>');
        if (d.website) lines.push('<div>Web: <a href="' + esc(d.website) + '" target="_blank" rel="noopener">' + esc(d.website) + '</a></div>');

        if (Array.isArray(d.brands) && d.brands.length) {
            lines.push('<div style="margin-top:4px; color:#555;"><em>' + esc(d.brands.join(', ')) + '</em></div>');
        }
        if (d.status) {
            lines.push('<div style="margin-top:2px; font-size:12px; color:#666;">Status: ' + esc(d.status) + '</div>');
        }

        // Knop: toevoegen of verwijderen, afhankelijk van huidige selectie.
        const btnLabel = isSelected ? 'Uit route halen' : 'Toevoegen aan route';
        const btnColor = isSelected ? '#C00000' : '#1F4E79';
        lines.push(
            '<button class="crm-popup-select-btn" style="margin-top:8px; width:100%; '
            + 'background:' + btnColor + '; color:#fff; border:0; padding:6px 10px; '
            + 'border-radius:3px; cursor:pointer; font-weight:600;">'
            + btnLabel + '</button>'
        );

        lines.push('</div>');
        return lines.join('');
    }

    /**
     * Auteur: Khayrallah Issa
     *
     * Open de geplande route in Google Maps in een nieuw tabblad.
     * Google Maps URL-format (Directions API mode):
     *   https://www.google.com/maps/dir/?api=1
     *     &origin=LAT,LNG          (eerste dealer)
     *     &destination=LAT,LNG     (laatste dealer)
     *     &waypoints=LAT,LNG|...   (alles ertussen, max 9 in Google Maps gratis)
     *     &travelmode=driving
     *
     * Werkt direct op desktop en mobiel - Google Maps app pakt 't op
     * via de URL en biedt navigatie aan. Geen API-key nodig.
     */
    function openInGoogleMaps() {
        if (selectedIds.length < 2) {
            alert('Selecteer minimaal 2 dealers en plan eerst een route.');
            return;
        }

        // Coordinaten verzamelen in de huidige volgorde.
        const coords = [];
        for (const id of selectedIds) {
            const m = markers[id];
            if (!m || !m.dealer || !m.dealer.lat || !m.dealer.lng) continue;
            coords.push(m.dealer.lat + ',' + m.dealer.lng);
        }
        if (coords.length < 2) {
            alert('Niet alle dealers hebben coordinaten.');
            return;
        }

        // Eerste = origin, laatste = destination, rest = waypoints.
        // Google Maps accepteert max 9 waypoints; we splitsen niet maar
        // waarschuwen de gebruiker als het meer is.
        const origin = coords[0];
        const destination = coords[coords.length - 1];
        const middle = coords.slice(1, -1);

        if (middle.length > 9) {
            const ok = confirm(
                'Google Maps ondersteunt maximaal 9 tussenstops. ' +
                'Alleen de eerste 9 worden meegenomen, de rest vervalt. Doorgaan?'
            );
            if (!ok) return;
        }

        const params = new URLSearchParams({
            api: '1',
            origin: origin,
            destination: destination,
            travelmode: 'driving',
        });
        if (middle.length > 0) {
            params.set('waypoints', middle.slice(0, 9).join('|'));
        }

        const url = 'https://www.google.com/maps/dir/?' + params.toString();
        window.open(url, '_blank', 'noopener');
    }

    /**
     * US-02: roep de backend aan om de route te berekenen via OSRM.
     */
    async function planRoute() {
        if (selectedIds.length < 2) {
            alert('Selecteer minimaal 2 dealers.');
            return;
        }
        const infoEl = document.getElementById('crm-route-info');
        infoEl.innerHTML = '<em>Bezig met berekenen...</em>';

        try {
            const res = await fetch(`${API}/routes/calculate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ dealer_ids: selectedIds }),
            });
            const data = await res.json();
            if (!res.ok) {
                infoEl.innerHTML = `<span style="color:#C00">${data.error || 'Fout bij route'}</span>`;
                return;
            }

            // Oude polyline weghalen, nieuwe tekenen.
            // Auteur: Khayrallah Issa - de route wordt op de
            // module-brede 'map' getekend; daarna zoomen we er netjes naartoe.
            if (routePolyline) routePolyline.remove();
            if (map && data.geometry && data.geometry.length > 0) {
                // OSRM geeft [lng,lat], Leaflet wil [lat,lng]
                const latlngs = data.geometry.map(([lng, lat]) => [lat, lng]);
                routePolyline = L.polyline(latlngs, {
                    color: '#C00000', weight: 4, opacity: 0.75,
                }).addTo(map);
                map.fitBounds(routePolyline.getBounds(), { padding: [40, 40] });
            }

            const warn = data.warning ? `<br><em style="color:#a60">${data.warning}</em>` : '';
            infoEl.innerHTML = `
                <strong>Route info</strong><br>
                Totaal: <strong>${data.total_distance_km} km</strong><br>
                Reistijd: <strong>${formatMinutes(data.estimated_time_min)}</strong><br>
                ${selectedIds.length} stops${warn}
            `;

            // US-04 (Khayrallah Issa): het resultaat onthouden zodat
            // de route opgeslagen kan worden, en het opslaan-blok tonen.
            lastRoute = {
                total_distance_km:  data.total_distance_km,
                estimated_time_min: data.estimated_time_min,
            };
            showSaveBox();

            // Auteur: Khayrallah Issa
            // Google Maps-knop zichtbaar maken zodra er een geldige route is.
            const gbtn = document.getElementById('crm-gmaps-btn');
            if (gbtn) gbtn.style.display = 'block';
        } catch (err) {
            infoEl.innerHTML = `<span style="color:#C00">Netwerkfout: ${err.message}</span>`;
        }
    }

    /**
     * US-04 (Khayrallah Issa): de berekende route opslaan.
     * Stuurt de naam + dealer-volgorde + afstand/tijd naar POST /routes.
     * De backend (RouteController->save) bewaart het in wp_crm_routes.
     */
    async function saveRoute() {
        const msgEl = document.getElementById('crm-save-msg');
        if (!msgEl) return;

        // Er moet eerst een route berekend zijn (afstand + tijd nodig).
        if (!lastRoute || selectedIds.length < 2) {
            msgEl.innerHTML = '<span style="color:#C00">Plan eerst een route.</span>';
            return;
        }
        // De route heeft een naam nodig.
        const nameEl = document.getElementById('crm-route-name');
        const name = (nameEl?.value || '').trim();
        if (name === '') {
            msgEl.innerHTML = '<span style="color:#C00">Geef de route eerst een naam.</span>';
            nameEl?.focus();
            return;
        }

        msgEl.innerHTML = '<em>Bezig met opslaan...</em>';
        try {
            const res = await fetch(`${API}/routes`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: name,
                    dealer_ids: selectedIds,
                    total_distance_km: lastRoute.total_distance_km,
                    estimated_time_min: lastRoute.estimated_time_min,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                msgEl.innerHTML = `<span style="color:#C00">${data.error || 'Opslaan mislukt.'}</span>`;
                return;
            }
            msgEl.innerHTML =
                `<span style="color:#093">"${escapeHtml(name)}" opgeslagen (route #${data.id}).</span>`;
            if (nameEl) nameEl.value = '';
            loadSavedRoutes();   // de lijst met opgeslagen routes meteen verversen
        } catch (err) {
            msgEl.innerHTML = `<span style="color:#C00">Netwerkfout: ${err.message}</span>`;
        }
    }

    /** US-04: toont het 'Route opslaan'-blok in de zijbalk. */
    function showSaveBox() {
        const box = document.getElementById('crm-save-box');
        if (box) box.style.display = 'block';
    }

    /** US-04: verbergt het 'Route opslaan'-blok en wist de melding. */
    function hideSaveBox() {
        const box = document.getElementById('crm-save-box');
        if (box) box.style.display = 'none';
        const msg = document.getElementById('crm-save-msg');
        if (msg) msg.innerHTML = '';
    }

    /**
     * Khayrallah Issa: toont/verbergt dealer-markers op basis van
     * de gekozen filters (merk, status, postcode/straal) en werkt de teller bij.
     */
    function applyFilters() {
        const brand  = document.getElementById('crm-filter-brand')?.value || '';
        const status = document.getElementById('crm-filter-status')?.value || '';
        let zichtbaar = 0;

        Object.values(markers).forEach(({ marker, dealer }) => {
            let toon = true;
            if (brand && !(dealer.brands || []).includes(brand)) toon = false;
            if (status && dealer.status !== status) toon = false;
            if (toon && radiusFilter) {
                const afstand = haversineKm(
                    radiusFilter.lat, radiusFilter.lng,
                    Number(dealer.lat), Number(dealer.lng)
                );
                if (afstand > radiusFilter.km) toon = false;
            }
            if (toon) {
                if (clusterGroup && !clusterGroup.hasLayer(marker)) clusterGroup.addLayer(marker);
                zichtbaar++;
            } else if (clusterGroup && clusterGroup.hasLayer(marker)) {
                clusterGroup.removeLayer(marker);
            }
        });

        const countEl = document.getElementById('crm-filter-count');
        if (countEl) countEl.textContent = `${zichtbaar} dealers zichtbaar`;
    }

    /**
     * Khayrallah Issa: zoekt de coordinaten van een postcode op via
     * OpenStreetMap (Nominatim) en filtert dealers binnen de gekozen straal.
     */
    async function searchPostcode() {
        const postcode = (document.getElementById('crm-filter-postcode')?.value || '').trim();
        const km = parseInt(document.getElementById('crm-filter-radius')?.value || '10', 10);
        if (postcode === '') {
            radiusFilter = null;
            applyFilters();
            return;
        }
        try {
            const url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1'
                      + '&countrycodes=nl&postalcode=' + encodeURIComponent(postcode);
            const res  = await fetch(url);
            const data = await res.json();
            if (!data || data.length === 0) {
                alert('Postcode niet gevonden.');
                return;
            }
            radiusFilter = {
                lat: parseFloat(data[0].lat),
                lng: parseFloat(data[0].lon),
                km:  km,
            };
            if (map) map.setView([radiusFilter.lat, radiusFilter.lng], 11);
            applyFilters();
        } catch (err) {
            alert('Postcode zoeken mislukt: ' + err.message);
        }
    }

    /** Khayrallah Issa: zet alle filters terug naar 'alles tonen'. */
    function resetFilters() {
        const b = document.getElementById('crm-filter-brand');
        const s = document.getElementById('crm-filter-status');
        const p = document.getElementById('crm-filter-postcode');
        if (b) b.value = '';
        if (s) s.value = '';
        if (p) p.value = '';
        radiusFilter = null;
        applyFilters();
    }

    /** Khayrallah Issa: afstand tussen 2 GPS-punten in km (Haversine). */
    function haversineKm(lat1, lng1, lat2, lng2) {
        const r = 6371; // straal van de aarde in km
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLng = (lng2 - lng1) * Math.PI / 180;
        const a = Math.sin(dLat / 2) ** 2
                + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180)
                  * Math.sin(dLng / 2) ** 2;
        return r * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
    }

    /**
     * US-04 (Khayrallah Issa): haalt de opgeslagen routes op via
     * GET /routes en toont ze in de zijbalk (naam, afstand, tijd, datum).
     */
    async function loadSavedRoutes() {
        const ul = document.getElementById('crm-saved-routes');
        if (!ul) return;
        try {
            const res  = await fetch(`${API}/routes`);
            const data = await res.json();
            if (!res.ok) {
                ul.innerHTML =
                    `<li style="color:#C00;font-size:13px;">${data.error || 'Kon routes niet laden.'}</li>`;
                return;
            }
            const routes = data.routes || [];
            if (routes.length === 0) {
                ul.innerHTML =
                    '<li style="color:#888;font-size:13px;">Nog geen routes opgeslagen.</li>';
                return;
            }
            ul.innerHTML = routes.map(r => {
                const datum = r.created_at
                    ? new Date(r.created_at).toLocaleDateString('nl-NL')
                    : '';
                const km = (r.total_distance_km != null) ? r.total_distance_km + ' km' : '';
                return `
                    <li data-route-id="${r.id}" title="Klik om deze route opnieuw te gebruiken"
                        style="padding:6px 8px;background:#fff;border:1px solid #ddd;border-radius:3px;margin-bottom:4px;font-size:13px;cursor:pointer;">
                        <strong>${escapeHtml(r.name)}</strong><br>
                        <span style="color:#666;">
                            ${km}${km ? ' - ' : ''}${formatMinutes(r.estimated_time_min || 0)}${datum ? ' - ' + datum : ''}
                        </span><br>
                        <span style="color:#1F4E79;font-size:11px;">opnieuw gebruiken &raquo;</span>
                    </li>`;
            }).join('');
        } catch (err) {
            ul.innerHTML =
                `<li style="color:#C00;font-size:13px;">Netwerkfout: ${err.message}</li>`;
        }
    }

    /**
     * US-04 (Khayrallah Issa): vangt een klik op de routelijst op
     * en laadt de aangeklikte route opnieuw.
     */
    function onSavedRouteClick(e) {
        const li = e.target.closest('li[data-route-id]');
        if (!li) return;
        loadRoute(parseInt(li.dataset.routeId, 10));
    }

    /**
     * US-04 (Khayrallah Issa): laadt een opgeslagen route opnieuw.
     * Haalt de dealer-volgorde op via GET /routes/{id}, selecteert die dealers
     * in dezelfde volgorde en berekent de route meteen opnieuw.
     */
    async function loadRoute(routeId) {
        try {
            const res  = await fetch(`${API}/routes/${routeId}`);
            const data = await res.json();
            if (!res.ok) {
                alert(data.error || 'Route laden mislukt.');
                return;
            }
            // Alleen dealers die nu nog als marker op de kaart bestaan.
            const ids = (data.dealer_ids || []).filter(id => markers[id]);
            if (ids.length < 2) {
                alert('Deze route bevat te weinig bekende dealers om opnieuw te plannen.');
                return;
            }
            selectedIds = ids;
            refreshMarkers();
            refreshSidebar();
            planRoute();   // meteen opnieuw berekenen en tekenen
        } catch (err) {
            alert('Route laden mislukt: ' + err.message);
        }
    }

    function formatMinutes(min) {
        const h = Math.floor(min / 60);
        const m = min % 60;
        return h > 0 ? `${h}u ${m}min` : `${m}min`;
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, c => ({
            '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
        }[c]));
    }
})();
