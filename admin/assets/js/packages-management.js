(function () {
    'use strict';

    var purchases = [];
    var packages = [];
    var categories = [];

    var perPage = 5;
    var purPage = 1;
    var varPage = 1;
    var activeReceiptId = null;
    var deleteContext = null;

    var toastEl = document.getElementById('dashToast');
    var toastTimer = null;

    function showToast(msg, isError) {
        if (!toastEl) return;
        toastEl.textContent = msg;
        toastEl.classList.toggle('is-error', Boolean(isError));
        toastEl.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3200);
    }

    function formatRupiah(num) {
        return 'Rp' + Number(num || 0).toLocaleString('id-ID');
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function resolveReceiptSrc(raw) {
        var val = String(raw || '').trim();
        if (!val) return '';
        if (/^data:/i.test(val)) {
            return val.replace(/[\r\n\s]+/g, '');
        }
        if (/^(https?:\/\/|\.\.\/|\/)/i.test(val)) {
            return val;
        }
        if (/^uploads\//i.test(val)) {
            return '../' + val;
        }
        return '../uploads/receipts/' + encodeURIComponent(val);
    }

    function formatDateDisplay(isoOrSql) {
        if (!isoOrSql) return '-';
        var clean = String(isoOrSql).slice(0, 10);
        var parts = clean.split('-');
        if (parts.length !== 3) return String(isoOrSql);
        var d = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        if (isNaN(d.getTime())) return String(isoOrSql);
        return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function postJson(payload) {
        return fetch(window.location.pathname + window.location.search, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'fetch'
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json();
        });
    }

    function syncCategorySelects() {
        if (!Array.isArray(categories) || !categories.length) return;

        var filterSel = document.getElementById('filterCategory');
        var pkgSel = document.getElementById('pkgCategory');

        if (filterSel) {
            var currentFilter = filterSel.value;
            var filterOpts = '<option value="">All Categories</option>';
            categories.forEach(function (c) {
                var cname = String(c.class_name || '');
                if (!cname) return;
                filterOpts += '<option value="' + escapeHtml(cname) + '">' + escapeHtml(cname) + '</option>';
            });
            filterSel.innerHTML = filterOpts;
            if (currentFilter) {
                filterSel.value = currentFilter;
            }
        }

        if (pkgSel) {
            var currentPkgCat = pkgSel.value;
            var pkgOpts = '';
            categories.forEach(function (c) {
                var cid = Number(c.class_id || 0);
                var cname = String(c.class_name || '');
                if (!cname) return;
                pkgOpts += '<option value="' + escapeHtml(cname) + '" data-class-id="' + cid + '">' + escapeHtml(cname) + '</option>';
            });
            if (pkgOpts) {
                pkgSel.innerHTML = pkgOpts;
                if (currentPkgCat) {
                    pkgSel.value = currentPkgCat;
                }
            }
        }
    }

    function mapPurchaseFromDb(row) {
        var verif = String(row.verification_status || '').toLowerCase();
        var pkgSt = String(row.package_status || 'pending').toLowerCase();
        var hasProof = Boolean(row.payment_proof && String(row.payment_proof).trim() !== '');
        var uiStatus = 'pending';

        if (pkgSt === 'rejected' || ['rejected', 'declined', 'failed'].indexOf(verif) !== -1) {
            uiStatus = 'rejected';
        } else if (pkgSt === 'active' && ['verified', 'confirmed', 'approved', 'paid'].indexOf(verif) !== -1) {
            uiStatus = 'confirmed';
        } else if (verif === 'waiting_verification' || hasProof) {
            uiStatus = 'check';
        } else {
            uiStatus = 'pending';
        }

        var dateRaw = String(row.purchase_date || '');
        var dateIso = dateRaw.slice(0, 10);
        var receiptUrl = resolveReceiptSrc(row.payment_proof);

        var noteText = String(row.rejection_reason || row.admin_note || '').trim();
        if (!noteText && uiStatus === 'confirmed') {
            noteText = 'Approved by admin';
        }

        return {
            id: Number(row.user_package_id || 0),
            payment_id: Number(row.payment_id || 0),
            order_id: String(row.order_id || ('PKO-' + (row.user_package_id || 0))),
            customer: String(row.customer_name || 'Customer'),
            package_name: String(row.package_name || 'Class Package'),
            variant: String(row.variant_label || ((row.total_sessions || 1) + ' Sessions')),
            date_iso: dateIso,
            date_display: formatDateDisplay(dateRaw),
            amount: Number(row.total_payment || 0),
            status: uiStatus,
            package_status: pkgSt,
            receipt_url: receiptUrl,
            admin_note: noteText
        };
    }

    function applyServerPayload(payload) {
        if (Array.isArray(payload.categories)) {
            categories = payload.categories;
            syncCategorySelects();
        }
        if (Array.isArray(payload.purchases)) {
            purchases = payload.purchases.map(mapPurchaseFromDb);
        }
        if (Array.isArray(payload.variants)) {
            packages = payload.variants.map(function (v) {
                return {
                    id: Number(v.id || 0),
                    code: String(v.code || ''),
                    name: String(v.name || ''),
                    variant: String(v.variant || ((v.sessions || 1) + ' Sessions')),
                    class_id: Number(v.class_id || 0),
                    category: String(v.category || 'All Classes'),
                    sessions: Number(v.sessions || 1),
                    validity: Number(v.validity || 30),
                    price: Number(v.price || 0),
                    status: String(v.status || 'active').toLowerCase() === 'inactive' ? 'inactive' : 'active',
                    description: String(v.description || '')
                };
            });
        }
        renderPurchases();
        renderVariants();
    }

    function loadFromDatabase() {
        var sep = window.location.search ? '&' : '?';
        fetch(window.location.pathname + window.location.search + sep + 'ajax=1', {
            headers: { 'X-Requested-With': 'fetch' }
        })
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.ok) {
                    applyServerPayload(data);
                } else {
                    showToast((data && data.message) || 'Failed to load database records.', true);
                }
            })
            .catch(function () {
                showToast('Network error while loading package data.', true);
            });
    }

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('dash-modal-open');
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('dash-modal-open');
    }

    var tabBtns = document.querySelectorAll('.pkg-tab');
    var panelPurchases = document.getElementById('panelPurchases');
    var panelVariants = document.getElementById('panelVariants');
    var btnAddPackage = document.getElementById('btnAddPackage');

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = btn.dataset.tab;
            tabBtns.forEach(function (b) {
                var active = b === btn;
                b.classList.toggle('is-active', active);
                b.setAttribute('aria-selected', active ? 'true' : 'false');
            });
            panelPurchases.hidden = target !== 'purchases';
            panelVariants.hidden = target !== 'variants';
            btnAddPackage.hidden = target !== 'variants';
        });
    });

    var purBody = document.getElementById('purchasesBody');
    var purSearch = document.getElementById('purSearch');
    var purDate = document.getElementById('purDate');
    var purDateWrap = document.getElementById('purDateWrap');
    var purDateText = document.getElementById('purDateText');
    var purDateClear = document.getElementById('purDateClear');
    var purStatus = document.getElementById('purStatus');
    var purStatusWrap = document.getElementById('purStatusWrap');
    var purPagePrev = document.getElementById('purPagePrev');
    var purPageNext = document.getElementById('purPageNext');
    var purPageNumbers = document.getElementById('purPageNumbers');

    var receiptModal = document.getElementById('receiptModal');
    var rejectionReason = document.getElementById('rejectionReason');
    var rejectionError = document.getElementById('rejectionError');
    var btnApprove = document.getElementById('btnApprove');
    var btnReject = document.getElementById('btnReject');
    var btnCloseReceipt = document.getElementById('btnCloseReceipt');
    var receiptNote = document.getElementById('receiptNote');
    var receiptFeedback = document.getElementById('receiptFeedback');

    function renderPurchases() {
        var checkCount = purchases.filter(function (p) { return p.status === 'check'; }).length;
        document.getElementById('kpiPurTotal').textContent = purchases.length;
        document.getElementById('kpiPurCheck').textContent = checkCount;
        document.getElementById('kpiPurConfirmed').textContent = purchases.filter(function (p) { return p.status === 'confirmed'; }).length;
        document.getElementById('kpiPurRejected').textContent = purchases.filter(function (p) { return p.status === 'rejected'; }).length;
        document.getElementById('tabBadgeCheck').textContent = checkCount;

        var q = (purSearch.value || '').trim().toLowerCase();
        var d = purDate.value;
        var st = purStatus.value;

        var list = purchases.filter(function (p) {
            var matchQ = !q || p.order_id.toLowerCase().indexOf(q) !== -1 ||
                p.customer.toLowerCase().indexOf(q) !== -1 ||
                p.package_name.toLowerCase().indexOf(q) !== -1;
            var matchD = !d || p.date_iso === d;
            var matchS = !st || p.status === st;
            return matchQ && matchD && matchS;
        });

        var totalPages = Math.max(1, Math.ceil(list.length / perPage));
        if (purPage > totalPages) purPage = totalPages;
        var pageItems = list.slice((purPage - 1) * perPage, purPage * perPage);

        if (!pageItems.length) {
            purBody.innerHTML = '<tr class="bookings-empty"><td colspan="7">No package purchases found.</td></tr>';
        } else {
            var labels = { check: 'Check', pending: 'Pending', confirmed: 'Confirmed', rejected: 'Rejected' };
            purBody.innerHTML = pageItems.map(function (p, idx) {
                var isLast = idx === pageItems.length - 1 ? ' class="is-last-visible"' : '';
                return '<tr' + isLast + '>' +
                    '<td>' + escapeHtml(p.order_id) + '</td>' +
                    '<td>' + escapeHtml(p.customer) + '</td>' +
                    '<td><div class="pkg-name-cell">' +
                    '<span class="pkg-name-cell__title">' + escapeHtml(p.package_name) + '</span>' +
                    '<span class="pkg-name-cell__variant">' + escapeHtml(p.variant) + '</span>' +
                    '</div></td>' +
                    '<td>' + escapeHtml(p.date_display) + '</td>' +
                    '<td class="pkg-price-cell">' + formatRupiah(p.amount) + '</td>' +
                    '<td><button type="button" class="status-badge status-badge--' + p.status + ' js-open-receipt" data-id="' + p.id + '">' +
                    (labels[p.status] || p.status) + '</button></td>' +
                    '<td><div class="row-actions">' +
                    '<button type="button" class="icon-btn js-open-receipt" data-id="' + p.id + '" title="Verify receipt"><i class="ph-fill ph-eye" style="font-size: 22px;"></i></button>' +
                    '<button type="button" class="icon-btn js-delete-purchase" data-id="' + p.id + '" title="Delete order"><i class="ph-fill ph-trash" style="font-size: 21px;"></i></button>' +
                    '</div></td>' +
                    '</tr>';
            }).join('');
        }

        purPagePrev.disabled = purPage <= 1;
        purPageNext.disabled = purPage >= totalPages;
        var html = '';
        for (var i = 1; i <= totalPages; i++) {
            html += '<button type="button" class="page-btn' + (i === purPage ? ' is-current' : '') + '" data-pur-page="' + i + '">' + i + '</button>';
        }
        purPageNumbers.innerHTML = html;
    }

    function openReceiptModal(id) {
        var item = purchases.find(function (p) { return p.id === id; });
        if (!item) return;
        activeReceiptId = id;

        document.getElementById('receiptModalTitle').textContent = item.order_id;
        document.getElementById('recOrderId').textContent = item.order_id;
        document.getElementById('recCustomer').textContent = item.customer;
        document.getElementById('recPackage').textContent = item.package_name + ' (' + item.variant + ')';
        document.getElementById('recAmount').textContent = formatRupiah(item.amount);

        var img = document.getElementById('receiptImage');
        var empty = document.getElementById('receiptEmpty');
        if (item.receipt_url) {
            empty.hidden = true;
            empty.textContent = 'No payment receipt has been uploaded yet.';
            img.hidden = false;
            img.alt = 'Payment receipt of ' + item.customer + ' (' + item.order_id + ')';
            img.onerror = function () {
                img.onerror = null;
                img.hidden = true;
                img.removeAttribute('src');
                empty.textContent = 'The receipt image could not be loaded.';
                empty.hidden = false;
            };
            img.src = item.receipt_url;
        } else {
            img.onerror = null;
            img.hidden = true;
            img.removeAttribute('src');
            empty.textContent = 'No payment receipt has been uploaded yet.';
            empty.hidden = false;
        }

        rejectionReason.value = item.admin_note || '';
        rejectionReason.classList.remove('is-invalid');
        rejectionError.hidden = true;

        var isActionable = item.status === 'check' || item.status === 'pending';
        btnApprove.hidden = !isActionable;
        btnReject.hidden = !isActionable;
        receiptFeedback.hidden = !isActionable;
        btnCloseReceipt.hidden = isActionable;

        if (!isActionable && item.admin_note) {
            receiptNote.textContent = 'Note: ' + item.admin_note;
            receiptNote.hidden = false;
        } else {
            receiptNote.hidden = true;
        }

        openModal(receiptModal);
    }

    function submitVerification(actionName, reasonText) {
        var item = purchases.find(function (p) { return p.id === activeReceiptId; });
        if (!item) return;

        btnApprove.disabled = true;
        btnReject.disabled = true;

        postJson({
            action: actionName,
            user_package_id: item.id,
            payment_id: item.payment_id,
            reason: reasonText || ''
        })
            .then(function (data) {
                if (data && data.ok) {
                    applyServerPayload(data);
                    closeModal(receiptModal);
                    showToast(data.message || ('Package purchase ' + item.order_id + ' updated.'));
                } else {
                    showToast((data && data.message) || 'Failed to update status.', true);
                }
            })
            .catch(function () {
                showToast('Network error while saving verification.', true);
            })
            .finally(function () {
                btnApprove.disabled = false;
                btnReject.disabled = false;
            });
    }

    btnApprove.addEventListener('click', function () {
        var note = (rejectionReason.value || '').trim();
        submitVerification('approve_purchase', note || 'Approved by admin');
    });

    btnReject.addEventListener('click', function () {
        var reason = (rejectionReason.value || '').trim();
        if (!reason) {
            rejectionReason.classList.add('is-invalid');
            rejectionError.hidden = false;
            rejectionReason.focus();
            return;
        }
        submitVerification('reject_purchase', reason);
    });

    purBody.addEventListener('click', function (e) {
        var recBtn = e.target.closest('.js-open-receipt');
        if (recBtn) {
            openReceiptModal(parseInt(recBtn.dataset.id, 10));
            return;
        }
        var delBtn = e.target.closest('.js-delete-purchase');
        if (delBtn) {
            var id = parseInt(delBtn.dataset.id, 10);
            var item = purchases.find(function (p) { return p.id === id; });
            if (item) {
                deleteContext = { type: 'purchase', id: id };
                document.getElementById('deleteModalTitle').textContent = 'Delete Purchase';
                document.getElementById('deleteTargetName').textContent = item.order_id + ' (' + item.customer + ')';
                openModal(deleteModal);
            }
        }
    });

    purSearch.addEventListener('input', function () { purPage = 1; renderPurchases(); });
    purDate.addEventListener('change', function () {
        var val = purDate.value;
        purDateWrap.classList.toggle('is-active', Boolean(val));
        purDateText.textContent = val || 'Date';
        purDateClear.hidden = !val;
        purPage = 1;
        renderPurchases();
    });
    purDateClear.addEventListener('click', function () {
        purDate.value = '';
        purDateWrap.classList.remove('is-active');
        purDateText.textContent = 'Date';
        purDateClear.hidden = true;
        purPage = 1;
        renderPurchases();
    });
    purStatus.addEventListener('change', function () {
        purStatusWrap.classList.toggle('is-active', Boolean(purStatus.value));
        purPage = 1;
        renderPurchases();
    });
    purPagePrev.addEventListener('click', function () { if (purPage > 1) { purPage--; renderPurchases(); } });
    purPageNext.addEventListener('click', function () { purPage++; renderPurchases(); });
    purPageNumbers.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-pur-page]');
        if (btn) { purPage = parseInt(btn.dataset.purPage, 10); renderPurchases(); }
    });

    var tbody = document.getElementById('packagesBody');
    var filterSearch = document.getElementById('filterSearch');
    var filterCategory = document.getElementById('filterCategory');
    var filterStatus = document.getElementById('filterStatus');
    var filterCategoryWrap = document.getElementById('filterCategoryWrap');
    var filterStatusWrap = document.getElementById('filterStatusWrap');
    var pagePrev = document.getElementById('pagePrev');
    var pageNext = document.getElementById('pageNext');
    var pageNumbers = document.getElementById('pageNumbers');
    var packageModal = document.getElementById('packageModal');
    var deleteModal = document.getElementById('deleteModal');
    var packageForm = document.getElementById('packageForm');
    var btnSavePackage = document.getElementById('btnSavePackage');
    var btnConfirmDelete = document.getElementById('btnConfirmDelete');

    function renderVariants() {
        document.getElementById('kpiTotal').textContent = packages.length;
        document.getElementById('kpiActive').textContent = packages.filter(function (p) { return p.status === 'active'; }).length;
        document.getElementById('kpiPilates').textContent = packages.filter(function (p) {
            return String(p.category || '').toLowerCase().indexOf('pilates') !== -1;
        }).length;
        document.getElementById('kpiInactive').textContent = packages.filter(function (p) { return p.status === 'inactive'; }).length;

        var q = (filterSearch.value || '').trim().toLowerCase();
        var cat = filterCategory.value;
        var st = filterStatus.value;

        var list = packages.filter(function (p) {
            var matchQ = !q || p.name.toLowerCase().indexOf(q) !== -1 || p.variant.toLowerCase().indexOf(q) !== -1 || p.code.toLowerCase().indexOf(q) !== -1;
            var matchCat = !cat || p.category === cat;
            var matchSt = !st || p.status === st;
            return matchQ && matchCat && matchSt;
        });

        var totalPages = Math.max(1, Math.ceil(list.length / perPage));
        if (varPage > totalPages) varPage = totalPages;
        var pageItems = list.slice((varPage - 1) * perPage, varPage * perPage);

        if (!pageItems.length) {
            tbody.innerHTML = '<tr class="bookings-empty"><td colspan="8">No package variants found.</td></tr>';
        } else {
            tbody.innerHTML = pageItems.map(function (p, idx) {
                var isLast = idx === pageItems.length - 1 ? ' class="is-last-visible"' : '';
                var badgeClass = p.status === 'active' ? 'status-badge--confirmed' : 'status-badge--rejected';
                return '<tr' + isLast + '>' +
                    '<td>' + escapeHtml(p.code) + '</td>' +
                    '<td><div class="pkg-name-cell">' +
                    '<span class="pkg-name-cell__title">' + escapeHtml(p.name) + '</span>' +
                    '<span class="pkg-name-cell__variant">' + escapeHtml(p.variant) + '</span>' +
                    '</div></td>' +
                    '<td>' + escapeHtml(p.category) + '</td>' +
                    '<td>' + p.sessions + ' Sessions</td>' +
                    '<td>' + p.validity + ' Days</td>' +
                    '<td class="pkg-price-cell">' + formatRupiah(p.price) + '</td>' +
                    '<td><button type="button" class="status-badge ' + badgeClass + ' js-toggle-status" data-id="' + p.id + '">' +
                    (p.status === 'active' ? 'Active' : 'Inactive') + '</button></td>' +
                    '<td><div class="row-actions">' +
                    '<button type="button" class="icon-btn js-edit" data-id="' + p.id + '" title="Edit variant"><i class="ph-fill ph-pencil-simple" style="font-size: 21px;"></i></button>' +
                    '<button type="button" class="icon-btn js-delete" data-id="' + p.id + '" title="Delete variant"><i class="ph-fill ph-trash" style="font-size: 21px;"></i></button>' +
                    '</div></td>' +
                    '</tr>';
            }).join('');
        }

        pagePrev.disabled = varPage <= 1;
        pageNext.disabled = varPage >= totalPages;
        var html = '';
        for (var i = 1; i <= totalPages; i++) {
            html += '<button type="button" class="page-btn' + (i === varPage ? ' is-current' : '') + '" data-page="' + i + '">' + i + '</button>';
        }
        pageNumbers.innerHTML = html;
    }

    btnAddPackage.addEventListener('click', function () {
        document.getElementById('pkgId').value = '';
        document.getElementById('packageModalEyebrow').textContent = 'New Variant';
        document.getElementById('packageModalTitle').textContent = 'Add Package Variant';
        btnSavePackage.textContent = 'Save Variant';
        packageForm.reset();
        document.getElementById('pkgStatus').value = 'active';
        openModal(packageModal);
    });

    packageForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!packageForm.checkValidity()) { packageForm.reportValidity(); return; }

        var idVal = parseInt(document.getElementById('pkgId').value || '0', 10);
        var catSelect = document.getElementById('pkgCategory');
        var selectedOpt = catSelect.options[catSelect.selectedIndex];
        var classId = selectedOpt && selectedOpt.dataset.classId ? parseInt(selectedOpt.dataset.classId, 10) : 0;

        var payload = {
            action: 'save_variant',
            id: idVal,
            name: document.getElementById('pkgName').value.trim(),
            variant: document.getElementById('pkgVariant').value.trim(),
            class_id: classId,
            category: catSelect.value,
            sessions: parseInt(document.getElementById('pkgSessions').value, 10) || 1,
            validity: parseInt(document.getElementById('pkgValidity').value, 10) || 30,
            price: parseInt(document.getElementById('pkgPrice').value, 10) || 0,
            status: document.getElementById('pkgStatus').value,
            description: document.getElementById('pkgDesc').value.trim()
        };

        btnSavePackage.disabled = true;

        postJson(payload)
            .then(function (data) {
                if (data && data.ok) {
                    if (!idVal) varPage = 1;
                    applyServerPayload(data);
                    closeModal(packageModal);
                    showToast(data.message || 'Package variant saved.');
                } else {
                    showToast((data && data.message) || 'Failed to save package variant.', true);
                }
            })
            .catch(function () {
                showToast('Network error while saving package variant.', true);
            })
            .finally(function () {
                btnSavePackage.disabled = false;
            });
    });

    tbody.addEventListener('click', function (e) {
        var editBtn = e.target.closest('.js-edit');
        if (editBtn) {
            var pkg = packages.find(function (p) { return p.id === parseInt(editBtn.dataset.id, 10); });
            if (!pkg) return;
            document.getElementById('pkgId').value = pkg.id;
            document.getElementById('packageModalEyebrow').textContent = pkg.code;
            document.getElementById('packageModalTitle').textContent = 'Edit Package Variant';
            btnSavePackage.textContent = 'Update Variant';
            document.getElementById('pkgName').value = pkg.name;
            document.getElementById('pkgVariant').value = pkg.variant;

            var catSelect = document.getElementById('pkgCategory');
            if (pkg.category) {
                var exists = Array.prototype.some.call(catSelect.options, function (o) { return o.value === pkg.category; });
                if (!exists) {
                    var opt = document.createElement('option');
                    opt.value = pkg.category;
                    opt.textContent = pkg.category;
                    if (pkg.class_id) opt.dataset.classId = String(pkg.class_id);
                    catSelect.appendChild(opt);
                }
                catSelect.value = pkg.category;
            }

            document.getElementById('pkgSessions').value = pkg.sessions;
            document.getElementById('pkgValidity').value = pkg.validity;
            document.getElementById('pkgPrice').value = pkg.price;
            document.getElementById('pkgStatus').value = pkg.status;
            document.getElementById('pkgDesc').value = pkg.description || '';
            openModal(packageModal);
            return;
        }
        var delBtn = e.target.closest('.js-delete');
        if (delBtn) {
            var id = parseInt(delBtn.dataset.id, 10);
            var target = packages.find(function (p) { return p.id === id; });
            if (target) {
                deleteContext = { type: 'variant', id: id };
                document.getElementById('deleteModalTitle').textContent = 'Delete Variant';
                document.getElementById('deleteTargetName').textContent = target.name + ' (' + target.variant + ')';
                openModal(deleteModal);
            }
            return;
        }
        var toggleBtn = e.target.closest('.js-toggle-status');
        if (toggleBtn) {
            var toggleId = parseInt(toggleBtn.dataset.id, 10);
            toggleBtn.disabled = true;

            postJson({
                action: 'toggle_variant_status',
                id: toggleId
            })
                .then(function (data) {
                    if (data && data.ok) {
                        applyServerPayload(data);
                        showToast(data.message || 'Package status updated.');
                    } else {
                        showToast((data && data.message) || 'Failed to toggle package status.', true);
                    }
                })
                .catch(function () {
                    showToast('Network error while updating status.', true);
                })
                .finally(function () {
                    toggleBtn.disabled = false;
                });
        }
    });

    btnConfirmDelete.addEventListener('click', function () {
        if (!deleteContext) return;
        var currentContext = deleteContext;
        btnConfirmDelete.disabled = true;

        var actionName = currentContext.type === 'purchase' ? 'delete_purchase' : 'delete_variant';

        postJson({
            action: actionName,
            id: currentContext.id,
            user_package_id: currentContext.id
        })
            .then(function (data) {
                if (data && data.ok) {
                    applyServerPayload(data);
                    deleteContext = null;
                    closeModal(deleteModal);
                    showToast(data.message || 'Item deleted.');
                } else {
                    showToast((data && data.message) || 'Failed to delete item.', true);
                }
            })
            .catch(function () {
                showToast('Network error while deleting item.', true);
            })
            .finally(function () {
                btnConfirmDelete.disabled = false;
            });
    });

    document.querySelectorAll('.dash-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay || e.target.closest('[data-close]')) closeModal(overlay);
        });
    });

    filterSearch.addEventListener('input', function () { varPage = 1; renderVariants(); });
    filterCategory.addEventListener('change', function () {
        filterCategoryWrap.classList.toggle('is-active', Boolean(filterCategory.value));
        varPage = 1;
        renderVariants();
    });
    filterStatus.addEventListener('change', function () {
        filterStatusWrap.classList.toggle('is-active', Boolean(filterStatus.value));
        varPage = 1;
        renderVariants();
    });
    pagePrev.addEventListener('click', function () { if (varPage > 1) { varPage--; renderVariants(); } });
    pageNext.addEventListener('click', function () { varPage++; renderVariants(); });
    pageNumbers.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-page]');
        if (btn) { varPage = parseInt(btn.dataset.page, 10); renderVariants(); }
    });

    if (window.__INITIAL_PACKAGES_DATA__) {
        applyServerPayload(window.__INITIAL_PACKAGES_DATA__);
    } else {
        renderPurchases();
        renderVariants();
        loadFromDatabase();
    }
})();