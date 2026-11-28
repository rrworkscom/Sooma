(function () {
    'use strict';

    var root = document.querySelector('.dashboard-page');
    var dataEl = document.getElementById('dashboard-data');
    if (!root || !dataEl) return;

    var DATA = {};
    try {
        DATA = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
        console.warn('[dashboard.js] Data dashboard tidak valid:', err);
        return;
    }

    var bookings = DATA.bookings || {};
    var bookingOrder = Array.isArray(DATA.booking_order) ? DATA.booking_order.slice() : [];
    var statusLabels = DATA.statusLabels || { check: 'Check', pending: 'Pending', confirmed: 'Confirmed', rejected: 'Rejected' };
    var PER_PAGE = DATA.perPage || 6;
    var fetchUrl = root.getAttribute('data-fetch-endpoint') || '';
    var verifyUrl = root.getAttribute('data-verify-endpoint') || '';
    var deleteUrl = root.getAttribute('data-delete-endpoint') || '';

    var MONTHS_ID = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    function $(id) { return document.getElementById(id); }

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function (m) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[m];
        });
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

    function isPrivateAccepted(b) {
        if (!b) return false;
        var reqStatus = String(b.private_status || b.booking_status || b.request_status || b.approval_status || '').trim().toLowerCase();
        if (reqStatus === 'accepted' || reqStatus === 'approved' || reqStatus === 'confirmed' || reqStatus === 'completed') {
            return true;
        }
        if (b.is_accepted || b.private_accepted) {
            return true;
        }
        return String(b.status || '').trim().toLowerCase() === 'confirmed';
    }

    function renderClassTypeCell(b) {
        var rawType = String((b && (b.class_type || b.type_class || b.type)) || 'Regular').trim() || 'Regular';
        if (rawType.toLowerCase() === 'private') {
            if (isPrivateAccepted(b)) {
                return '<span class="class-type-text" title="Private class (Accepted)">Private</span>';
            }
            return '<span class="class-type-text class-type-text--pending" title="Private class (Waiting for approval)">' +
                '<span>Private</span>' +
                '<i class="ph-bold ph-clock-countdown class-type-text__icon" aria-hidden="true"></i>' +
                '</span>';
        }
        return escapeHtml(rawType.charAt(0).toUpperCase() + rawType.slice(1).toLowerCase());
    }

    function formatRupiah(value) {
        return 'Rp ' + Math.round(value).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function formatIsoDate(iso) {
        var p = (iso || '').split('-');
        if (p.length !== 3) return iso;
        return parseInt(p[2], 10) + ' ' + MONTHS_ID[parseInt(p[1], 10) - 1] + ' ' + p[0];
    }

    function getSortedBookingIds(map, order) {
        if (Array.isArray(order) && order.length) {
            var seen = {};
            var ordered = [];
            order.forEach(function (id) {
                var key = String(id);
                if (map && Object.prototype.hasOwnProperty.call(map, key) && !seen[key]) {
                    seen[key] = true;
                    ordered.push(key);
                }
            });
            Object.keys(map || {}).forEach(function (key) {
                if (!seen[key]) ordered.push(key);
            });
            return ordered;
        }

        return Object.keys(map || {}).sort(function (a, b) {
            var numA = Number(a);
            var numB = Number(b);
            if (!isNaN(numA) && !isNaN(numB)) {
                return numB - numA;
            }
            return String(b).localeCompare(String(a));
        });
    }

    var toastTimer = null;
    function toast(message, isError) {
        var el = $('dashToast');
        if (!el) return;
        el.textContent = message;
        el.classList.toggle('is-error', !!isError);
        el.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () { el.hidden = true; }, 3200);
    }

    function postToEndpoint(url, fields) {
        if (!url) return Promise.resolve({ success: false, message: 'Endpoint not configured.' });

        var body = new FormData();
        Object.keys(fields).forEach(function (k) { body.append(k, fields[k]); });
        body.append('csrf_token', DATA.csrf || '');

        return fetch(url, { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function (res) {
                return res.json().catch(function () {
                    return { success: res.ok, message: res.ok ? '' : 'HTTP ' + res.status };
                }).then(function (json) {
                    if (!res.ok) json.success = false;
                    return json;
                });
            })
            .catch(function (err) {
                console.warn('[dashboard.js] Request gagal:', err);
                return { success: false, message: err.message || 'Network error' };
            });
    }

    var monthSelect = $('salesMonth');
    var canvas = $('salesChart');
    var chart = null;

    function compactRupiah(v) {
        if (v >= 1000000) return (v / 1000000).toString().replace('.', ',') + 'jt';
        if (v >= 1000) return (v / 1000) + 'rb';
        return String(v);
    }

    function seriesFor(month) {
        var info = (DATA.chart && DATA.chart.months && DATA.chart.months[month]) || { days: 0, values: [] };
        var bookingValues = Array.isArray(info.bookings) ? info.bookings : (info.values || []);
        var packageValues = Array.isArray(info.packages)
            ? info.packages
            : bookingValues.map(function (v, idx) {
                return Math.round(v * (0.45 + ((idx % 3) * 0.1)));
            });

        var len = Math.max(bookingValues.length, packageValues.length);
        var labels = [];
        for (var d = 1; d <= len; d++) labels.push(String(d));

        return {
            labels: labels,
            bookings: bookingValues,
            packages: packageValues
        };
    }

    function peakIndex(values) {
        var idx = 0;
        for (var i = 1; i < values.length; i++) if (values[i] > values[idx]) idx = i;
        return idx;
    }

    var peakLabelPlugin = {
        id: 'peakLabel',
        afterDatasetsDraw: function (c) {
            if (c.tooltip && c.tooltip.getActiveElements().length) return;
            var meta = c.getDatasetMeta(0);
            var values = c.data.datasets[0].data;
            if (!meta || !meta.data.length || !values.length) return;

            var i = peakIndex(values);
            var pt = meta.data[i];
            var ctx = c.ctx;
            var text = formatRupiah(values[i]);

            ctx.save();
            ctx.font = '700 12px Montserrat, sans-serif';
            var w = ctx.measureText(text).width + 20;
            var h = 24;
            var x = Math.min(Math.max(pt.x - w / 2, c.chartArea.left), c.chartArea.right - w);
            var y = pt.y - h - 12;

            ctx.fillStyle = '#4379ee';
            ctx.beginPath();
            if (ctx.roundRect) { ctx.roundRect(x, y, w, h, 4); } else { ctx.rect(x, y, w, h); }
            ctx.fill();

            ctx.beginPath();
            ctx.moveTo(pt.x - 5, y + h);
            ctx.lineTo(pt.x + 5, y + h);
            ctx.lineTo(pt.x, y + h + 6);
            ctx.closePath();
            ctx.fill();

            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(text, x + w / 2, y + h / 2 + 1);
            ctx.restore();
        }
    };

    function initChart() {
        if (!canvas) return;
        if (typeof window.Chart === 'undefined') {
            var fb = $('chartFallback');
            if (fb) fb.hidden = false;
            canvas.hidden = true;
            return;
        }

        var month = monthSelect ? monthSelect.value : DATA.chart.default;
        var series = seriesFor(month);
        var ctx = canvas.getContext('2d');

        var gradientBlue = ctx.createLinearGradient(0, 0, 0, 300);
        gradientBlue.addColorStop(0, 'rgba(67, 121, 238, 0.28)');
        gradientBlue.addColorStop(1, 'rgba(67, 121, 238, 0)');

        var gradientOrange = ctx.createLinearGradient(0, 0, 0, 300);
        gradientOrange.addColorStop(0, 'rgba(249, 115, 22, 0.24)');
        gradientOrange.addColorStop(1, 'rgba(249, 115, 22, 0)');

        chart = new window.Chart(ctx, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [
                    {
                        label: 'Booking Income',
                        data: series.bookings,
                        borderColor: '#4379ee',
                        borderWidth: 2,
                        backgroundColor: gradientBlue,
                        fill: true,
                        tension: 0.25,
                        pointBackgroundColor: '#4379ee',
                        pointRadius: function (c) { return c.dataIndex === peakIndex(c.dataset.data) ? 4 : 0; },
                        pointHoverRadius: 5,
                        pointHitRadius: 12
                    },
                    {
                        label: 'Packages Income',
                        data: series.packages,
                        borderColor: '#f97316',
                        borderWidth: 2,
                        backgroundColor: gradientOrange,
                        fill: true,
                        tension: 0.25,
                        pointBackgroundColor: '#f97316',
                        pointRadius: 0,
                        pointHoverRadius: 5,
                        pointHitRadius: 12
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', axis: 'x', intersect: false },
                layout: { padding: { top: 34, right: 4 } },
                animation: { duration: 450 },
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        align: 'end',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 8,
                            font: { family: 'Montserrat, sans-serif', weight: '600', size: 12 }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#202224',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        titleFont: { family: 'Montserrat, sans-serif', weight: '600', size: 11 },
                        bodyFont: { family: 'Montserrat, sans-serif', weight: '700', size: 13 },
                        padding: 10,
                        cornerRadius: 6,
                        displayColors: true,
                        callbacks: {
                            title: function (items) {
                                return items.length ? items[0].label + ' ' + monthSelect.value : '';
                            },
                            label: function (item) {
                                return item.dataset.label + ': ' + formatRupiah(item.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        border: { display: false },
                        ticks: {
                            color: 'rgba(43, 48, 52, 0.4)',
                            font: { family: 'Montserrat, sans-serif', weight: '600', size: 12 },
                            maxRotation: 0,
                            autoSkipPadding: 14
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(43, 48, 52, 0.08)' },
                        border: { display: false },
                        ticks: {
                            color: 'rgba(43, 48, 52, 0.4)',
                            font: { family: 'Montserrat, sans-serif', weight: '600', size: 12 },
                            maxTicksLimit: 6,
                            callback: function (v) { return compactRupiah(v); }
                        }
                    }
                }
            },
            plugins: [peakLabelPlugin]
        });
    }

    function updateChart(silent) {
        if (!chart || !monthSelect) return;
        var series = seriesFor(monthSelect.value);
        chart.data.labels = series.labels;
        chart.data.datasets[0].data = series.bookings;
        chart.data.datasets[1].data = series.packages;
        chart.update(silent ? 'none' : undefined);
    }

    if (monthSelect) {
        monthSelect.addEventListener('change', function () { updateChart(false); });
    }

    var tbody = $('bookingsBody');
    var emptyRow = $('bookingsEmpty');
    var searchInput = $('filterSearch');
    var dateInput = $('filterDate');
    var dateText = $('filterDateText');
    var dateClear = $('filterDateClear');
    var statusSel = $('filterStatus');
    var pageNumbers = $('pageNumbers');
    var pagePrev = $('pagePrev');
    var pageNext = $('pageNext');
    var pagination = $('pagination');

    var state = { q: '', date: '', status: '', page: 1 };

    function allRows() {
        return tbody ? Array.prototype.slice.call(tbody.querySelectorAll('tr[data-id]')) : [];
    }

    function matches(row) {
        if (state.status && row.getAttribute('data-status') !== state.status) return false;
        if (state.date && row.getAttribute('data-date') !== state.date) return false;
        if (state.q) {
            var id = row.getAttribute('data-id').toLowerCase();
            var item = bookings[row.getAttribute('data-id')];
            var disp = item && item.display_id ? item.display_id.toLowerCase().replace(/^#/, '') : '';
            var name = row.getAttribute('data-name') || '';
            if (id.indexOf(state.q) === -1 && disp.indexOf(state.q) === -1 && name.indexOf(state.q) === -1) return false;
        }
        return true;
    }

    function pageList(current, total) {
        if (total <= 5) {
            var all = [];
            for (var i = 1; i <= total; i++) all.push(i);
            return all;
        }
        var set = {};
        [1, 2, current - 1, current, current + 1, total].forEach(function (n) {
            if (n >= 1 && n <= total) set[n] = true;
        });
        var nums = Object.keys(set).map(Number).sort(function (a, b) { return a - b; });
        var out = [];
        nums.forEach(function (n, idx) {
            if (idx && n - nums[idx - 1] > 1) out.push('…');
            out.push(n);
        });
        return out;
    }

    function renderPagination(totalPages) {
        if (!pageNumbers) return;
        pageNumbers.textContent = '';

        pageList(state.page, totalPages).forEach(function (item) {
            if (item === '…') {
                var dots = document.createElement('span');
                dots.className = 'page-ellipsis';
                dots.textContent = '…';
                pageNumbers.appendChild(dots);
                return;
            }
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'page-btn' + (item === state.page ? ' is-current' : '');
            btn.textContent = String(item);
            btn.setAttribute('data-page', String(item));
            btn.setAttribute('aria-label', 'Page ' + item);
            if (item === state.page) btn.setAttribute('aria-current', 'page');
            pageNumbers.appendChild(btn);
        });

        if (pagePrev) pagePrev.disabled = state.page <= 1;
        if (pageNext) pageNext.disabled = state.page >= totalPages;
    }

    function render() {
        var rows = allRows();
        var filtered = rows.filter(matches);
        var totalPages = Math.max(1, Math.ceil(filtered.length / PER_PAGE));
        state.page = Math.min(Math.max(state.page, 1), totalPages);

        var start = (state.page - 1) * PER_PAGE;
        var visible = filtered.slice(start, start + PER_PAGE);

        rows.forEach(function (row) {
            row.hidden = visible.indexOf(row) === -1;
            row.classList.remove('is-last-visible');
        });
        if (visible.length) visible[visible.length - 1].classList.add('is-last-visible');

        if (emptyRow) emptyRow.hidden = filtered.length > 0;
        if (pagination) pagination.hidden = filtered.length === 0;

        renderPagination(totalPages);
    }

    function resetPageAndRender() {
        state.page = 1;
        render();
    }

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            state.q = searchInput.value.trim().toLowerCase().replace(/^#/, '');
            resetPageAndRender();
        });
    }

    if (statusSel) {
        statusSel.addEventListener('change', function () {
            state.status = statusSel.value;
            statusSel.parentNode.classList.toggle('is-active', !!state.status);
            resetPageAndRender();
        });
    }

    function setDate(iso) {
        state.date = iso || '';
        if (dateInput) dateInput.value = state.date;
        if (dateText) dateText.textContent = state.date ? formatIsoDate(state.date) : 'Date';
        if (dateClear) dateClear.hidden = !state.date;
        if (dateInput && dateInput.parentNode) dateInput.parentNode.classList.toggle('is-active', !!state.date);
        resetPageAndRender();
    }

    if (dateInput) {
        dateInput.addEventListener('change', function () { setDate(dateInput.value); });
        dateInput.addEventListener('click', function () {
            if (typeof dateInput.showPicker === 'function') {
                try { dateInput.showPicker(); } catch (e) { }
            }
        });
    }
    if (dateClear) {
        dateClear.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            setDate('');
        });
    }

    if (pagePrev) pagePrev.addEventListener('click', function () { state.page--; render(); });
    if (pageNext) pageNext.addEventListener('click', function () { state.page++; render(); });
    if (pageNumbers) {
        pageNumbers.addEventListener('click', function (e) {
            var btn = e.target.closest('.page-btn');
            if (!btn) return;
            state.page = parseInt(btn.getAttribute('data-page'), 10);
            render();
        });
    }

    var openOverlay = null;
    var lastFocus = null;
    var pressedOnBackdrop = false;

    function openModal(overlay, trigger) {
        if (openOverlay) closeModal(openOverlay, true);

        lastFocus = trigger || document.activeElement;
        var scrollbar = window.innerWidth - document.documentElement.clientWidth;
        if (scrollbar > 0) document.body.style.paddingRight = scrollbar + 'px';
        document.body.classList.add('dash-modal-open');

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        var body = overlay.querySelector('.dash-modal__body');
        if (body) body.scrollTop = 0;
        overlay.querySelector('.dash-modal').focus({ preventScroll: true });
        openOverlay = overlay;
    }

    function closeModal(overlay, keepFocusState) {
        if (!overlay || !overlay.classList.contains('is-open')) return;
        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        openOverlay = null;

        if (keepFocusState) return;
        document.body.classList.remove('dash-modal-open');
        document.body.style.paddingRight = '';
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
    }

    [$('receiptModal'), $('detailModal')].forEach(function (overlay) {
        if (!overlay) return;

        overlay.querySelectorAll('[data-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(overlay); });
        });

        overlay.addEventListener('mousedown', function (e) { pressedOnBackdrop = (e.target === overlay); });
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay && pressedOnBackdrop) closeModal(overlay);
            pressedOnBackdrop = false;
        });
    });

    document.addEventListener('keydown', function (e) {
        if (!openOverlay) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal(openOverlay);
            return;
        }
        if (e.key === 'Tab') {
            var modal = openOverlay.querySelector('.dash-modal');
            var focusable = Array.prototype.filter.call(
                modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
                function (el) { return !el.disabled && !el.hidden; }
            );
            if (!focusable.length) return;
            var first = focusable[0];
            var last = focusable[focusable.length - 1];

            if (e.shiftKey && (document.activeElement === first || document.activeElement === modal)) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    function fillFields(overlay, item) {
        overlay.querySelectorAll('[data-field]').forEach(function (el) {
            var v = item[el.getAttribute('data-field')];
            el.textContent = (v === undefined || v === null) ? '' : v;
        });
    }

    var receiptModal = $('receiptModal');
    var receiptImg = $('receiptImage');
    var receiptEmpty = $('receiptEmpty');
    var receiptNote = $('receiptNote');
    var reasonInput = $('rejectionReason');
    var reasonError = $('rejectionError');
    var btnReject = $('btnReject');
    var btnApprove = $('btnApprove');
    var btnCloseReceipt = $('btnCloseReceipt');
    var activeId = null;

    function isPrivateRequestItem(item) {
        if (!item) return false;
        return Boolean(
            item.is_private_request ||
            (String(item.id || '').indexOf('req-') === 0 && (item.status || 'pending') === 'pending')
        );
    }

    function showReceiptEmpty(message) {
        if (receiptImg) {
            receiptImg.onerror = null;
            receiptImg.hidden = true;
            receiptImg.removeAttribute('src');
        }
        if (receiptEmpty) {
            receiptEmpty.textContent = message;
            receiptEmpty.hidden = false;
        }
    }

    function openReceiptModal(id, trigger) {
        var item = bookings[id];
        if (!item || !receiptModal) return;
        activeId = id;

        fillFields(receiptModal, item);

        var status = item.status || 'pending';
        var isPrivateReq = isPrivateRequestItem(item);
        var receiptSrc = resolveReceiptSrc(item.receipt);

        var eyebrowEl = receiptModal.querySelector('.dash-modal__eyebrow');
        if (eyebrowEl) {
            eyebrowEl.textContent = isPrivateReq ? 'Verify Private Request' : 'Verify Payment Receipt';
        }

        if (receiptSrc) {
            receiptEmpty.hidden = true;
            receiptImg.hidden = false;
            receiptImg.alt = 'Payment receipt of ' + item.name + ' (' + item.display_id + ')';
            receiptImg.onerror = function () { showReceiptEmpty('The receipt image could not be loaded.'); };
            receiptImg.src = receiptSrc;
        } else {
            showReceiptEmpty(
                isPrivateReq
                    ? 'Private session request — customer will upload payment receipt after you approve this request.'
                    : 'No payment receipt has been uploaded yet.'
            );
        }

        if (reasonInput) {
            reasonInput.value = item.admin_note || item.rejection_reason || '';
            reasonInput.classList.remove('is-invalid');
            reasonInput.placeholder = isPrivateReq
                ? 'e.g., Jadwal bentrok, coach tidak tersedia, atau catatan persetujuan...'
                : 'e.g., Nominal transfer tidak sesuai, bukti buram, atau salah rekening tujuan...';
        }
        if (reasonError) reasonError.hidden = true;

        if (status === 'confirmed' || status === 'rejected') {
            if (reasonInput) reasonInput.disabled = true;
            if (btnReject) btnReject.hidden = true;
            if (btnApprove) btnApprove.hidden = true;
            if (btnCloseReceipt) btnCloseReceipt.hidden = false;

            receiptNote.hidden = false;
            receiptNote.textContent = status === 'confirmed'
                ? 'This payment has already been confirmed.'
                : (isPrivateReq ? 'This private request has been rejected.' : 'This payment has been rejected.');
        } else if (status === 'check' || (isPrivateReq && status === 'pending')) {
            if (reasonInput) reasonInput.disabled = false;
            if (btnCloseReceipt) btnCloseReceipt.hidden = true;

            if (btnReject) {
                btnReject.hidden = false;
                btnReject.disabled = false;
                btnReject.classList.remove('is-loading');
                btnReject.textContent = 'Reject';
            }

            if (btnApprove) {
                btnApprove.hidden = false;
                btnApprove.disabled = false;
                btnApprove.classList.remove('is-loading');
                btnApprove.textContent = isPrivateReq ? 'Accept Request' : 'Approve';
            }

            receiptNote.hidden = !isPrivateReq;
            receiptNote.textContent = isPrivateReq
                ? 'Accept this request so the customer can proceed with payment.'
                : '';
        } else {
            if (reasonInput) reasonInput.disabled = true;
            if (btnCloseReceipt) btnCloseReceipt.hidden = true;

            if (btnReject) {
                btnReject.hidden = false;
                btnReject.disabled = true;
                btnReject.classList.remove('is-loading');
                btnReject.textContent = 'Reject';
            }

            if (btnApprove) {
                btnApprove.hidden = false;
                btnApprove.disabled = true;
                btnApprove.classList.remove('is-loading');
                btnApprove.textContent = 'Approve';
            }

            receiptNote.hidden = false;
            receiptNote.textContent = 'Waiting for the customer to upload a receipt.';
        }

        openModal(receiptModal, trigger);
    }

    function setBookingStatus(id, status, note) {
        if (bookings[id]) {
            bookings[id].status = status;
            if (status === 'confirmed') {
                bookings[id].is_accepted = true;
            }
            if (note !== undefined) bookings[id].admin_note = note;
        }

        var row = tbody ? tbody.querySelector('tr[data-id="' + id + '"]') : null;
        if (!row) return;
        row.setAttribute('data-status', status);

        var typeCell = row.querySelector('.js-class-type-cell');
        if (typeCell && bookings[id]) {
            typeCell.innerHTML = renderClassTypeCell(bookings[id]);
        }

        var badge = row.querySelector('.status-badge');
        if (badge) {
            badge.className = 'status-badge status-badge--' + status + ' js-open-receipt';
            badge.textContent = statusLabels[status] || status;
            badge.setAttribute('aria-label', 'Payment receipt ' + id + ': ' + (statusLabels[status] || status));
        }
    }

    if (btnApprove) {
        btnApprove.addEventListener('click', function () {
            if (!activeId || btnApprove.disabled) return;
            var id = activeId;
            var note = reasonInput ? reasonInput.value.trim() : '';
            var wasPrivateReq = isPrivateRequestItem(bookings[id]);

            if (reasonInput) reasonInput.classList.remove('is-invalid');
            if (reasonError) reasonError.hidden = true;

            btnApprove.disabled = true;
            btnApprove.classList.add('is-loading');
            btnApprove.textContent = 'Saving…';
            if (btnReject) btnReject.disabled = true;

            postToEndpoint(verifyUrl, { booking_id: id, status: 'confirmed', note: note }).then(function (res) {
                btnApprove.classList.remove('is-loading');
                btnApprove.textContent = wasPrivateReq ? 'Accept Request' : 'Approve';

                if (!res || res.success === false) {
                    btnApprove.disabled = false;
                    if (btnReject) btnReject.disabled = false;
                    toast((res && res.message) || 'Failed to update status. Please try again.', true);
                    return;
                }

                if (wasPrivateReq) {
                    var reqDisplayId = (bookings[id] && bookings[id].display_id) || id;
                    closeModal(receiptModal);
                    toast(reqDisplayId + ' accepted' + (res.booking_code ? ' (' + res.booking_code + ')' : '') + '. Waiting for customer payment.');
                    syncDashboard(true);
                    return;
                }

                setBookingStatus(id, 'confirmed', note);
                render();
                closeModal(receiptModal);
                toast(bookings[id].display_id + ' payment approved.');
                syncDashboard(true);
            });
        });
    }

    if (btnReject) {
        btnReject.addEventListener('click', function () {
            if (!activeId || btnReject.disabled) return;
            var id = activeId;
            var reason = reasonInput ? reasonInput.value.trim() : '';
            var wasPrivateReq = isPrivateRequestItem(bookings[id]);

            if (!reason) {
                if (reasonInput) {
                    reasonInput.classList.add('is-invalid');
                    reasonInput.focus();
                }
                if (reasonError) reasonError.hidden = false;
                return;
            }

            if (reasonInput) reasonInput.classList.remove('is-invalid');
            if (reasonError) reasonError.hidden = true;

            btnReject.disabled = true;
            btnReject.classList.add('is-loading');
            btnReject.textContent = 'Rejecting…';
            if (btnApprove) btnApprove.disabled = true;

            postToEndpoint(verifyUrl, { booking_id: id, status: 'rejected', reason: reason }).then(function (res) {
                btnReject.classList.remove('is-loading');
                btnReject.textContent = 'Reject';

                if (!res || res.success === false) {
                    btnReject.disabled = false;
                    if (btnApprove) btnApprove.disabled = false;
                    toast((res && res.message) || 'Failed to reject. Please try again.', true);
                    return;
                }
                setBookingStatus(id, 'rejected', reason);
                render();
                closeModal(receiptModal);
                toast(bookings[id].display_id + (wasPrivateReq ? ' private request rejected.' : ' payment rejected.'));
                syncDashboard(true);
            });
        });
    }

    var detailModal = $('detailModal');
    var detailStatus = $('detailStatus');
    var detailClassType = $('detailClassType');

    function openDetailModal(id, trigger) {
        var item = bookings[id];
        if (!item || !detailModal) return;

        fillFields(detailModal, item);
        if (detailClassType) {
            detailClassType.innerHTML = renderClassTypeCell(item);
        }
        if (detailStatus) {
            detailStatus.className = 'status-badge status-badge--static status-badge--' + item.status;
            detailStatus.textContent = statusLabels[item.status] || item.status;
        }
        openModal(detailModal, trigger);
    }

    function deleteBooking(id, trigger) {
        var item = bookings[id];
        if (!item) return;
        if (!window.confirm('Delete booking ' + item.display_id + ' (' + item.name + ')? This cannot be undone.')) return;

        trigger.disabled = true;
        postToEndpoint(deleteUrl, { booking_id: id }).then(function (res) {
            trigger.disabled = false;
            if (!res || res.success === false) {
                toast((res && res.message) || 'Failed to delete the booking. Please try again.', true);
                return;
            }
            var row = tbody ? tbody.querySelector('tr[data-id="' + id + '"]') : null;
            if (row) row.remove();
            delete bookings[id];
            bookingOrder = bookingOrder.filter(function (bid) { return String(bid) !== String(id); });
            rebuildTableRows();
            render();
            toast(item.display_id + ' deleted.');
            syncDashboard(true);
        });
    }

    if (tbody) {
        tbody.addEventListener('click', function (e) {
            var receiptBtn = e.target.closest('.js-open-receipt');
            if (receiptBtn) { openReceiptModal(receiptBtn.getAttribute('data-id'), receiptBtn); return; }

            var viewBtn = e.target.closest('.js-view-detail');
            if (viewBtn) { openDetailModal(viewBtn.getAttribute('data-id'), viewBtn); return; }

            var delBtn = e.target.closest('.js-delete');
            if (delBtn) { deleteBooking(delBtn.getAttribute('data-id'), delBtn); }
        });
    }

    function rebuildTableRows() {
        if (!tbody) return;
        allRows().forEach(function (r) { r.remove(); });

        var fragment = document.createDocumentFragment();
        getSortedBookingIds(bookings, bookingOrder).forEach(function (id) {
            var b = bookings[id];
            var tr = document.createElement('tr');
            tr.setAttribute('data-id', b.id);
            tr.setAttribute('data-name', (b.name || '').toLowerCase());
            tr.setAttribute('data-date', b.date_iso || '');
            tr.setAttribute('data-status', b.status || '');

            var badgeLabel = statusLabels[b.status] || b.status;
            tr.innerHTML =
                '<td>' + escapeHtml(b.display_id || b.id) + '</td>' +
                '<td>' + escapeHtml(b.name) + '</td>' +
                '<td>' + escapeHtml(b.class_name) + '<br>' + escapeHtml(b.level) + '</td>' +
                '<td class="js-class-type-cell">' + renderClassTypeCell(b) + '</td>' +
                '<td>' + (b.date_table || '') + '</td>' +
                '<td>' + escapeHtml(b.medical) + '</td>' +
                '<td><button type="button" class="status-badge status-badge--' + escapeHtml(b.status) + ' js-open-receipt" data-id="' + escapeHtml(b.id) + '" aria-haspopup="dialog">' + escapeHtml(badgeLabel) + '</button></td>' +
                '<td><div class="row-actions">' +
                '<button type="button" class="icon-btn js-view-detail" data-id="' + escapeHtml(b.id) + '" aria-haspopup="dialog" title="View detail"><i class="ph-fill ph-eye" style="font-size: 24px;"></i></button>' +
                '<button type="button" class="icon-btn js-delete" data-id="' + escapeHtml(b.id) + '" title="Delete"><i class="ph-fill ph-trash" style="font-size: 23px;"></i></button>' +
                '</div></td>';

            fragment.appendChild(tr);
        });

        tbody.insertBefore(fragment, emptyRow);
    }

    var lastSnapshot = JSON.stringify({
        kpi: DATA.kpi || {},
        bookings: DATA.bookings || {},
        chart: DATA.chart || {}
    });

    function syncDashboard(force) {
        if (!fetchUrl) return;
        if (!force && (document.hidden || openOverlay)) return;

        fetch(fetchUrl, { credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (fresh) {
                if (!fresh || !fresh.bookings) return;

                var snapshot = JSON.stringify({
                    kpi: fresh.kpi || {},
                    bookings: fresh.bookings || {},
                    chart: fresh.chart || {}
                });
                if (!force && snapshot === lastSnapshot) return;
                lastSnapshot = snapshot;

                if (fresh.statusLabels) {
                    statusLabels = fresh.statusLabels;
                }

                if (fresh.kpi) {
                    Object.keys(fresh.kpi).forEach(function (key) {
                        var card = root.querySelector('[data-kpi-card="' + key + '"]');
                        if (!card) return;
                        var info = fresh.kpi[key];
                        var valEl = card.querySelector('.stat-card__value');
                        var trendEl = card.querySelector('.stat-card__trend');
                        var iconEl = trendEl ? trendEl.querySelector('i') : null;
                        var pctEl = trendEl ? trendEl.querySelector('strong') : null;

                        if (valEl) valEl.textContent = info.value;
                        if (pctEl) pctEl.textContent = info.pct;
                        if (trendEl) {
                            trendEl.classList.remove('stat-card__trend--up', 'stat-card__trend--down');
                            trendEl.classList.add('stat-card__trend--' + info.dir);
                        }
                        if (iconEl) {
                            iconEl.className = 'ph ph-trend-' + info.dir;
                        }
                    });
                }

                if (fresh.chart) {
                    DATA.chart = fresh.chart;
                    updateChart(true);
                }

                bookings = fresh.bookings;
                bookingOrder = Array.isArray(fresh.booking_order) ? fresh.booking_order.slice() : [];
                rebuildTableRows();
                render();
            })
            .catch(function (err) {
                console.warn('[dashboard.js] Auto-update gagal:', err);
            });
    }

    if (fetchUrl) {
        setInterval(function () { syncDashboard(false); }, 8000);
    }

    initChart();
    rebuildTableRows();
    render();
})();