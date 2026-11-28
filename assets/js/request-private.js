(function () {
    'use strict';

    var STORAGE_KEY = 'rp_last_submitted';
    var form = document.getElementById('rp-form');
    var successView = document.getElementById('rp-success-view');
    var editAgainBtn = document.getElementById('rp-edit-again');
    var tncModal = document.getElementById('tnc-modal');
    var openTnc = document.getElementById('bd-open-tnc');
    var acceptTnc = document.getElementById('tnc-accept');
    var agreeBox = document.getElementById('rp-agree');
    var submitBtn = document.getElementById('rp-submit');

    function escapeHtml(str) {
        return String(str == null ? '' : str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function renderSuccessView(data, shouldScroll) {
        if (!data || !successView) return;

        var codeText = data.booking_code
            ? '#' + String(data.booking_code).replace(/^#/, '')
            : 'REQ-0000';

        var codeEl = document.getElementById('rp-res-code');
        if (codeEl) codeEl.textContent = codeText;

        var heroEl = document.getElementById('rp-res-hero');
        if (heroEl && data.image) {
            heroEl.style.backgroundImage = "url('" + data.image + "')";
        }

        document.getElementById('rp-res-title').textContent = data.class_name || '';
        document.getElementById('rp-res-category').textContent = (data.category || '') + ' Class';
        document.getElementById('rp-res-date').textContent = data.date_readable || '-';
        document.getElementById('rp-res-time').textContent = data.time_readable || '-';
        document.getElementById('rp-res-coach').textContent = data.coach || 'Any Available Coach';
        document.getElementById('rp-res-level').textContent = data.level || 'Beginner';
        document.getElementById('rp-res-badge').innerHTML = escapeHtml(data.people_text || '1 Person') + ' &bull; Private';

        var bookingCodeLine = data.booking_code
            ? '&bull; Request ID: <strong>' + escapeHtml(codeText) + '</strong><br>'
            : '';

        document.getElementById('rp-res-details').innerHTML =
            '<strong>Submitted Details:</strong><br>' +
            bookingCodeLine +
            '&bull; Class: ' + escapeHtml(data.class_name) + ' (' + escapeHtml(data.people_text) + ')<br>' +
            '&bull; Level: ' + escapeHtml(data.level) + '<br>' +
            '&bull; Schedule: ' + escapeHtml(data.date_readable) + ', ' + escapeHtml(data.time_readable) + '<br>' +
            '&bull; Coach Preference: ' + escapeHtml(data.coach) + '<br>' +
            '&bull; Participants: ' + escapeHtml(data.participants) + '<br>' +
            '&bull; Lead Contact Phone: ' + escapeHtml(data.lead_phone);

        if (form) form.hidden = true;
        successView.hidden = false;

        if (shouldScroll) {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    }

    function restoreStateFromUrl() {
        var params = new URLSearchParams(window.location.search);
        if (params.get('submitted') !== '1') {
            return;
        }

        if (successView && !successView.hidden) {
            if (form) form.hidden = true;
            return;
        }

        try {
            var raw = sessionStorage.getItem(STORAGE_KEY);
            if (raw) {
                renderSuccessView(JSON.parse(raw), false);
            }
        } catch (err) {
            // Abaikan error storage
        }
    }

    restoreStateFromUrl();

    document.querySelectorAll('[data-toggle-panel]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var expanded = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
            var panel = document.getElementById(btn.getAttribute('aria-controls'));
            if (panel) panel.hidden = expanded;
        });
    });

    function closeTncModal() {
        if (!tncModal) return;
        tncModal.classList.remove('is-active');
        tncModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bd-modal-open');
    }

    if (openTnc && tncModal) {
        openTnc.addEventListener('click', function (e) {
            e.preventDefault();
            tncModal.classList.add('is-active');
            tncModal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('bd-modal-open');
        });
    }

    if (tncModal) {
        tncModal.addEventListener('click', function (e) {
            if (e.target === tncModal) closeTncModal();
        });
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && tncModal && tncModal.classList.contains('is-active')) {
            closeTncModal();
        }
    });

    if (!form) return;

    var maxAttendees = 4;
    form.dataset.max = String(maxAttendees);

    var submitEndpoint = form.dataset.endpoint || form.getAttribute('action') || '../assets/api/submit-private-request.php';
    var classSelect = document.getElementById('rp-class');
    var dateInput = document.getElementById('rp-date');
    var dayDisplay = document.getElementById('rp-day-display');
    var timeSelect = document.getElementById('rp-time');
    var coachSelect = document.getElementById('rp-coach');
    var levelSelect = document.getElementById('rp-level');
    var packagesBox = document.getElementById('rp-packages');
    var packagesHint = document.getElementById('rp-packages-hint');
    var clearPkgBtn = document.getElementById('rp-clear-package');
    var pkgError = document.getElementById('rp-package-error');
    var packageSelect = form.querySelector('select[name="package_id"], #rp-package, #bd-package-select');

    var attendeeList = document.getElementById('rp-attendee-list');
    var addAttendeeBtn = document.getElementById('rp-add-attendee');
    var attendeeTpl = document.getElementById('rp-attendee-template');
    var nextIndex = attendeeList ? attendeeList.querySelectorAll('[data-attendee]').length : 1;

    var cardSessionTitle = document.getElementById('rp-card-session-title');

    var sumHero = document.getElementById('rp-sum-hero');
    var sumTitle = document.getElementById('rp-sum-title');
    var sumCat = document.getElementById('rp-sum-category');
    var sumDate = document.getElementById('rp-sum-date');
    var sumTime = document.getElementById('rp-sum-time');
    var sumCoach = document.getElementById('rp-sum-coach');
    var sumLevel = document.getElementById('rp-sum-level');
    var slotsBadge = document.getElementById('rp-slots-badge');

    var rateLabel = document.getElementById('rp-rate-label');
    var subtotalEl = document.getElementById('rp-subtotal');
    var totalEl = document.getElementById('rp-total');

    function toLocalIsoDate(d) {
        var y = d.getFullYear();
        var m = d.getMonth() + 1;
        var day = d.getDate();
        return y + '-' + (m < 10 ? '0' + m : m) + '-' + (day < 10 ? '0' + day : day);
    }

    function getAllowedDateBounds() {
        var now = new Date();
        var minObj = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        var maxObj = new Date(now.getFullYear(), now.getMonth(), now.getDate() + 7);
        return {
            now: now,
            minObj: minObj,
            maxObj: maxObj,
            minIso: toLocalIsoDate(minObj),
            maxIso: toLocalIsoDate(maxObj)
        };
    }

    function syncDateAndTimeConstraints() {
        var bounds = getAllowedDateBounds();

        if (dateInput) {
            dateInput.min = bounds.minIso;
            dateInput.max = bounds.maxIso;

            if (!dateInput.value) {
                dateInput.value = bounds.minIso;
            }
        }

        var selectedDate = dateInput ? dateInput.value : bounds.minIso;
        if (selectedDate < bounds.minIso || selectedDate > bounds.maxIso) {
            var rangeMsg = 'Please select a date between today (' + bounds.minIso + ') and 7 days from now (' + bounds.maxIso + ').';
            if (dateInput) {
                dateInput.setCustomValidity(rangeMsg);
            }
            return {
                ok: false,
                message: rangeMsg,
                bounds: bounds
            };
        }

        if (dateInput) {
            dateInput.setCustomValidity('');
        }

        if (timeSelect) {
            var isToday = (selectedDate === bounds.minIso);
            var currentMinutes = bounds.now.getHours() * 60 + bounds.now.getMinutes();
            var options = Array.prototype.slice.call(timeSelect.options);
            var firstEnabled = null;
            var currentStillValid = false;

            options.forEach(function (opt) {
                var parts = (opt.value || '').split(':');
                var slotMinutes = parts.length === 2
                    ? (parseInt(parts[0], 10) * 60 + parseInt(parts[1], 10))
                    : 0;
                var disabled = isToday && slotMinutes <= currentMinutes;
                opt.disabled = disabled;

                if (!disabled) {
                    if (!firstEnabled) firstEnabled = opt;
                    if (opt.value === timeSelect.value) currentStillValid = true;
                }
            });

            if (!currentStillValid && firstEnabled) {
                timeSelect.value = firstEnabled.value;
            }

            if (isToday && !firstEnabled) {
                var noSlotMsg = 'All time slots for today have passed. Please choose another date within the next 7 days.';
                timeSelect.setCustomValidity(noSlotMsg);
                return {
                    ok: false,
                    message: noSlotMsg,
                    bounds: bounds
                };
            }

            timeSelect.setCustomValidity('');
        }

        return {
            ok: true,
            message: '',
            bounds: bounds
        };
    }

    function normalizeProgram(raw) {
        var v = String(raw || '').toLowerCase().trim();
        if (!v || v === 'all' || v === 'all classes' || v.indexOf('all class') > -1) return 'all';
        if (v.indexOf('pilates') > -1 || v.indexOf('reformer') > -1 || v.indexOf('mat') > -1) return 'reformer_pilates';
        if (v.indexOf('yoga') > -1) return 'regular_yoga';
        return v;
    }

    function normalizeClassType(raw) {
        var v = String(raw || '').toLowerCase().trim();
        if (!v) return '';
        if (v.indexOf('private group') > -1 || v.indexOf('private_group') > -1 || v.indexOf('private-group') > -1) {
            return 'private_group';
        }
        if (v.indexOf('private') > -1) return 'private';
        if (v.indexOf('group') > -1) return 'group';
        if (v === 'all' || v === 'all types' || v.indexOf('all type') > -1) return 'all';
        return '';
    }

    function attendeeCount() {
        return attendeeList ? attendeeList.querySelectorAll('[data-attendee]').length : 1;
    }

    function resolveTargetClassType(qty) {
        return qty > 1 ? 'private_group' : 'private';
    }

    function getSelectedPaymentMethod() {
        var checked = form.querySelector('input[name="payment_method"]:checked');
        return checked ? checked.value : 'dropin';
    }

    function resolveEffectivePackageRadio() {
        var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="package_id"]:not(:disabled)'));
        if (!radios.length) return null;

        var qty = attendeeCount();
        var checked = radios.find(function (r) { return r.checked; });
        if (checked) return checked;

        var enough = radios.find(function (r) {
            return (parseInt(r.dataset.remaining, 10) || 0) >= qty;
        });
        return enough || radios[0];
    }

    function getSelectedPackageId() {
        if (packageSelect && packageSelect.value) {
            return packageSelect.value;
        }
        var eff = resolveEffectivePackageRadio();
        return eff ? eff.value : '';
    }

    function syncPackages() {
        var opt = classSelect.options[classSelect.selectedIndex];
        var catLabel = opt ? (opt.dataset.category || opt.dataset.name || '') : '';
        var targetProg = normalizeProgram(catLabel);
        var qty = attendeeCount();
        var targetType = resolveTargetClassType(qty);
        var typeDisplayLabel = targetType === 'private_group' ? 'Private Group' : 'Private';
        var fullTargetLabel = (typeDisplayLabel + ' ' + catLabel).trim();

        var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="package_id"]'));
        var checkedRadio = null;
        var compatibleRadios = [];
        var firstCompat = null;
        var firstEnoughCompat = null;

        radios.forEach(function (r) {
            var pkgProg = normalizeProgram(r.dataset.program || r.dataset.pkgLabel || '');
            var pkgType = normalizeClassType(r.dataset.classType || r.dataset.pkgLabel || '') || 'group';

            var progCompat = (pkgProg === 'all' || !targetProg || targetProg === 'all' || pkgProg === targetProg);
            var typeCompat = (pkgType === 'all' || pkgType === targetType);
            var compat = progCompat && typeCompat;
            var rem = parseInt(r.dataset.remaining, 10) || 0;

            r.disabled = !compat;

            var wrap = r.closest('.bd-package');
            if (wrap) {
                wrap.classList.toggle('is-incompatible', !compat);
                wrap.classList.remove('is-auto-default');
                var lockEl = wrap.querySelector('.bd-package__lock');
                if (lockEl) {
                    lockEl.textContent = 'Not applicable for ' + fullTargetLabel;
                    lockEl.hidden = compat;
                }
                var tagEl = wrap.querySelector('[data-earliest-tag]');
                if (tagEl) {
                    tagEl.hidden = true;
                }
            }

            if (compat) {
                compatibleRadios.push(r);
                if (!firstCompat) firstCompat = r;
                if (!firstEnoughCompat && rem >= qty) firstEnoughCompat = r;
            }

            if (r.checked) {
                if (!compat) {
                    r.checked = false;
                } else {
                    checkedRadio = r;
                }
            }
        });

        var hasMultipleSamePackages = compatibleRadios.length > 1;
        if (hasMultipleSamePackages && compatibleRadios[0]) {
            var firstWrap = compatibleRadios[0].closest('.bd-package');
            var firstTag = firstWrap ? firstWrap.querySelector('[data-earliest-tag]') : null;
            if (firstTag) {
                firstTag.hidden = false;
            }
        }

        if (packagesHint) {
            packagesHint.textContent = hasMultipleSamePackages
                ? 'Select the package you want to use, or leave unselected to automatically use your earliest purchased package.'
                : 'Select the package you want or leave unselected to automatically use your package.';
        }

        var effectiveRadio = checkedRadio || firstEnoughCompat || firstCompat;
        if (!checkedRadio && effectiveRadio) {
            var defWrap = effectiveRadio.closest('.bd-package');
            if (defWrap) defWrap.classList.add('is-auto-default');
        }

        if (clearPkgBtn) {
            clearPkgBtn.textContent = hasMultipleSamePackages ? 'Use Earliest (Default)' : 'Use Default Package';
            clearPkgBtn.hidden = !checkedRadio;
        }

        var isPkg = getSelectedPaymentMethod() === 'package';
        if (packagesBox) {
            packagesBox.hidden = !isPkg;
        }

        var errorMsg = '';
        if (isPkg) {
            if (!radios.length || !effectiveRadio) {
                errorMsg = 'No compatible active package available for ' + fullTargetLabel + '.';
            } else {
                var remSessions = parseInt(effectiveRadio.dataset.remaining, 10) || 0;
                if (remSessions < qty) {
                    errorMsg = 'Your package does not have enough remaining sessions.';
                }
            }
        }

        if (pkgError) {
            pkgError.textContent = errorMsg;
        }

        return {
            ok: errorMsg === '',
            message: errorMsg
        };
    }

    if (clearPkgBtn) {
        clearPkgBtn.addEventListener('click', function () {
            form.querySelectorAll('input[name="package_id"]').forEach(function (r) {
                r.checked = false;
            });
            updateSummary();
        });
    }

    function updateAttendeeTitles() {
        if (!attendeeList) return;
        attendeeList.querySelectorAll('[data-attendee]').forEach(function (card, index) {
            var title = card.querySelector('.js-attendee-title');
            var nameInput = card.querySelector('[data-attendee-name]');
            if (title) {
                var val = nameInput ? nameInput.value.trim() : '';
                title.textContent = val || 'Attendee ' + (index + 1);
            }
        });
    }

    function setAttendeeOpen(card, open) {
        var btn = card.querySelector('[data-toggle-attendee]');
        if (!btn) return;
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        var panel = document.getElementById(btn.getAttribute('aria-controls'));
        if (panel) panel.hidden = !open;
    }

    function parseLocalDate(isoDate) {
        if (!isoDate) return null;
        var parts = isoDate.split('-');
        if (parts.length !== 3) return null;
        return new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
    }

    function formatDateReadable(isoDate) {
        var d = parseLocalDate(isoDate);
        if (!d || isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('en-GB', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
    }

    function formatDateShort(isoDate) {
        var d = parseLocalDate(isoDate);
        if (!d || isNaN(d.getTime())) return '';
        return d.toLocaleDateString('en-GB', {
            weekday: 'short',
            day: 'numeric',
            month: 'short'
        });
    }

    function formatDayOnly(isoDate) {
        var d = parseLocalDate(isoDate);
        if (!d || isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('en-GB', { weekday: 'long' });
    }

    function formatTimeRange(startStr) {
        if (!startStr || startStr.indexOf(':') === -1) return '-';
        var p = startStr.split(':');
        var h = parseInt(p[0], 10);
        var m = parseInt(p[1], 10);
        var endH = (h + 1) % 24;
        var pad = function (n) {
            return n < 10 ? '0' + n : String(n);
        };
        return pad(h) + '.' + pad(m) + ' - ' + pad(endH) + '.' + pad(m) + ' WIB';
    }

    function updateSummary() {
        var dateState = syncDateAndTimeConstraints();
        var opt = classSelect.options[classSelect.selectedIndex];
        var unitPrice = parseInt(opt.dataset.price, 10) || 0;
        var qty = attendeeCount();
        var subtotal = unitPrice * qty;
        var paymentMethod = getSelectedPaymentMethod();
        var isPackage = paymentMethod === 'package';
        var total = isPackage ? 0 : subtotal;
        var readableDate = formatDateReadable(dateInput.value);
        var shortDate = formatDateShort(dateInput.value);
        var readableTime = formatTimeRange(timeSelect.value);
        var peopleText = qty + (qty === 1 ? ' Person' : ' People');
        var pkgState = syncPackages();

        if (dayDisplay) {
            dayDisplay.innerHTML = 'Day: <strong>' + formatDayOnly(dateInput.value) + '</strong>';
        }
        if (cardSessionTitle) {
            cardSessionTitle.textContent = opt.dataset.name + (shortDate ? ' • ' + shortDate : '');
        }

        sumHero.style.backgroundImage = "url('" + opt.dataset.image + "')";
        sumTitle.textContent = opt.dataset.name;
        sumCat.textContent = opt.dataset.category + ' Class';
        sumDate.textContent = readableDate;
        sumTime.textContent = readableTime;
        sumCoach.textContent = coachSelect.value || 'Any Available Coach';
        sumLevel.textContent = levelSelect ? levelSelect.value : opt.dataset.level;
        slotsBadge.innerHTML = peopleText + ' &bull; ' + (qty > 1 ? 'Private Group' : 'Private');

        rateLabel.innerHTML = isPackage
            ? 'Subtotal (' + qty + ' &times; ' + qty + ' Package Session' + (qty > 1 ? 's' : '') + ')'
            : 'Subtotal (' + qty + ' &times; Rp' + unitPrice.toLocaleString('en-US') + ')';
        subtotalEl.innerHTML = '<em>Rp</em>' + subtotal.toLocaleString('en-US');
        totalEl.innerHTML = '<em>Rp</em>' + total.toLocaleString('en-US');

        if (addAttendeeBtn) {
            addAttendeeBtn.disabled = qty >= maxAttendees;
            addAttendeeBtn.title = qty >= maxAttendees ? 'Maximum ' + maxAttendees + ' participants per private request' : '';
        }

        submitBtn.disabled = !agreeBox.checked || !pkgState.ok || !dateState.ok;
    }

    if (acceptTnc && tncModal) {
        acceptTnc.addEventListener('click', function () {
            closeTncModal();
            if (agreeBox) {
                agreeBox.checked = true;
            }
            updateSummary();
        });
    }

    if (addAttendeeBtn && attendeeList && attendeeTpl) {
        addAttendeeBtn.addEventListener('click', function () {
            if (attendeeCount() >= maxAttendees) return;

            attendeeList.querySelectorAll('[data-attendee]').forEach(function (c) {
                setAttendeeOpen(c, false);
            });

            attendeeList.insertAdjacentHTML(
                'beforeend',
                attendeeTpl.innerHTML.split('__INDEX__').join(String(nextIndex++))
            );

            updateAttendeeTitles();
            updateSummary();
        });
    }

    if (attendeeList) {
        attendeeList.addEventListener('click', function (e) {
            var toggle = e.target.closest('[data-toggle-attendee]');
            if (toggle) {
                var card = toggle.closest('[data-attendee]');
                setAttendeeOpen(card, toggle.getAttribute('aria-expanded') !== 'true');
                return;
            }

            var rm = e.target.closest('[data-remove-attendee]');
            if (rm) {
                rm.closest('[data-attendee]').remove();
                updateAttendeeTitles();
                updateSummary();
            }
        });

        attendeeList.addEventListener('input', function (e) {
            var input = e.target;
            if (input.type === 'tel') {
                input.value = input.value.replace(/[^\d+]/g, '');
            }
            if (input.matches('[data-attendee-name]')) {
                updateAttendeeTitles();
            }
        });
    }

    [classSelect, dateInput, timeSelect, coachSelect, levelSelect, agreeBox, packageSelect].forEach(function (el) {
        if (el) el.addEventListener('change', updateSummary);
    });

    if (dateInput) {
        dateInput.addEventListener('input', updateSummary);
    }

    form.querySelectorAll('input[name="payment_method"], input[name="package_id"]').forEach(function (radio) {
        radio.addEventListener('change', updateSummary);
    });

    function showFormError(message) {
        var errBox = document.getElementById('rp-submit-error');
        if (!errBox) {
            errBox = document.createElement('p');
            errBox.id = 'rp-submit-error';
            errBox.className = 'rp-submit-error';
            submitBtn.parentNode.insertBefore(errBox, submitBtn);
        }
        errBox.textContent = message;
        errBox.hidden = false;
    }

    function hideFormError() {
        var errBox = document.getElementById('rp-submit-error');
        if (errBox) errBox.hidden = true;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        hideFormError();

        var dateState = syncDateAndTimeConstraints();
        if (!dateState.ok) {
            showFormError(dateState.message);
            form.reportValidity();
            return;
        }

        if (attendeeCount() > maxAttendees) {
            showFormError('Maximum ' + maxAttendees + ' participants per private request.');
            return;
        }

        var paymentMethod = getSelectedPaymentMethod();
        var selectedPkgId = getSelectedPackageId();
        var pkgState = syncPackages();

        if (paymentMethod === 'package' && (!selectedPkgId || !pkgState.ok)) {
            showFormError(pkgState.message || 'Please select an active package before submitting.');
            return;
        }

        if (!form.checkValidity()) {
            form.querySelectorAll('[data-rp-card]').forEach(function (card) {
                if (card.querySelector(':invalid')) {
                    var toggle = card.querySelector('[data-toggle-panel]');
                    var panel = document.getElementById(toggle.getAttribute('aria-controls'));
                    toggle.setAttribute('aria-expanded', 'true');
                    if (panel) panel.hidden = false;
                }
            });
            attendeeList.querySelectorAll('[data-attendee]').forEach(function (c) {
                if (c.querySelector(':invalid')) setAttendeeOpen(c, true);
            });
            form.reportValidity();
            return;
        }

        if (!agreeBox.checked) {
            agreeBox.focus();
            return;
        }

        var opt = classSelect.options[classSelect.selectedIndex];
        var cards = attendeeList.querySelectorAll('[data-attendee]');
        var qty = cards.length;
        var readableDate = formatDateReadable(dateInput.value);
        var readableTime = formatTimeRange(timeSelect.value);
        var coachText = coachSelect.value || 'Any Available Coach';
        var levelText = levelSelect ? levelSelect.value : opt.dataset.level;
        var peopleText = qty + (qty === 1 ? ' Person' : ' People');

        var names = [];
        var payload = new FormData(form);
        payload.set('class_type', qty > 1 ? 'Private Group' : 'Private');
        payload.set('class_id', opt.dataset.classId || opt.value || '');
        payload.set('class_name', opt.dataset.name || '');
        payload.set('category', opt.dataset.category || '');
        payload.set('level', levelText);
        payload.set('price', opt.dataset.price || '0');
        payload.set('date', dateInput.value || '');
        payload.set('time', timeSelect.value || '');
        payload.set('coach', coachSelect.value || '');
        payload.set('attendee_count', String(qty));
        payload.set('payment_method', paymentMethod);
        payload.set('package_id', (paymentMethod === 'package' && selectedPkgId) ? selectedPkgId : '');
        payload.set('user_package_id', (paymentMethod === 'package' && selectedPkgId) ? selectedPkgId : '');

        cards.forEach(function (c, i) {
            var nInput = c.querySelector('[data-attendee-name]');
            var pInput = c.querySelector('input[type="tel"]');
            var eInput = c.querySelector('input[type="email"]');
            var mInput = c.querySelector('textarea, input[name*="medical"]');
            var npInput = c.querySelector('input[type="checkbox"]');

            var nVal = nInput && nInput.value.trim() ? nInput.value.trim() : 'Attendee ' + (i + 1);
            names.push(nVal);

            payload.set('attendees[' + i + '][name]', nVal);
            payload.set('attendees[' + i + '][phone]', pInput ? pInput.value.trim() : '');
            payload.set('attendees[' + i + '][email]', eInput ? eInput.value.trim() : '');
            payload.set('attendees[' + i + '][medical]', mInput ? mInput.value.trim() : '');
            payload.set('attendees[' + i + '][not_pregnant]', (npInput && npInput.checked) ? '1' : '0');
        });

        var leadPhoneInput = cards[0] ? cards[0].querySelector('input[type="tel"]') : null;
        var leadPhone = leadPhoneInput ? leadPhoneInput.value.trim() : '-';

        var originalBtnText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';

        fetch(submitEndpoint, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        })
            .then(function (res) {
                return res.json().catch(function () {
                    return { success: false, message: 'Invalid server response (' + res.status + ').' };
                }).then(function (json) {
                    if (!res.ok) json.success = false;
                    return json;
                });
            })
            .then(function (res) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;

                if (!res || res.success === false) {
                    showFormError((res && res.message) || 'Failed to submit private class request. Please try again.');
                    return;
                }

                var summaryData = {
                    booking_code: res.booking_code || '',
                    image: opt.dataset.image || '',
                    class_name: opt.dataset.name || '',
                    category: opt.dataset.category || '',
                    date_readable: readableDate,
                    time_readable: readableTime,
                    coach: coachText,
                    level: levelText,
                    people_text: peopleText,
                    participants: names.join(', '),
                    lead_phone: leadPhone
                };

                try {
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(summaryData));
                } catch (err) {
                    // Abaikan error storage
                }

                var url = new URL(window.location.href);
                url.searchParams.set('submitted', '1');
                window.history.replaceState({ submitted: true }, '', url.toString());

                renderSuccessView(summaryData, true);
            })
            .catch(function (err) {
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
                showFormError(err && err.message ? err.message : 'Network error. Please try again.');
            });
    });

    if (editAgainBtn) {
        editAgainBtn.addEventListener('click', function () {
            hideFormError();

            try {
                sessionStorage.removeItem(STORAGE_KEY);
            } catch (err) {
                // Abaikan error storage
            }

            var url = new URL(window.location.href);
            url.searchParams.delete('submitted');
            window.history.replaceState(null, '', url.pathname + (url.search ? url.search : ''));

            if (agreeBox) agreeBox.checked = false;
            if (successView) successView.hidden = true;
            form.hidden = false;
            updateSummary();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    updateAttendeeTitles();
    updateSummary();
})();