document.addEventListener('DOMContentLoaded', function () {

    // Clickable rows
    document.querySelectorAll('.crm-dealer-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') return;
            // Don't navigate when clicking buttons, inputs, or inside the actions cell
            if (e.target.closest('button')) return;
            if (e.target.closest('input')) return;
            if (e.target.closest('.crm-actions-cell')) return;
            if (e.target.closest('.crm-checkbox-cell')) return;
            if (!this.dataset.href) return;
            window.location = this.dataset.href;
        });
    });

    // Tabs
    document.querySelectorAll('.crm-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            document.querySelectorAll('.crm-tab').forEach(function (t) { t.classList.remove('active'); });
            document.querySelectorAll('.crm-tab-content').forEach(function (c) { c.classList.remove('active'); });
            this.classList.add('active');
            document.getElementById('tab-' + this.dataset.tab).classList.add('active');
        });
    });

    // Edit toggle
    document.querySelectorAll('.crm-edit-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.dataset.target;
            document.getElementById(target + '-display').style.display = 'none';
            document.getElementById(target + '-edit').style.display = 'block';
            this.style.display = 'none';
        });
    });
    document.querySelectorAll('.crm-edit-cancel').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = this.dataset.target;
            document.getElementById(target + '-display').style.display = 'block';
            document.getElementById(target + '-edit').style.display = 'none';
            document.querySelector('.crm-edit-toggle[data-target="' + target + '"]').style.display = '';
        });
    });

    // Edit dealer form
    var editForm = document.getElementById('dealer-edit-form');
    if (editForm) {
        editForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_update_dealer');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Fout bij opslaan.');
                });
        });
    }

    // Add note
    var noteForm = document.getElementById('add-note-form');
    if (noteForm) {
        noteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_add_note');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        var list = document.getElementById('notes-list');
                        list.querySelector('.crm-empty')?.remove();
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-flash';
                        item.dataset.id = d.id;
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<strong>' + esc(d.author) + '</strong>' +
                            '<time>' + esc(d.date) + '</time>' +
                            '<button class="crm-delete-btn" data-type="note" data-id="' + d.id + '" title="Verwijderen">&times;</button>' +
                            '</div>' +
                            '<div class="crm-timeline-body">' + esc(d.content).replace(/\n/g, '<br>') + '</div>';
                        list.prepend(item);
                        form.reset();
                        updateTabCount('notes', 1);
                    }
                });
        });
    }

    // Add contact
    var contactForm = document.getElementById('add-contact-form');
    if (contactForm) {
        contactForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_add_contact');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        var list = document.getElementById('contacts-list');
                        list.querySelector('.crm-empty')?.remove();
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-flash';
                        item.dataset.id = d.id;
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<span class="crm-contact-type crm-contact-type-' + esc(d.type) + '">' + esc(d.type.charAt(0).toUpperCase() + d.type.slice(1)) + '</span>' +
                            '<strong>' + esc(d.author) + '</strong>' +
                            '<time>' + esc(d.date) + '</time>' +
                            '<button class="crm-delete-btn" data-type="contact" data-id="' + d.id + '" title="Verwijderen">&times;</button>' +
                            '</div>' +
                            (d.subject ? '<div class="crm-timeline-subject">' + esc(d.subject) + '</div>' : '') +
                            (d.content ? '<div class="crm-timeline-body">' + esc(d.content).replace(/\n/g, '<br>') + '</div>' : '');
                        list.prepend(item);
                        form.reset();
                        form.querySelector('[name="contact_date"]').value = new Date().toISOString().slice(0, 16);
                        updateTabCount('contacts', 1);
                    }
                });
        });
    }

    // Delete note/contact
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-delete-btn');
        if (!btn) return;
        if (!confirm('Weet je het zeker?')) return;
        var fd = new FormData();
        fd.append('action', btn.dataset.type === 'note' ? 'crm_delete_note' : 'crm_delete_contact');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var item = btn.closest('.crm-timeline-item');
                    var tabType = btn.dataset.type === 'note' ? 'notes' : 'contacts';
                    item.remove();
                    updateTabCount(tabType, -1);
                }
            });
    });

    // Add tag
    var addTagBtn = document.getElementById('add-tag-btn');
    if (addTagBtn) {
        addTagBtn.addEventListener('click', function () {
            var select = document.getElementById('add-tag-select');
            var tagId = select.value;
            if (!tagId) return;
            var fd = new FormData();
            fd.append('action', 'crm_add_tag');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('tag_id', tagId);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) { if (r.success) location.reload(); });
        });
    }

    // Remove tag
    document.addEventListener('click', function (e) {
        var removeBtn = e.target.closest('.crm-tag-remove');
        if (!removeBtn) return;
        var chip = removeBtn.closest('.crm-tag-removable');
        var fd = new FormData();
        fd.append('action', 'crm_remove_tag');
        fd.append('dealer_id', chip.dataset.dealerId);
        fd.append('tag_id', chip.dataset.tagId);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) { if (r.success) chip.remove(); });
    });

    function updateTabCount(tabType, delta) {
        var tab = document.querySelector('.crm-tab[data-tab="' + tabType + '"]');
        if (!tab) return;
        var m = tab.textContent.match(/\((\d+)\)/);
        if (m) {
            var n = Math.max(0, parseInt(m[1]) + delta);
            tab.textContent = tab.textContent.replace(/\(\d+\)/, '(' + n + ')');
        }
    }

    function esc(str) {
        var d = document.createElement('div');
        d.textContent = str || '';
        return d.innerHTML;
    }

    // Merge: search for duplicate dealer
    var mergeSearchInput = document.getElementById('merge-search-input');
    var mergeSearchResults = document.getElementById('merge-search-results');
    var mergePrimaryId = document.getElementById('merge-primary-id');
    var mergeSearchTimer = null;

    if (mergeSearchInput) {
        mergeSearchInput.addEventListener('input', function () {
            var query = this.value.trim();
            clearTimeout(mergeSearchTimer);
            if (query.length < 2) {
                mergeSearchResults.style.display = 'none';
                return;
            }
            mergeSearchTimer = setTimeout(function () {
                var fd = new FormData();
                fd.append('action', 'crm_search_dealers');
                fd.append('search', query);
                fd.append('exclude', mergePrimaryId.value);
                fd.append('nonce', dealerCRM.nonce);
                fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        if (!r.success || !r.data.length) {
                            mergeSearchResults.innerHTML = '<div class="crm-merge-search-empty">Geen dealers gevonden.</div>';
                            mergeSearchResults.style.display = 'block';
                            return;
                        }
                        var html = '';
                        r.data.forEach(function (d) {
                            var mergeUrl = window.location.href.split('?')[0] +
                                '?page=dealer-crm&action=merge&id=' + mergePrimaryId.value + '&merge_with=' + d.id;
                            html += '<a href="' + mergeUrl + '" class="crm-merge-search-item">' +
                                '<div>' +
                                '<div class="crm-merge-search-item-name">' + esc(d.name) + '</div>' +
                                '<div class="crm-merge-search-item-details">' +
                                    (d.city ? esc(d.city) : '') +
                                    (d.email ? ' &middot; ' + esc(d.email) : '') +
                                    (d.phone ? ' &middot; ' + esc(d.phone) : '') +
                                '</div>' +
                                '</div>' +
                                '<span class="crm-merge-search-item-select">Selecteren</span>' +
                                '</a>';
                        });
                        mergeSearchResults.innerHTML = html;
                        mergeSearchResults.style.display = 'block';
                    });
            }, 300);
        });

        // Hide results on outside click
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.crm-merge-search-wrap')) {
                mergeSearchResults.style.display = 'none';
            }
        });

        mergeSearchInput.focus();
    }

    // Add follow-up
    var followupForm = document.getElementById('add-followup-form');
    if (followupForm) {
        followupForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_add_followup');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        var list = document.getElementById('followups-list');
                        list.querySelector('.crm-empty')?.remove();
                        var today = new Date().toISOString().slice(0, 10);
                        var isOverdue = (d.status === 'open' && d.due_date < today);
                        var badgeClass = isOverdue ? 'crm-followup-badge-verlopen' : 'crm-followup-badge-' + d.status;
                        var badgeText = isOverdue ? 'Verlopen' : d.status.charAt(0).toUpperCase() + d.status.slice(1);
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-followup-item crm-flash' + (isOverdue ? ' crm-followup-overdue' : '');
                        item.dataset.id = d.id;
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<span class="crm-followup-badge ' + badgeClass + '">' + esc(badgeText) + '</span>' +
                            '<strong>' + esc(d.assignee_name) + '</strong>' +
                            '<time>' + formatDate(d.due_date) + '</time>' +
                            '<button class="button button-small crm-complete-followup-btn" data-id="' + d.id + '">Voltooien</button>' +
                            '<button class="crm-delete-btn crm-delete-followup-btn" data-id="' + d.id + '" title="Verwijderen">&times;</button>' +
                            '</div>' +
                            '<div class="crm-timeline-subject">' + esc(d.title) + '</div>' +
                            (d.description ? '<div class="crm-timeline-body">' + esc(d.description).replace(/\n/g, '<br>') + '</div>' : '') +
                            '<div class="crm-followup-creator">Aangemaakt door ' + esc(d.creator_name) + '</div>';
                        list.prepend(item);
                        form.reset();
                        form.querySelector('[name="due_date"]').value = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
                        form.querySelector('[name="user_id"]').value = form.querySelector('[name="user_id"] option[selected]')?.value || '';
                        updateTabCount('followups', 1);
                    } else {
                        alert(r.data || 'Fout bij toevoegen.');
                    }
                });
        });
    }

    // Complete follow-up
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-complete-followup-btn');
        if (!btn) return;
        var fd = new FormData();
        fd.append('action', 'crm_complete_followup');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        btn.disabled = true;
        btn.textContent = 'Bezig...';
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var item = btn.closest('.crm-timeline-item, tr');
                    if (item.tagName === 'TR') {
                        // Dashboard table row
                        location.reload();
                    } else {
                        // Dealer detail follow-up item
                        item.classList.remove('crm-followup-overdue');
                        var badge = item.querySelector('.crm-followup-badge');
                        if (badge) {
                            badge.className = 'crm-followup-badge crm-followup-badge-voltooid';
                            badge.textContent = 'Voltooid';
                        }
                        btn.remove();
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Voltooien';
                    alert(r.data || 'Fout bij voltooien.');
                }
            });
    });

    // Delete follow-up
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-delete-followup-btn');
        if (!btn) return;
        if (!confirm('Weet je het zeker?')) return;
        var fd = new FormData();
        fd.append('action', 'crm_delete_followup');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var item = btn.closest('.crm-timeline-item, tr');
                    if (item.tagName === 'TR') {
                        item.remove();
                    } else {
                        item.remove();
                        updateTabCount('followups', -1);
                    }
                }
            });
    });

    function formatDate(dateStr) {
        var parts = dateStr.split('-');
        return parts[2] + '-' + parts[1] + '-' + parts[0];
    }

    // Merge: execute
    var mergeForm = document.getElementById('merge-execute-form');
    if (mergeForm) {
        mergeForm.addEventListener('submit', function (e) {
            e.preventDefault();
            if (!confirm('Weet je zeker dat je deze dealers wilt samenvoegen? Dit kan niet ongedaan worden gemaakt.')) return;

            var fd = new FormData(this);
            fd.append('action', 'crm_merge_dealers');
            fd.append('primary_id', this.dataset.primaryId);
            fd.append('merge_with_id', this.dataset.mergeWithId);
            fd.append('nonce', dealerCRM.nonce);

            var btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Bezig met samenvoegen...';

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        window.location = r.data.redirect;
                    } else {
                        alert(r.data || 'Er is een fout opgetreden.');
                        btn.disabled = false;
                        btn.textContent = 'Samenvoegen uitvoeren';
                    }
                });
        });
    }

    // Send email form
    var emailForm = document.getElementById('send-email-form');
    if (emailForm) {
        emailForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('send-email-btn');
            var origText = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Versturen...';
            var fd = new FormData(this);
            fd.append('action', 'crm_send_email');
            fd.append('dealer_id', this.dataset.dealerId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    btn.disabled = false;
                    btn.textContent = origText;
                    if (r.success) {
                        var d = r.data;
                        // Show success message
                        var existing = document.querySelector('.crm-email-success');
                        if (existing) existing.remove();
                        var msg = document.createElement('div');
                        msg.className = 'crm-email-success';
                        msg.textContent = 'E-mail succesvol verstuurd naar ' + d.recipient;
                        form.parentNode.insertBefore(msg, form);
                        setTimeout(function () { msg.remove(); }, 5000);

                        // Add sent email to list
                        var list = document.getElementById('sent-emails-list');
                        list.querySelector('.crm-empty')?.remove();
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-sent-email-item crm-flash';
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<span class="crm-contact-type crm-contact-type-email">E-mail</span>' +
                            '<strong>' + esc(d.author) + '</strong>' +
                            '<time>' + esc(d.date) + '</time>' +
                            '</div>' +
                            '<div class="crm-timeline-subject">' + esc(d.subject) + '</div>' +
                            '<div class="crm-sent-email-recipient">Aan: ' + esc(d.recipient) + '</div>' +
                            '<div class="crm-timeline-body">' + esc(d.message).replace(/\n/g, '<br>') + '</div>';
                        list.prepend(item);
                        form.reset();
                    } else {
                        alert(r.data || 'Fout bij versturen.');
                    }
                })
                .catch(function () {
                    btn.disabled = false;
                    btn.textContent = origText;
                    alert('Fout bij versturen.');
                });
        });
    }

    // Webshop batch scan
    var webshopScanBtn = document.getElementById('crm-webshop-scan-btn');
    var webshopScanStatus = document.getElementById('crm-webshop-scan-status');
    var webshopScanProgress = document.getElementById('crm-webshop-scan-progress');
    var webshopScanFill = document.getElementById('crm-webshop-scan-fill');

    if (webshopScanBtn) {
        var scanning = false;
        var totalToScan = 0;
        var totalScanned = 0;

        webshopScanBtn.addEventListener('click', function () {
            if (scanning) {
                scanning = false;
                webshopScanBtn.textContent = 'Scan starten';
                return;
            }
            scanning = true;
            totalScanned = 0;
            var statusText = webshopScanStatus.textContent.match(/(\d+)/);
            totalToScan = statusText ? parseInt(statusText[1]) : 0;
            webshopScanBtn.textContent = 'Stoppen';
            webshopScanProgress.style.display = 'block';
            runWebshopScan();
        });

        function runWebshopScan() {
            if (!scanning) return;
            webshopScanStatus.textContent = 'Bezig met scannen...';
            var fd = new FormData();
            fd.append('action', 'crm_scan_webshops');
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        totalScanned += d.scanned;
                        var pct = totalToScan > 0 ? Math.min(100, Math.round(((totalToScan - d.remaining) / totalToScan) * 100)) : 100;
                        webshopScanFill.style.width = pct + '%';

                        if (d.remaining > 0 && scanning) {
                            webshopScanStatus.textContent = d.remaining + ' dealers nog te scannen... (' + totalScanned + ' verwerkt)';
                            runWebshopScan();
                        } else {
                            scanning = false;
                            webshopScanBtn.textContent = 'Scan starten';
                            webshopScanStatus.textContent = d.remaining > 0
                                ? d.remaining + ' dealers nog te scannen'
                                : 'Alle dealers zijn gescand! Herlaad de pagina om de resultaten te zien.';
                            if (d.remaining === 0) {
                                webshopScanFill.style.width = '100%';
                            }
                        }
                    } else {
                        scanning = false;
                        webshopScanBtn.textContent = 'Scan starten';
                        webshopScanStatus.textContent = 'Fout: ' + (r.data || 'Onbekende fout');
                    }
                })
                .catch(function () {
                    scanning = false;
                    webshopScanBtn.textContent = 'Scan starten';
                    webshopScanStatus.textContent = 'Verbindingsfout. Probeer opnieuw.';
                });
        }
    }

    // Reset webshop platform
    document.querySelectorAll('.crm-reset-platform-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var platform = this.dataset.platform;
            if (!confirm('Weet je zeker dat je alle "' + platform + '" dealers wilt resetten?\nZe worden dan opnieuw gescand bij de volgende scan.')) return;

            btn.disabled = true;
            btn.textContent = 'Resetten...';

            var fd = new FormData();
            fd.append('action', 'crm_reset_webshop_platform');
            fd.append('platform', platform);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        alert(r.data.message);
                        location.reload();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                        btn.disabled = false;
                        btn.textContent = platform;
                    }
                })
                .catch(function () {
                    alert('Verbindingsfout.');
                    btn.disabled = false;
                    btn.textContent = platform;
                });
        });
    });

    // Single dealer webshop scan
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-scan-single-btn');
        if (!btn) return;
        e.preventDefault();
        var dealerId = btn.dataset.dealerId;
        var cell = document.getElementById('webshop-status-cell');
        if (!cell) return;

        btn.disabled = true;
        if (btn.tagName === 'BUTTON') {
            btn.textContent = 'Scannen...';
        } else {
            btn.textContent = 'Bezig...';
        }

        var fd = new FormData();
        fd.append('action', 'crm_scan_single_dealer');
        fd.append('dealer_id', dealerId);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var d = r.data;
                    var html = '';
                    if (d.status === 'detected' && d.platform) {
                        html = '<span class="crm-webshop-badge crm-webshop-generic" style="animation:crm-flash-bg 1s;">' + esc(d.platform) + '</span>';
                        html += '<br><small style="color:#999;">Zojuist gescand</small>';
                    } else if (d.status === 'none') {
                        html = '<span style="color:#999;">Geen webshop gedetecteerd</span>';
                        html += '<br><small style="color:#999;">Zojuist gescand</small>';
                    } else {
                        html = '<span style="color:#b32d2e;">Fout bij scannen</span>';
                    }
                    html += ' <a href="#" class="crm-scan-single-btn" data-dealer-id="' + dealerId + '" style="margin-left:0.5rem;font-size:0.8rem;">Opnieuw scannen</a>';
                    cell.innerHTML = html;
                } else {
                    alert(r.data || 'Fout bij scannen.');
                    btn.disabled = false;
                    if (btn.tagName === 'BUTTON') btn.textContent = 'Scannen';
                    else btn.textContent = 'Opnieuw scannen';
                }
            })
            .catch(function () {
                alert('Verbindingsfout.');
                btn.disabled = false;
                if (btn.tagName === 'BUTTON') btn.textContent = 'Scannen';
                else btn.textContent = 'Opnieuw scannen';
            });
    });

    // Auto-merge all duplicates
    var autoMergeBtn = document.getElementById('crm-auto-merge-btn');
    var autoMergeStatus = document.getElementById('crm-auto-merge-status');
    var autoMergeProgress = document.getElementById('crm-auto-merge-progress');
    var autoMergeFill = document.getElementById('crm-auto-merge-fill');

    if (autoMergeBtn) {
        var merging = false;
        var totalMerged = 0;
        var totalToMerge = 0;

        autoMergeBtn.addEventListener('click', function () {
            if (merging) {
                merging = false;
                autoMergeBtn.textContent = 'Alles automatisch samenvoegen';
                return;
            }
            if (!confirm('Weet je zeker dat je alle duplicaten automatisch wilt samenvoegen?\n\nBij elk paar wordt de gevulde waarde gekozen. Notities, contactmomenten, merken en tags worden samengevoegd.')) return;

            merging = true;
            totalMerged = 0;
            var statusText = autoMergeStatus.textContent.match(/(\d+)/);
            totalToMerge = statusText ? parseInt(statusText[1]) : 0;
            autoMergeBtn.textContent = 'Stoppen';
            autoMergeProgress.style.display = 'block';
            runAutoMerge();
        });

        function runAutoMerge() {
            if (!merging) return;
            autoMergeStatus.textContent = 'Bezig met samenvoegen...';
            var fd = new FormData();
            fd.append('action', 'crm_auto_merge_duplicates');
            fd.append('batch_size', '10');
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        totalMerged += d.merged;
                        var pct = totalToMerge > 0 ? Math.min(100, Math.round(((totalToMerge - d.remaining) / totalToMerge) * 100)) : 100;
                        autoMergeFill.style.width = pct + '%';

                        if (d.remaining > 0 && d.merged > 0 && merging) {
                            autoMergeStatus.textContent = d.remaining + ' duplicaten resterend... (' + totalMerged + ' samengevoegd)';
                            runAutoMerge();
                        } else {
                            merging = false;
                            autoMergeBtn.textContent = 'Alles automatisch samenvoegen';
                            if (d.remaining === 0) {
                                autoMergeStatus.textContent = 'Klaar! ' + totalMerged + ' duplicaten samengevoegd. Pagina wordt herladen...';
                                autoMergeFill.style.width = '100%';
                                setTimeout(function () { location.reload(); }, 1500);
                            } else {
                                autoMergeStatus.textContent = d.remaining + ' duplicaten resterend. ' + totalMerged + ' samengevoegd.';
                            }
                        }
                    } else {
                        merging = false;
                        autoMergeBtn.textContent = 'Alles automatisch samenvoegen';
                        autoMergeStatus.textContent = 'Fout: ' + (r.data || 'Onbekende fout');
                    }
                })
                .catch(function () {
                    merging = false;
                    autoMergeBtn.textContent = 'Alles automatisch samenvoegen';
                    autoMergeStatus.textContent = 'Verbindingsfout. Probeer opnieuw.';
                });
        }
    }

    // Dismiss duplicate
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-dismiss-duplicate-btn');
        if (!btn) return;
        var fd = new FormData();
        fd.append('action', 'crm_dismiss_duplicate');
        fd.append('dealer_id_1', btn.dataset.id1);
        fd.append('dealer_id_2', btn.dataset.id2);
        fd.append('nonce', dealerCRM.nonce);
        btn.disabled = true;
        btn.textContent = 'Bezig...';
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var card = btn.closest('.crm-duplicate-card');
                    if (card) {
                        card.style.transition = 'opacity 0.3s';
                        card.style.opacity = '0';
                        setTimeout(function () { card.remove(); }, 300);
                    }
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Negeren';
                    alert(r.data || 'Er is een fout opgetreden.');
                }
            });
    });

    // New dealer form
    var newDealerForm = document.getElementById('crm-new-dealer-form');
    if (newDealerForm) {
        newDealerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var btn = document.getElementById('crm-save-new-dealer');
            btn.disabled = true;
            btn.textContent = 'Opslaan...';

            var fd = new FormData();
            fd.append('action', 'crm_create_dealer');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('name', newDealerForm.querySelector('[name="name"]').value);

            ['street', 'postcode', 'city', 'phone', 'email', 'website', 'status'].forEach(function (f) {
                var el = newDealerForm.querySelector('[name="' + f + '"]');
                if (el) fd.append(f, el.value);
            });

            newDealerForm.querySelectorAll('[name="brands[]"]:checked').forEach(function (cb) {
                fd.append('brands[]', cb.value);
            });
            newDealerForm.querySelectorAll('[name="tags[]"]:checked').forEach(function (cb) {
                fd.append('tags[]', cb.value);
            });

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        window.location = r.data.redirect;
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                        btn.disabled = false;
                        btn.textContent = 'Dealer opslaan';
                    }
                })
                .catch(function () {
                    alert('Verbindingsfout.');
                    btn.disabled = false;
                    btn.textContent = 'Dealer opslaan';
                });
        });
    }

    // Office postcode: Save & geocode
    var officeSaveBtn = document.getElementById('crm-office-save-btn');
    if (officeSaveBtn) {
        officeSaveBtn.addEventListener('click', function () {
            var postcodeInput = document.getElementById('crm-office-postcode');
            var statusEl = document.getElementById('crm-office-status');
            var postcode = postcodeInput.value.trim();

            officeSaveBtn.disabled = true;
            officeSaveBtn.textContent = 'Opslaan...';
            if (statusEl) statusEl.textContent = '⏳ Bezig met geocoderen...';

            var fd = new FormData();
            fd.append('action', 'crm_save_office_postcode');
            fd.append('postcode', postcode);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    officeSaveBtn.disabled = false;
                    officeSaveBtn.textContent = 'Opslaan & geocoderen';
                    if (r.success) {
                        if (statusEl) {
                            if (r.data.lat && r.data.lng) {
                                statusEl.textContent = '✓ Locatie bekend (' + (Math.round(r.data.lat * 10000) / 10000) + ', ' + (Math.round(r.data.lng * 10000) / 10000) + ')';
                                statusEl.style.color = '#46b450';
                            } else {
                                statusEl.textContent = 'Nog niet ingesteld';
                                statusEl.style.color = '#666';
                            }
                        }
                    } else {
                        if (statusEl) {
                            statusEl.textContent = '⚠️ ' + (r.data || 'Onbekende fout');
                            statusEl.style.color = '#d63638';
                        }
                    }
                })
                .catch(function () {
                    officeSaveBtn.disabled = false;
                    officeSaveBtn.textContent = 'Opslaan & geocoderen';
                    if (statusEl) {
                        statusEl.textContent = '⚠️ Verbindingsfout';
                        statusEl.style.color = '#d63638';
                    }
                });
        });
    }

    // Slack: Save settings
    var slackSaveBtn = document.getElementById('crm-slack-save-btn');
    if (slackSaveBtn) {
        slackSaveBtn.addEventListener('click', function () {
            var webhookUrl = document.getElementById('crm-slack-webhook-url').value.trim();
            var events = [];
            document.querySelectorAll('.crm-slack-event:checked').forEach(function (cb) {
                events.push(cb.value);
            });

            slackSaveBtn.disabled = true;
            slackSaveBtn.textContent = 'Opslaan...';

            var fd = new FormData();
            fd.append('action', 'crm_slack_save_settings');
            fd.append('webhook_url', webhookUrl);
            fd.append('events', events.join(','));
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    slackSaveBtn.disabled = false;
                    slackSaveBtn.textContent = 'Opslaan';
                    if (r.success) {
                        alert(r.data.message);
                        location.reload();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                })
                .catch(function () {
                    slackSaveBtn.disabled = false;
                    slackSaveBtn.textContent = 'Opslaan';
                    alert('Verbindingsfout.');
                });
        });
    }

    // Slack: Test connection
    var slackTestBtn = document.getElementById('crm-slack-test-btn');
    if (slackTestBtn) {
        slackTestBtn.addEventListener('click', function () {
            slackTestBtn.disabled = true;
            slackTestBtn.textContent = 'Verzenden...';

            var fd = new FormData();
            fd.append('action', 'crm_slack_test');
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    slackTestBtn.disabled = false;
                    slackTestBtn.textContent = 'Test versturen';
                    alert(r.success ? r.data.message : ('Fout: ' + (r.data || 'Onbekende fout')));
                })
                .catch(function () {
                    slackTestBtn.disabled = false;
                    slackTestBtn.textContent = 'Test versturen';
                    alert('Verbindingsfout.');
                });
        });
    }

    // Mailchimp: Save API key
    var mcSaveBtn = document.getElementById('crm-mailchimp-save-btn');
    if (mcSaveBtn) {
        mcSaveBtn.addEventListener('click', function () {
            var apiKey = document.getElementById('crm-mailchimp-api-key').value.trim();
            mcSaveBtn.disabled = true;
            mcSaveBtn.textContent = 'Opslaan...';

            var fd = new FormData();
            fd.append('action', 'crm_mailchimp_save_key');
            fd.append('api_key', apiKey);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    mcSaveBtn.disabled = false;
                    mcSaveBtn.textContent = 'Opslaan';
                    if (r.success) {
                        alert(r.data.message);
                        location.reload();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                })
                .catch(function () {
                    mcSaveBtn.disabled = false;
                    mcSaveBtn.textContent = 'Opslaan';
                    alert('Verbindingsfout.');
                });
        });
    }

    // Mailchimp: Sync single dealer
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-mailchimp-sync-btn');
        if (!btn) return;
        e.preventDefault();
        var dealerId = btn.dataset.dealerId;
        btn.disabled = true;
        btn.textContent = 'Synchroniseren...';

        var fd = new FormData();
        fd.append('action', 'crm_mailchimp_sync_dealer');
        fd.append('dealer_id', dealerId);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                btn.disabled = false;
                btn.textContent = 'Synchroniseren';
                if (r.success) {
                    location.reload();
                } else {
                    alert('Fout: ' + (r.data || 'Onbekende fout'));
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.textContent = 'Synchroniseren';
                alert('Verbindingsfout.');
            });
    });

    // Mailchimp: Batch sync
    var mcBatchBtn = document.getElementById('crm-mailchimp-batch-btn');
    var mcBatchStatus = document.getElementById('crm-mailchimp-batch-status');
    var mcBatchProgress = document.getElementById('crm-mailchimp-batch-progress');
    var mcBatchFill = document.getElementById('crm-mailchimp-batch-fill');

    if (mcBatchBtn) {
        var mcSyncing = false;
        var mcTotalSynced = 0;
        var mcTotalToSync = 0;

        mcBatchBtn.addEventListener('click', function () {
            if (mcSyncing) {
                mcSyncing = false;
                mcBatchBtn.textContent = 'Batch synchronisatie';
                return;
            }
            mcSyncing = true;
            mcTotalSynced = 0;
            mcBatchBtn.textContent = 'Stoppen';
            mcBatchProgress.style.display = 'block';
            runMcBatchSync();
        });

        function runMcBatchSync() {
            if (!mcSyncing) return;
            mcBatchStatus.textContent = 'Bezig met synchroniseren...';
            var fd = new FormData();
            fd.append('action', 'crm_mailchimp_sync_batch');
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        mcTotalSynced += d.synced_dealers;
                        if (mcTotalToSync === 0 && d.remaining > 0) {
                            mcTotalToSync = d.remaining + mcTotalSynced;
                        }
                        var pct = mcTotalToSync > 0 ? Math.min(100, Math.round((mcTotalSynced / mcTotalToSync) * 100)) : 100;
                        mcBatchFill.style.width = pct + '%';

                        if (d.remaining > 0 && d.synced_dealers > 0 && mcSyncing) {
                            mcBatchStatus.textContent = d.remaining + ' dealers resterend... (' + mcTotalSynced + ' gesynchroniseerd, ' + d.total_campaigns + ' campagnes)';
                            runMcBatchSync();
                        } else {
                            mcSyncing = false;
                            mcBatchBtn.textContent = 'Batch synchronisatie';
                            if (d.remaining === 0) {
                                mcBatchStatus.textContent = 'Klaar! ' + mcTotalSynced + ' dealers gesynchroniseerd.';
                                mcBatchFill.style.width = '100%';
                                setTimeout(function () { location.reload(); }, 1500);
                            } else {
                                mcBatchStatus.textContent = mcTotalSynced + ' dealers gesynchroniseerd. ' + d.remaining + ' resterend.';
                            }
                        }
                    } else {
                        mcSyncing = false;
                        mcBatchBtn.textContent = 'Batch synchronisatie';
                        mcBatchStatus.textContent = 'Fout: ' + (r.data || 'Onbekende fout');
                    }
                })
                .catch(function () {
                    mcSyncing = false;
                    mcBatchBtn.textContent = 'Batch synchronisatie';
                    mcBatchStatus.textContent = 'Verbindingsfout. Probeer opnieuw.';
                });
        }
    }

    // ── Brand Module ──

    // New brand form
    var newBrandForm = document.getElementById('crm-new-brand-form');
    if (newBrandForm) {
        newBrandForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_create_brand');
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        window.location = (dealerCRM.admin_url || '/wp-admin/admin.php') + '?page=dealer-crm-brands&action=view&id=' + r.data.id;
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                });
        });
    }

    // Clickable brand rows
    document.querySelectorAll('.crm-brand-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.tagName === 'A') return;
            window.location = this.dataset.href;
        });
    });

    // Brand tracker form
    var brandTrackerForm = document.getElementById('brand-tracker-form');
    if (brandTrackerForm) {
        brandTrackerForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_update_brand');
            fd.append('brand_id', this.dataset.brandId);
            fd.append('nonce', dealerCRM.nonce);
            var btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Opslaan...';
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) location.reload();
                    else {
                        alert(r.data || 'Fout bij opslaan.');
                        btn.disabled = false;
                        btn.textContent = 'Opslaan';
                    }
                })
                .catch(function () {
                    alert('Verbindingsfout.');
                    btn.disabled = false;
                    btn.textContent = 'Opslaan';
                });
        });
    }

    // Brand info form (sidebar)
    var brandInfoForm = document.getElementById('brand-info-form');
    if (brandInfoForm) {
        brandInfoForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_update_brand');
            fd.append('brand_id', this.dataset.brandId);
            fd.append('nonce', dealerCRM.nonce);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) location.reload();
                    else alert(r.data || 'Fout bij opslaan.');
                });
        });
    }

    // Brand note add
    var brandNoteForm = document.getElementById('add-brand-note-form');
    if (brandNoteForm) {
        brandNoteForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_add_brand_note');
            fd.append('brand_id', this.dataset.brandId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        var list = document.getElementById('brand-notes-list');
                        list.querySelector('.crm-empty')?.remove();
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-flash';
                        item.dataset.id = d.id;
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<strong>' + esc(d.author) + '</strong>' +
                            '<time>' + esc(d.date) + '</time>' +
                            '<button class="crm-delete-btn crm-delete-brand-note-btn" data-id="' + d.id + '" title="Verwijderen">&times;</button>' +
                            '</div>' +
                            '<div class="crm-timeline-body">' + esc(d.content).replace(/\n/g, '<br>') + '</div>';
                        list.prepend(item);
                        form.reset();
                        updateBrandTabCount('brand-notes', 1);
                    }
                });
        });
    }

    // Brand note delete
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-delete-brand-note-btn');
        if (!btn) return;
        if (!confirm('Weet je het zeker?')) return;
        var fd = new FormData();
        fd.append('action', 'crm_delete_brand_note');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    btn.closest('.crm-timeline-item').remove();
                    updateBrandTabCount('brand-notes', -1);
                }
            });
    });

    // Brand followup add
    var brandFollowupForm = document.getElementById('add-brand-followup-form');
    if (brandFollowupForm) {
        brandFollowupForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_add_brand_followup');
            fd.append('brand_id', this.dataset.brandId);
            fd.append('nonce', dealerCRM.nonce);
            var form = this;
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        var d = r.data;
                        var list = document.getElementById('brand-followups-list');
                        list.querySelector('.crm-empty')?.remove();
                        var today = new Date().toISOString().slice(0, 10);
                        var isOverdue = (d.status === 'open' && d.due_date < today);
                        var badgeClass = isOverdue ? 'crm-followup-badge-verlopen' : 'crm-followup-badge-' + d.status;
                        var badgeText = isOverdue ? 'Verlopen' : d.status.charAt(0).toUpperCase() + d.status.slice(1);
                        var item = document.createElement('div');
                        item.className = 'crm-timeline-item crm-followup-item crm-flash' + (isOverdue ? ' crm-followup-overdue' : '');
                        item.dataset.id = d.id;
                        item.innerHTML =
                            '<div class="crm-timeline-meta">' +
                            '<span class="crm-followup-badge ' + badgeClass + '">' + esc(badgeText) + '</span>' +
                            '<strong>' + esc(d.assignee_name) + '</strong>' +
                            '<time>' + formatDate(d.due_date) + '</time>' +
                            '<button class="button button-small crm-complete-brand-followup-btn" data-id="' + d.id + '">Voltooien</button>' +
                            '<button class="crm-delete-btn crm-delete-brand-followup-btn" data-id="' + d.id + '" title="Verwijderen">&times;</button>' +
                            '</div>' +
                            '<div class="crm-timeline-subject">' + esc(d.title) + '</div>' +
                            (d.description ? '<div class="crm-timeline-body">' + esc(d.description).replace(/\n/g, '<br>') + '</div>' : '') +
                            '<div class="crm-followup-creator">Aangemaakt door ' + esc(d.creator_name) + '</div>';
                        list.prepend(item);
                        form.reset();
                        form.querySelector('[name="due_date"]').value = new Date(Date.now() + 7 * 86400000).toISOString().slice(0, 10);
                        updateBrandTabCount('brand-followups', 1);
                    } else {
                        alert(r.data || 'Fout bij toevoegen.');
                    }
                });
        });
    }

    // Brand followup complete
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-complete-brand-followup-btn');
        if (!btn) return;
        var fd = new FormData();
        fd.append('action', 'crm_complete_brand_followup');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        btn.disabled = true;
        btn.textContent = 'Bezig...';
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    var item = btn.closest('.crm-timeline-item');
                    item.classList.remove('crm-followup-overdue');
                    var badge = item.querySelector('.crm-followup-badge');
                    if (badge) {
                        badge.className = 'crm-followup-badge crm-followup-badge-voltooid';
                        badge.textContent = 'Voltooid';
                    }
                    btn.remove();
                } else {
                    btn.disabled = false;
                    btn.textContent = 'Voltooien';
                    alert(r.data || 'Fout bij voltooien.');
                }
            });
    });

    // Brand followup delete
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-delete-brand-followup-btn');
        if (!btn) return;
        if (!confirm('Weet je het zeker?')) return;
        var fd = new FormData();
        fd.append('action', 'crm_delete_brand_followup');
        fd.append('id', btn.dataset.id);
        fd.append('nonce', dealerCRM.nonce);
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    btn.closest('.crm-timeline-item').remove();
                    updateBrandTabCount('brand-followups', -1);
                }
            });
    });

    // Brand import
    var importBrandsBtn = document.getElementById('crm-import-brands-btn');
    var importBrandsFile = document.getElementById('crm-import-brands-file');
    if (importBrandsBtn && importBrandsFile) {
        importBrandsBtn.addEventListener('click', function () {
            importBrandsFile.click();
        });
        importBrandsFile.addEventListener('change', function () {
            var file = this.files[0];
            if (!file) return;
            if (!file.name.endsWith('.xlsx')) {
                alert('Selecteer een .xlsx bestand.');
                return;
            }
            if (!confirm('Wil je "' + file.name + '" importeren?')) {
                this.value = '';
                return;
            }
            importBrandsBtn.disabled = true;
            importBrandsBtn.textContent = 'Importeren...';
            var statusEl = document.getElementById('crm-brand-import-status');
            var msgEl = document.getElementById('crm-brand-import-msg');
            statusEl.style.display = 'block';
            msgEl.textContent = 'Bezig met importeren...';

            var fd = new FormData();
            fd.append('action', 'crm_import_brands');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('file', file);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        msgEl.textContent = r.data.message + ' Pagina wordt herladen...';
                        msgEl.style.color = '#155724';
                        setTimeout(function () { location.reload(); }, 1500);
                    } else {
                        msgEl.textContent = 'Fout: ' + (r.data || 'Onbekende fout');
                        msgEl.style.color = '#721c24';
                        importBrandsBtn.disabled = false;
                        importBrandsBtn.textContent = 'Importeren uit Excel';
                    }
                })
                .catch(function () {
                    msgEl.textContent = 'Verbindingsfout. Probeer opnieuw.';
                    msgEl.style.color = '#721c24';
                    importBrandsBtn.disabled = false;
                    importBrandsBtn.textContent = 'Importeren uit Excel';
                });
            this.value = '';
        });
    }

    function updateBrandTabCount(tabType, delta) {
        var tab = document.querySelector('.crm-tab[data-tab="' + tabType + '"]');
        if (!tab) return;
        var m = tab.textContent.match(/\((\d+)\)/);
        if (m) {
            var n = Math.max(0, parseInt(m[1]) + delta);
            tab.textContent = tab.textContent.replace(/\(\d+\)/, '(' + n + ')');
        }
    }

    // ── Campaigns ──

    // Dealer list: select all checkbox
    var selectAll = document.getElementById('crm-select-all-dealers');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.crm-dealer-checkbox').forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            updateCampaignBar();
        });
    }

    // Dealer list: individual checkboxes
    document.querySelectorAll('.crm-dealer-checkbox').forEach(function (cb) {
        cb.addEventListener('change', updateCampaignBar);
    });

    // Prevent row click when clicking checkbox
    document.querySelectorAll('.crm-checkbox-cell').forEach(function (cell) {
        cell.addEventListener('click', function (e) {
            e.stopPropagation();
        });
    });

    function updateCampaignBar() {
        var checked = document.querySelectorAll('.crm-dealer-checkbox:checked');
        var bar = document.getElementById('crm-campaign-bar');
        var countEl = document.getElementById('crm-selected-count');
        if (!bar) return;
        if (checked.length > 0) {
            bar.style.display = 'flex';
            countEl.textContent = checked.length;
        } else {
            bar.style.display = 'none';
        }
    }

    // Add selected dealers to campaign from dealer list
    var addToCampaignBtn = document.getElementById('crm-add-to-campaign-btn');
    if (addToCampaignBtn) {
        addToCampaignBtn.addEventListener('click', function () {
            var campaignId = document.getElementById('crm-campaign-select').value;
            if (!campaignId) { alert('Kies eerst een campagne.'); return; }

            var ids = [];
            document.querySelectorAll('.crm-dealer-checkbox:checked').forEach(function (cb) {
                ids.push(cb.value);
            });
            if (ids.length === 0) return;

            var fd = new FormData();
            fd.append('action', 'crm_add_dealers_to_campaign');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('campaign_id', campaignId);
            ids.forEach(function (id) { fd.append('dealer_ids[]', id); });

            addToCampaignBtn.disabled = true;
            addToCampaignBtn.textContent = 'Toevoegen...';

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        alert(r.data.message);
                        document.querySelectorAll('.crm-dealer-checkbox:checked').forEach(function (cb) { cb.checked = false; });
                        if (selectAll) selectAll.checked = false;
                        updateCampaignBar();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                    addToCampaignBtn.disabled = false;
                    addToCampaignBtn.textContent = 'Toevoegen aan campagne';
                });
        });
    }

    // Campaign form (create/edit)
    var campaignForm = document.getElementById('crm-campaign-form');
    if (campaignForm) {
        campaignForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(this);
            fd.append('action', 'crm_save_campaign');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('campaign_id', this.dataset.campaignId || '0');

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        window.location = (dealerCRM.admin_url || '/wp-admin/admin.php') + '?page=dealer-crm-campaigns&action=view&id=' + r.data.id;
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                });
        });
    }

    // Campaign detail: select all checkbox
    var campDealerSelectAll = document.getElementById('crm-camp-dealer-select-all');
    if (campDealerSelectAll) {
        campDealerSelectAll.addEventListener('change', function () {
            document.querySelectorAll('.crm-camp-remove-cb').forEach(function (cb) {
                cb.checked = campDealerSelectAll.checked;
            });
            updateRemoveBtn();
        });
    }

    // Campaign detail: individual checkboxes
    document.querySelectorAll('.crm-camp-remove-cb').forEach(function (cb) {
        cb.addEventListener('change', updateRemoveBtn);
    });

    function updateRemoveBtn() {
        var btn = document.getElementById('crm-remove-selected-campaign-dealers-btn');
        if (!btn) return;
        var checked = document.querySelectorAll('.crm-camp-remove-cb:checked');
        if (checked.length > 0) {
            btn.style.display = '';
            btn.textContent = checked.length + ' verwijderen';
        } else {
            btn.style.display = 'none';
        }
    }

    // Campaign detail: bulk remove
    var removeSelectedBtn = document.getElementById('crm-remove-selected-campaign-dealers-btn');
    if (removeSelectedBtn) {
        removeSelectedBtn.addEventListener('click', function () {
            var ids = [];
            document.querySelectorAll('.crm-camp-remove-cb:checked').forEach(function (cb) {
                ids.push(cb.value);
            });
            if (!ids.length) return;
            if (!confirm(ids.length + ' dealer' + (ids.length !== 1 ? 's' : '') + ' verwijderen uit deze campagne?')) return;

            var fd = new FormData();
            fd.append('action', 'crm_remove_campaign_dealers_bulk');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('campaign_id', this.dataset.campaignId);
            ids.forEach(function (id) { fd.append('dealer_ids[]', id); });

            removeSelectedBtn.disabled = true;
            removeSelectedBtn.textContent = 'Verwijderen...';

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        document.querySelectorAll('.crm-camp-remove-cb:checked').forEach(function (cb) {
                            cb.closest('tr').remove();
                        });
                        updateCampaignTabCount('campaign-dealers', -r.data.removed);
                        if (campDealerSelectAll) campDealerSelectAll.checked = false;
                        updateRemoveBtn();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                    removeSelectedBtn.disabled = false;
                });
        });
    }

    // Campaign detail: remove single dealer
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.crm-remove-campaign-dealer-btn');
        if (!btn) return;
        if (!confirm('Dealer verwijderen uit deze campagne?')) return;

        var fd = new FormData();
        fd.append('action', 'crm_remove_campaign_dealer');
        fd.append('nonce', dealerCRM.nonce);
        fd.append('campaign_id', btn.dataset.campaignId);
        fd.append('dealer_id', btn.dataset.dealerId);

        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) {
                    btn.closest('tr').remove();
                    updateCampaignTabCount('campaign-dealers', -1);
                    updateRemoveBtn();
                }
            });
    });

    // Campaign detail: search dealers to add
    var campaignSearchBtn = document.getElementById('crm-campaign-search-btn');
    if (campaignSearchBtn) {
        campaignSearchBtn.addEventListener('click', function () {
            var campId = this.dataset.campaignId;
            var fd = new FormData();
            fd.append('action', 'crm_search_campaign_dealers');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('campaign_id', campId);
            fd.append('search', document.getElementById('camp-filter-search').value);
            fd.append('brand', document.getElementById('camp-filter-brand').value);
            fd.append('city', document.getElementById('camp-filter-city').value);
            fd.append('status', document.getElementById('camp-filter-status').value);
            fd.append('webshop', document.getElementById('camp-filter-webshop').value);
            fd.append('tag_id', document.getElementById('camp-filter-tag').value);

            campaignSearchBtn.disabled = true;
            campaignSearchBtn.textContent = 'Zoeken...';

            var resultsEl = document.getElementById('crm-campaign-search-results');

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    campaignSearchBtn.disabled = false;
                    campaignSearchBtn.textContent = 'Zoeken';

                    if (!r.success || !r.data.dealers.length) {
                        resultsEl.innerHTML = '<p class="crm-empty">Geen dealers gevonden (of alle resultaten zitten al in de campagne).</p>';
                        return;
                    }

                    var html = '<div style="margin-bottom:0.5rem;display:flex;align-items:center;gap:0.5rem;">' +
                        '<span>' + r.data.count + ' dealer' + (r.data.count !== 1 ? 's' : '') + ' gevonden</span>' +
                        '<button class="button button-primary button-small" id="crm-add-all-search-results">Alle ' + r.data.count + ' toevoegen</button>' +
                        '</div>';
                    html += '<table class="crm-table widefat striped"><thead><tr>' +
                        '<th style="width:30px;"><input type="checkbox" id="crm-camp-select-all" checked></th>' +
                        '<th>Naam</th><th>Plaats</th><th>E-mail</th><th>Status</th>' +
                        '</tr></thead><tbody>';

                    r.data.dealers.forEach(function (d) {
                        html += '<tr>' +
                            '<td><input type="checkbox" class="crm-camp-dealer-cb" value="' + d.id + '" checked></td>' +
                            '<td>' + escHtml(d.name) + '</td>' +
                            '<td>' + escHtml(d.city || '') + '</td>' +
                            '<td>' + escHtml(d.email || '') + '</td>' +
                            '<td>' + escHtml(d.status || '') + '</td>' +
                            '</tr>';
                    });
                    html += '</tbody></table>';

                    resultsEl.innerHTML = html;

                    // Select all toggle
                    document.getElementById('crm-camp-select-all').addEventListener('change', function () {
                        var checked = this.checked;
                        resultsEl.querySelectorAll('.crm-camp-dealer-cb').forEach(function (cb) { cb.checked = checked; });
                    });

                    // Add selected
                    document.getElementById('crm-add-all-search-results').addEventListener('click', function () {
                        var ids = [];
                        resultsEl.querySelectorAll('.crm-camp-dealer-cb:checked').forEach(function (cb) { ids.push(cb.value); });
                        if (!ids.length) { alert('Geen dealers geselecteerd.'); return; }

                        var addFd = new FormData();
                        addFd.append('action', 'crm_add_dealers_to_campaign');
                        addFd.append('nonce', dealerCRM.nonce);
                        addFd.append('campaign_id', campId);
                        ids.forEach(function (id) { addFd.append('dealer_ids[]', id); });

                        this.disabled = true;
                        this.textContent = 'Toevoegen...';
                        var addBtn = this;

                        fetch(dealerCRM.ajax_url, { method: 'POST', body: addFd })
                            .then(function (r) { return r.json(); })
                            .then(function (r) {
                                if (r.success) {
                                    alert(r.data.message);
                                    // Re-trigger search to update results
                                    campaignSearchBtn.click();
                                    updateCampaignTabCount('campaign-dealers', r.data.added);
                                } else {
                                    alert('Fout: ' + (r.data || 'Onbekende fout'));
                                    addBtn.disabled = false;
                                    addBtn.textContent = 'Alle toevoegen';
                                }
                            });
                    });
                });
        });
    }

    // Dealer list CSV export
    var exportDealersBtn = document.getElementById('crm-export-dealers-btn');
    if (exportDealersBtn) {
        exportDealersBtn.addEventListener('click', function () {
            var params = new URLSearchParams(window.location.search);
            var fd = new FormData();
            fd.append('action', 'crm_export_dealers_csv');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('search', params.get('s') || '');
            fd.append('brand', params.get('brand') || '');
            fd.append('city', params.get('city') || '');
            fd.append('status', params.get('status') || '');
            fd.append('tag_id', params.get('tag_id') || '0');
            fd.append('webshop', params.get('webshop') || '');
            fd.append('orderby', params.get('orderby') || 'name');
            fd.append('order', params.get('order') || 'ASC');

            exportDealersBtn.disabled = true;
            exportDealersBtn.textContent = 'Exporteren...';

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    exportDealersBtn.disabled = false;
                    exportDealersBtn.textContent = 'Exporteer resultaat';
                    if (r.success) {
                        var blob = new Blob([r.data.csv], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = r.data.filename;
                        link.click();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                });
        });
    }

    // Campaign CSV export
    var exportBtn = document.getElementById('crm-export-campaign-btn');
    if (exportBtn) {
        exportBtn.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'crm_export_campaign_csv');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('campaign_id', this.dataset.campaignId);

            exportBtn.disabled = true;
            exportBtn.textContent = 'Exporteren...';

            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    exportBtn.disabled = false;
                    exportBtn.textContent = 'Exporteren als CSV';
                    if (r.success) {
                        var blob = new Blob([r.data.csv], { type: 'text/csv;charset=utf-8;' });
                        var link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = r.data.filename;
                        link.click();
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                    }
                });
        });
    }

    function updateCampaignTabCount(tabType, delta) {
        var tab = document.querySelector('.crm-tab[data-tab="' + tabType + '"]');
        if (!tab) return;
        var m = tab.textContent.match(/\((\d+)\)/);
        if (m) {
            var n = Math.max(0, parseInt(m[1]) + delta);
            tab.textContent = tab.textContent.replace(/\(\d+\)/, '(' + n + ')');
        }
    }

    // ── Brand Merge ──
    var mergeBrandBtn = document.getElementById('crm-merge-brand-btn');
    if (mergeBrandBtn) {
        var mergeModal = document.getElementById('crm-merge-brand-modal');
        var mergeSearch = document.getElementById('crm-merge-brand-search');
        var mergeResults = document.getElementById('crm-merge-brand-results');
        var mergeSelected = document.getElementById('crm-merge-brand-selected');
        var mergeSelectedName = document.getElementById('crm-merge-brand-selected-name');
        var mergeSelectedId = document.getElementById('crm-merge-brand-selected-id');
        var mergeConfirm = document.getElementById('crm-merge-brand-confirm');
        var brandId = mergeBrandBtn.dataset.brandId;
        var brandName = mergeBrandBtn.dataset.brandName;
        var mergeSearchTimeout;

        mergeBrandBtn.addEventListener('click', function () {
            mergeModal.style.display = 'block';
            mergeSearch.value = '';
            mergeResults.innerHTML = '';
            mergeSelected.style.display = 'none';
            mergeConfirm.disabled = true;
            mergeSelectedId.value = '';
            setTimeout(function () { mergeSearch.focus(); }, 100);
        });

        mergeModal.querySelectorAll('.crm-modal-close, .crm-modal-overlay').forEach(function (el) {
            el.addEventListener('click', function () {
                mergeModal.style.display = 'none';
            });
        });

        mergeSearch.addEventListener('input', function () {
            var q = this.value.trim();
            clearTimeout(mergeSearchTimeout);
            if (q.length < 2) { mergeResults.innerHTML = ''; return; }
            mergeSearchTimeout = setTimeout(function () {
                var fd = new FormData();
                fd.append('action', 'crm_search_brands');
                fd.append('nonce', dealerCRM.nonce);
                fd.append('search', q);
                fd.append('exclude_id', brandId);
                fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                    .then(function (r) { return r.json(); })
                    .then(function (r) {
                        mergeResults.innerHTML = '';
                        if (r.success && r.data.length > 0) {
                            r.data.forEach(function (b) {
                                var div = document.createElement('div');
                                div.className = 'crm-merge-result-item';
                                div.textContent = b.name;
                                div.dataset.id = b.id;
                                div.dataset.name = b.name;
                                div.addEventListener('click', function () {
                                    mergeSelectedId.value = this.dataset.id;
                                    mergeSelectedName.textContent = this.dataset.name;
                                    mergeSelected.style.display = 'block';
                                    mergeConfirm.disabled = false;
                                    mergeResults.innerHTML = '';
                                    mergeSearch.value = '';
                                });
                                mergeResults.appendChild(div);
                            });
                        } else if (r.success) {
                            mergeResults.innerHTML = '<div class="crm-merge-result-empty">Geen merken gevonden.</div>';
                        }
                    });
            }, 300);
        });

        mergeConfirm.addEventListener('click', function () {
            var secondaryId = mergeSelectedId.value;
            var secondaryName = mergeSelectedName.textContent;
            if (!secondaryId) return;
            if (!confirm('Weet je zeker dat je "' + secondaryName + '" wilt samenvoegen met "' + brandName + '"?\n\nAlle dealers, notities en follow-ups worden overgenomen. "' + secondaryName + '" wordt daarna verwijderd.')) return;

            mergeConfirm.disabled = true;
            mergeConfirm.textContent = 'Bezig...';

            var fd = new FormData();
            fd.append('action', 'crm_merge_brands');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('primary_id', brandId);
            fd.append('secondary_id', secondaryId);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        alert(r.data.message);
                        window.location = r.data.redirect;
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                        mergeConfirm.disabled = false;
                        mergeConfirm.textContent = 'Samenvoegen';
                    }
                })
                .catch(function () {
                    alert('Verbindingsfout.');
                    mergeConfirm.disabled = false;
                    mergeConfirm.textContent = 'Samenvoegen';
                });
        });
    }

    // ── Brand Delete ──
    var deleteBrandBtn = document.getElementById('crm-delete-brand-btn');
    if (deleteBrandBtn) {
        deleteBrandBtn.addEventListener('click', function () {
            var bName = this.dataset.brandName;
            var bId = this.dataset.brandId;
            if (!confirm('Weet je zeker dat je het merk "' + bName + '" wilt verwijderen?\n\nAlle koppelingen met dealers, notities en follow-ups worden ook verwijderd. Dit kan niet ongedaan worden gemaakt.')) return;

            deleteBrandBtn.disabled = true;
            deleteBrandBtn.textContent = 'Verwijderen...';

            var fd = new FormData();
            fd.append('action', 'crm_delete_brand');
            fd.append('nonce', dealerCRM.nonce);
            fd.append('brand_id', bId);
            fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (r.success) {
                        alert(r.data.message);
                        window.location = r.data.redirect;
                    } else {
                        alert('Fout: ' + (r.data || 'Onbekende fout'));
                        deleteBrandBtn.disabled = false;
                        deleteBrandBtn.textContent = 'Merk verwijderen';
                    }
                })
                .catch(function () {
                    alert('Verbindingsfout.');
                    deleteBrandBtn.disabled = false;
                    deleteBrandBtn.textContent = 'Merk verwijderen';
                });
        });
    }

    // ── Dealer Trash / Restore / Permanent delete ──

    function getRowInfo(btn) {
        var row = btn.closest('tr.crm-dealer-row');
        if (!row) return null;
        return {
            row: row,
            id: row.dataset.dealerId,
            name: row.dataset.dealerName || 'deze dealer',
        };
    }

    function removeRowWithFade(row) {
        row.style.transition = 'opacity 0.3s';
        row.style.opacity = '0';
        setTimeout(function () { row.remove(); }, 300);
    }

    function ajaxCall(action, data, onSuccess, onError) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', dealerCRM.nonce);
        Object.keys(data).forEach(function (k) {
            var val = data[k];
            if (Array.isArray(val)) {
                val.forEach(function (v) { fd.append(k + '[]', v); });
            } else {
                fd.append(k, val);
            }
        });
        fetch(dealerCRM.ajax_url, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (r) {
                if (r.success) { onSuccess(r.data); }
                else { (onError || function (m) { alert('Fout: ' + m); })(r.data || 'Onbekende fout'); }
            })
            .catch(function () {
                (onError || function () { alert('Verbindingsfout.'); })('Verbindingsfout');
            });
    }

    // Per-row: Move to trash
    document.querySelectorAll('.crm-trash-dealer-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var info = getRowInfo(btn);
            if (!info) return;
            if (!confirm('Weet je zeker dat je "' + info.name + '" naar de prullenbak wilt verplaatsen?\n\nJe kunt de dealer later herstellen vanuit de prullenbak.')) return;
            btn.disabled = true;
            ajaxCall('crm_trash_dealer', { dealer_id: info.id }, function () {
                removeRowWithFade(info.row);
            }, function (m) { alert('Fout: ' + m); btn.disabled = false; });
        });
    });

    // Per-row: Restore from trash
    document.querySelectorAll('.crm-restore-dealer-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var info = getRowInfo(btn);
            if (!info) return;
            btn.disabled = true;
            ajaxCall('crm_restore_dealer', { dealer_id: info.id }, function () {
                removeRowWithFade(info.row);
            }, function (m) { alert('Fout: ' + m); btn.disabled = false; });
        });
    });

    // Per-row: Permanent delete
    document.querySelectorAll('.crm-delete-permanent-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var info = getRowInfo(btn);
            if (!info) return;
            if (!confirm('PERMANENT verwijderen: "' + info.name + '"?\n\nAlle notities, follow-ups, contact log en koppelingen worden verwijderd. Dit kan NIET ongedaan worden gemaakt.')) return;
            btn.disabled = true;
            ajaxCall('crm_delete_dealer_permanent', { dealer_id: info.id }, function () {
                removeRowWithFade(info.row);
            }, function (m) { alert('Fout: ' + m); btn.disabled = false; });
        });
    });

    // Bulk: Move to trash
    var bulkTrashBtn = document.getElementById('crm-bulk-trash-btn');
    if (bulkTrashBtn) {
        bulkTrashBtn.addEventListener('click', function () {
            var ids = [];
            document.querySelectorAll('.crm-dealer-checkbox:checked').forEach(function (cb) { ids.push(cb.value); });
            if (ids.length === 0) { alert('Selecteer eerst dealers.'); return; }
            if (!confirm(ids.length + ' dealer(s) naar de prullenbak verplaatsen?')) return;
            bulkTrashBtn.disabled = true;
            ajaxCall('crm_trash_dealers_bulk', { dealer_ids: ids }, function (data) {
                alert(data.message);
                location.reload();
            }, function (m) { alert('Fout: ' + m); bulkTrashBtn.disabled = false; });
        });
    }

    // Bulk: Restore
    var bulkRestoreBtn = document.getElementById('crm-bulk-restore-btn');
    if (bulkRestoreBtn) {
        bulkRestoreBtn.addEventListener('click', function () {
            var checked = document.querySelectorAll('.crm-dealer-checkbox:checked');
            if (checked.length === 0) { alert('Selecteer eerst dealers.'); return; }
            if (!confirm(checked.length + ' dealer(s) herstellen uit de prullenbak?')) return;
            bulkRestoreBtn.disabled = true;
            var total = checked.length;
            var done = 0;
            var failed = 0;
            checked.forEach(function (cb) {
                ajaxCall('crm_restore_dealer', { dealer_id: cb.value }, function () {
                    done++;
                    if (done + failed === total) { location.reload(); }
                }, function () {
                    failed++;
                    if (done + failed === total) { location.reload(); }
                });
            });
        });
    }

    // Bulk: Permanent delete
    var bulkPermBtn = document.getElementById('crm-bulk-delete-permanent-btn');
    if (bulkPermBtn) {
        bulkPermBtn.addEventListener('click', function () {
            var checked = document.querySelectorAll('.crm-dealer-checkbox:checked');
            if (checked.length === 0) { alert('Selecteer eerst dealers.'); return; }
            if (!confirm(checked.length + ' dealer(s) PERMANENT verwijderen?\n\nAlle bijbehorende gegevens worden verwijderd. Dit kan NIET ongedaan worden gemaakt.')) return;
            bulkPermBtn.disabled = true;
            var total = checked.length;
            var done = 0;
            var failed = 0;
            checked.forEach(function (cb) {
                ajaxCall('crm_delete_dealer_permanent', { dealer_id: cb.value }, function () {
                    done++;
                    if (done + failed === total) { location.reload(); }
                }, function () {
                    failed++;
                    if (done + failed === total) { location.reload(); }
                });
            });
        });
    }

    function escHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // Generic sortable table: any <table class="crm-sortable-table"> with
    // <th class="crm-sortable" data-sort-key="x" data-sort-type="text|number">
    // sorts on tr[data-x] values. Click toggles asc/desc.
    document.querySelectorAll('table.crm-sortable-table').forEach(function (table) {
        var headers = table.querySelectorAll('th.crm-sortable');
        headers.forEach(function (th) {
            th.style.cursor = 'pointer';
            th.style.userSelect = 'none';
            // Append an arrow placeholder for visual feedback
            if (!th.querySelector('.crm-sort-arrow')) {
                var arrow = document.createElement('span');
                arrow.className = 'crm-sort-arrow';
                arrow.style.opacity = '0.3';
                arrow.style.marginLeft = '4px';
                arrow.textContent = '⇅';
                th.appendChild(arrow);
            }
            th.addEventListener('click', function () {
                var key = th.getAttribute('data-sort-key');
                var type = th.getAttribute('data-sort-type') || 'text';
                var currentDir = th.getAttribute('data-sort-dir') || '';
                var newDir = currentDir === 'asc' ? 'desc' : 'asc';

                // Reset all other headers
                headers.forEach(function (h) {
                    h.removeAttribute('data-sort-dir');
                    var a = h.querySelector('.crm-sort-arrow');
                    if (a) { a.textContent = '⇅'; a.style.opacity = '0.3'; }
                });
                th.setAttribute('data-sort-dir', newDir);
                var arrow = th.querySelector('.crm-sort-arrow');
                if (arrow) {
                    arrow.textContent = newDir === 'asc' ? '▲' : '▼';
                    arrow.style.opacity = '1';
                }

                var tbody = table.querySelector('tbody');
                if (!tbody) return;
                var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
                rows.sort(function (a, b) {
                    var av = a.getAttribute('data-' + key) || '';
                    var bv = b.getAttribute('data-' + key) || '';
                    if (type === 'number') {
                        av = parseFloat(av); bv = parseFloat(bv);
                        if (isNaN(av)) av = Infinity;
                        if (isNaN(bv)) bv = Infinity;
                        return newDir === 'asc' ? av - bv : bv - av;
                    }
                    return newDir === 'asc'
                        ? av.localeCompare(bv, 'nl')
                        : bv.localeCompare(av, 'nl');
                });
                rows.forEach(function (r) { tbody.appendChild(r); });
            });
        });
    });
});
