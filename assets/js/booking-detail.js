document.addEventListener('DOMContentLoaded', () => {
    const paymentOptions = document.querySelectorAll('.payment-option');
    const paymentRadios = document.querySelectorAll(
        '.payment-option input[type="radio"]'
    );

    function updatePaymentSelection() {
        paymentOptions.forEach(option => {
            option.classList.remove('selected');
        });

        const checkedRadio = document.querySelector(
            '.payment-option input[type="radio"]:checked'
        );

        if (checkedRadio) {
            checkedRadio.closest('.payment-option')
                .classList.add('selected');
        }
    }

    paymentRadios.forEach(radio => {
        radio.addEventListener('change', updatePaymentSelection);
    });

    updatePaymentSelection();
});


document.addEventListener('DOMContentLoaded', () => {
    const tncModal = document.getElementById('tnc-modal');
    const openTncBtn = document.getElementById('bd-open-tnc');
    const acceptTncBtn = document.getElementById('tnc-accept');
    const agreeCheckbox = document.getElementById('bd-agree');

    if (!tncModal || !openTncBtn || !acceptTncBtn || !agreeCheckbox) {
        return;
    }

    function openTnc() {
        tncModal.classList.add('is-active');
        tncModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';

        acceptTncBtn.focus();
    }

    function closeTnc() {
        tncModal.classList.remove('is-active');
        tncModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';

        openTncBtn.focus();
    }

    openTncBtn.addEventListener('click', function (e) {
        e.preventDefault();
        openTnc();
    });

    acceptTncBtn.addEventListener('click', function () {
        agreeCheckbox.checked = true;

        agreeCheckbox.dispatchEvent(new Event('change', {
            bubbles: true
        }));

        closeTnc();
    });
});


document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bd-form');
    const voucherInput = document.getElementById('bd-voucher-input');
    const applyBtn = document.getElementById('bd-apply-voucher');
    const removeBtn = document.getElementById('bd-remove-voucher');
    const feedbackEl = document.getElementById('bd-voucher-feedback');
    const hiddenCodeInput = document.getElementById('bd-voucher-code');

    const subtotalEl = document.getElementById('bd-subtotal');
    const totalEl = document.getElementById('bd-total');
    const appliedRow = document.getElementById('bd-voucher-applied');
    const appliedBadge = document.getElementById('bd-voucher-badge');
    const appliedDesc = document.getElementById('bd-voucher-desc');
    const discountEl = document.getElementById('bd-voucher-discount');

    if (!voucherInput || !applyBtn || !subtotalEl || !totalEl) {
        return;
    }

    const startTimeStr = form ? (form.dataset.startTime || '') : '';
    const birthdateStr = form ? (form.dataset.birthdate || '') : '';
    const isPrivateAccepted = form ? form.dataset.privateAccepted === '1' : false;

    const VOUCHERS = {
        GENZ100: {
            title: 'Calling All Gen Z',
            description: 'Calling All Gen Z — Promo Rp100.000 / Session',
            type: 'per_person',
            price: 100000
        },
        SUMMER400: {
            title: 'Summer Day Out',
            description: 'Summer Day Out — Paket Rp400.000 / 4 Orang',
            type: 'bundle_4',
            price: 400000
        },
        HAPPYHOUR135: {
            title: 'Happy Hour Promo',
            description: 'Happy Hour Promo — Rp135.000 / Session',
            type: 'per_person',
            price: 135000
        }
    };

    let activeCode = null;
    let lastUnitPrice = 120000;
    let lastAttendeeCount = 1;
    let lastIsPackage = false;
    let isCurrentVoucherEligible = true;

    function formatRp(num) {
        return '<em>Rp</em>' + Math.max(0, num).toLocaleString('en-US');
    }

    function parseStartMinutes(timeStr) {
        const match = /^([01]\d|2[0-3]):([0-5]\d)$/.exec(timeStr.trim());
        if (!match) return null;
        return parseInt(match[1], 10) * 60 + parseInt(match[2], 10);
    }

    function validateVoucherRules(code, attendeeCount, unitPrice) {
        if (!VOUCHERS[code]) {
            return 'Kode voucher tidak valid atau tidak berlaku.';
        }

        if (code === 'SUMMER400') {
            if (!isPrivateAccepted) {
                return 'Voucher Summer Day Out hanya bisa digunakan jika Anda sudah melakukan Request Private dan telah di-accept oleh admin.';
            }
            if (attendeeCount < 4 || attendeeCount % 4 !== 0) {
                return 'Voucher Summer Day Out hanya berlaku jika jumlah peserta (termasuk attendee) berjumlah 4 atau kelipatan 4 (saat ini: ' + attendeeCount + ' peserta).';
            }
            return null;
        }

        if (code === 'GENZ100') {
            const yearMatch = /(19\d{2}|20\d{2})/.exec(birthdateStr.trim());
            const birthYear = yearMatch ? parseInt(yearMatch[1], 10) : 0;
            if (birthYear < 1997 || birthYear > 2012) {
                return 'Voucher Calling All Gen Z hanya dapat dipakai oleh customer dengan tahun lahir 1997 - 2012.';
            }
            if (unitPrice < 100000) {
                return 'Voucher Calling All Gen Z hanya berlaku untuk session dengan harga Rp100.000 ke atas.';
            }
            return null;
        }

        if (code === 'HAPPYHOUR135') {
            const startMins = parseStartMinutes(startTimeStr);
            const isSlot1 = startMins !== null && startMins >= 11 * 60 && startMins <= 14 * 60;
            const isSlot2 = startMins !== null && startMins >= 15 * 60 && startMins <= 17 * 60;
            if (!isSlot1 && !isSlot2) {
                return 'Voucher Happy Hour hanya berlaku untuk reservasi kelas pada pukul 11.00 - 14.00 atau 15.00 - 17.00.';
            }
            return null;
        }

        return null;
    }

    function computeTotal(unitPrice, attendeeCount, isPackage = false) {
        if (isPackage) {
            return 0;
        }
        const subtotal = unitPrice * attendeeCount;
        if (activeCode && VOUCHERS[activeCode] && isCurrentVoucherEligible) {
            const voucher = VOUCHERS[activeCode];
            if (voucher.type === 'bundle_4') {
                return (attendeeCount / 4) * voucher.price;
            }
            return voucher.price * attendeeCount;
        }
        return subtotal;
    }

    function calculateAndRender() {
        const subtotal = lastUnitPrice * lastAttendeeCount;
        subtotalEl.innerHTML = formatRp(subtotal);

        if (lastIsPackage) {
            isCurrentVoucherEligible = true;
            if (appliedRow) appliedRow.hidden = true;
            totalEl.innerHTML = formatRp(0);
            if (hiddenCodeInput) hiddenCodeInput.value = '';
            return;
        }

        if (activeCode && VOUCHERS[activeCode]) {
            const ruleError = validateVoucherRules(activeCode, lastAttendeeCount, lastUnitPrice);
            if (ruleError) {
                isCurrentVoucherEligible = false;
                appliedRow.hidden = true;
                totalEl.innerHTML = formatRp(subtotal);
                if (hiddenCodeInput) hiddenCodeInput.value = '';

                feedbackEl.textContent = ruleError;
                feedbackEl.className = 'bd-voucher__feedback is-error';
                return;
            }

            isCurrentVoucherEligible = true;
            const voucher = VOUCHERS[activeCode];
            const finalTotal = computeTotal(lastUnitPrice, lastAttendeeCount, false);
            const diff = subtotal - finalTotal;

            appliedRow.hidden = false;
            appliedBadge.textContent = activeCode;
            appliedDesc.textContent = voucher.description;

            if (diff > 0) {
                discountEl.innerHTML = '-' + formatRp(diff);
            } else if (diff < 0) {
                discountEl.textContent = 'Tarif Promo';
            } else {
                discountEl.innerHTML = formatRp(0);
            }

            feedbackEl.textContent = 'Voucher "' + voucher.title + '" berhasil digunakan.';
            feedbackEl.className = 'bd-voucher__feedback is-success';

            totalEl.innerHTML = formatRp(finalTotal);
            if (hiddenCodeInput) hiddenCodeInput.value = activeCode;
        } else {
            isCurrentVoucherEligible = true;
            appliedRow.hidden = true;
            totalEl.innerHTML = formatRp(subtotal);
            if (hiddenCodeInput) hiddenCodeInput.value = '';
        }
    }

    window.bdRefreshVoucher = function (unitPrice, attendeeCount, isPackage) {
        lastUnitPrice = unitPrice;
        lastAttendeeCount = attendeeCount;
        lastIsPackage = Boolean(isPackage);
        calculateAndRender();
    };

    window.bdGetFinalTotal = function (unitPrice, attendeeCount) {
        return computeTotal(unitPrice, attendeeCount, lastIsPackage);
    };

    window.bdIsVoucherValid = function () {
        if (lastIsPackage || !activeCode) return true;
        return validateVoucherRules(activeCode, lastAttendeeCount, lastUnitPrice) === null;
    };

    function applyVoucher() {
        if (lastIsPackage) {
            feedbackEl.textContent = 'Voucher hanya berlaku untuk metode Drop-in Payment.';
            feedbackEl.className = 'bd-voucher__feedback is-error';
            return;
        }

        const code = voucherInput.value.trim().toUpperCase();

        if (!code) {
            feedbackEl.textContent = 'Silakan masukkan kode voucher terlebih dahulu.';
            feedbackEl.className = 'bd-voucher__feedback is-error';
            return;
        }

        const ruleError = validateVoucherRules(code, lastAttendeeCount, lastUnitPrice);
        if (ruleError) {
            feedbackEl.textContent = ruleError;
            feedbackEl.className = 'bd-voucher__feedback is-error';
            return;
        }

        activeCode = code;
        voucherInput.value = code;
        voucherInput.disabled = true;
        applyBtn.hidden = true;
        removeBtn.hidden = false;

        calculateAndRender();
        document.dispatchEvent(new Event('bd:voucher-changed'));
    }

    function removeVoucher() {
        activeCode = null;
        isCurrentVoucherEligible = true;
        voucherInput.value = '';
        voucherInput.disabled = false;
        applyBtn.hidden = false;
        removeBtn.hidden = true;
        feedbackEl.textContent = '';
        feedbackEl.className = 'bd-voucher__feedback';

        calculateAndRender();
        document.dispatchEvent(new Event('bd:voucher-changed'));
    }

    applyBtn.addEventListener('click', applyVoucher);
    removeBtn.addEventListener('click', removeVoucher);

    voucherInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            applyVoucher();
        }
    });

    if (form) {
        const checkedMethod = form.querySelector('input[name="payment_method"]:checked');
        lastUnitPrice = parseInt(form.dataset.price, 10) || 120000;
        lastAttendeeCount = document.querySelectorAll('[data-attendee]').length || 1;
        lastIsPackage = checkedMethod ? checkedMethod.value === 'package' : false;
        calculateAndRender();
    }
});


document.addEventListener('DOMContentLoaded', () => {
    var form = document.getElementById('bd-form');
    if (!form) return;

    var price = parseInt(form.dataset.price, 10) || 0;
    var bookingLimit = parseInt(form.dataset.limit, 10) || 2;
    var max = Math.min(bookingLimit, parseInt(form.dataset.max, 10) || 1);
    var totalSlots = parseInt(form.dataset.totalSlots, 10) || 0;
    var list = document.getElementById('bd-attendee-list');
    var addBtn = document.getElementById('bd-add-attendee');
    var backBtn = document.getElementById('bd-back-btn');
    var slotsBadge = document.getElementById('bd-slots-badge');
    var scheduleInput = form.querySelector('input[name="schedule_id"]');
    var scheduleId = scheduleInput ? scheduleInput.value : '';
    var tpl = document.getElementById('bd-attendee-template');
    var subtotalEl = document.getElementById('bd-subtotal');
    var totalEl = document.getElementById('bd-total');
    var totalBox = document.getElementById('bd-total-box');
    var packagesBox = document.getElementById('bd-packages');
    var packagesHint = document.getElementById('bd-packages-hint');
    var clearPkgBtn = document.getElementById('bd-clear-package');
    var packageError = document.getElementById('bd-package-error');
    var agree = document.getElementById('bd-agree');
    var continueBtn = document.getElementById('bd-continue');
    var continueLabel = continueBtn.querySelector('span');
    var continueText = continueLabel ? continueLabel.textContent : 'Continue Payment';
    var endpoint = form.dataset.endpoint;
    var checking = false;

    var slotModal = document.getElementById('bd-slot-modal');
    var slotTitle = document.getElementById('bd-slot-title');
    var slotText = document.getElementById('bd-slot-text');
    var slotBack = document.getElementById('bd-slot-back');
    var slotRefresh = document.getElementById('bd-slot-refresh');
    var lastFocus = null;
    var nextIndex = list.querySelectorAll('[data-attendee]').length;

    function normalizeProgram(raw) {
        var v = String(raw || '').toLowerCase().trim();
        if (!v || v === 'all' || v === 'all classes' || v.indexOf('all class') > -1) return 'all';
        if (v.indexOf('pilates') > -1 || v.indexOf('reformer') > -1) return 'reformer_pilates';
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

    function resolveTargetClassType(attendeeQty) {
        var baseType = normalizeClassType(form.dataset.classType || '');
        if (!baseType) {
            baseType = normalizeClassType(form.dataset.className || '');
        }
        if (baseType === 'private') {
            return attendeeQty > 1 ? 'private_group' : 'private';
        }
        if (baseType === 'private_group') {
            return 'private_group';
        }
        return 'group';
    }

    function getTargetFullLabel(resolvedType) {
        var progKey = normalizeProgram(form.dataset.classCategory || form.dataset.className || '');
        var progLabel = progKey === 'reformer_pilates' ?
            'Reformer Pilates' :
            (progKey === 'regular_yoga' ? 'Regular Yoga' : (form.dataset.classCategory || form.dataset.className || ''));
        var typeLabel = resolvedType === 'private_group' ?
            'Private Group' :
            (resolvedType === 'private' ? 'Private' : 'Group');
        return (typeLabel + ' ' + progLabel).trim();
    }

    function syncPackageCompatibility(requiredSessions) {
        var targetProg = normalizeProgram(form.dataset.classCategory || form.dataset.className || '');
        var targetType = resolveTargetClassType(requiredSessions);
        var targetLabel = getTargetFullLabel(targetType);
        var radios = Array.prototype.slice.call(form.querySelectorAll('input[name="package_id"]'));
        if (!radios.length) return null;

        var checkedRadio = null;
        var compatibleRadios = [];
        var firstCompatible = null;
        var firstEnoughCompatible = null;

        radios.forEach(function (r) {
            var pkgProg = normalizeProgram(r.dataset.program || r.dataset.pkgLabel || '');
            var pkgType = normalizeClassType(r.dataset.classType || r.dataset.pkgLabel || '') || 'group';

            var progCompat = (pkgProg === 'all' || !targetProg || targetProg === 'all' || pkgProg === targetProg);
            var typeCompat = (pkgType === 'all' || pkgType === targetType);
            var compatible = progCompat && typeCompat;

            var rem = parseInt(r.dataset.remaining, 10) || 0;
            r.disabled = !compatible;

            var wrap = r.closest('.bd-package');
            if (wrap) {
                wrap.classList.toggle('is-incompatible', !compatible);
                wrap.classList.remove('is-auto-default');
                var lockEl = wrap.querySelector('.bd-package__lock');
                if (lockEl) {
                    lockEl.textContent = 'Not applicable for ' + targetLabel;
                    lockEl.hidden = compatible;
                }
                var tagEl = wrap.querySelector('[data-earliest-tag]');
                if (tagEl) {
                    tagEl.hidden = true;
                }
            }

            if (compatible) {
                compatibleRadios.push(r);
                if (!firstCompatible) firstCompatible = r;
                if (!firstEnoughCompatible && rem >= requiredSessions) firstEnoughCompatible = r;
            }

            if (r.checked) {
                if (!compatible) {
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
        } else if (compatibleRadios.length === 1) {
            compatibleRadios[0].checked = true;
            checkedRadio = compatibleRadios[0];
        }

        if (packagesHint) {
            packagesHint.textContent = hasMultipleSamePackages ?
                'Select the package you want to use, or leave unselected to automatically use your earliest purchased package.' :
                'Your compatible package is selected automatically.';
        }

        var effectiveRadio = checkedRadio || firstEnoughCompatible || firstCompatible;
        if (!checkedRadio && effectiveRadio) {
            var defWrap = effectiveRadio.closest('.bd-package');
            if (defWrap) defWrap.classList.add('is-auto-default');
        }
        if (clearPkgBtn) {
            clearPkgBtn.textContent = 'Use Earliest Package';
            clearPkgBtn.hidden = !hasMultipleSamePackages || !checkedRadio;
        }
        return effectiveRadio;
    }

    function updateAttendeeTitles() {
        list.querySelectorAll('[data-attendee]').forEach(function (card, index) {
            var title = card.querySelector('.js-attendee-title');
            var nameInput = card.querySelector('[data-attendee-name]');
            if (title) {
                var attendeeName = nameInput ? nameInput.value.trim() : '';
                title.textContent = attendeeName || 'Attendee ' + (index + 1);
            }
        });
    }

    function method() {
        var r = form.querySelector('input[name="payment_method"]:checked');
        return r ? r.value : 'dropin';
    }

    function count() {
        return list.querySelectorAll('[data-attendee]').length;
    }

    function resolveEffectivePackageRadio() {
        return syncPackageCompatibility(count());
    }

    function pkgRemaining() {
        var r = resolveEffectivePackageRadio();
        return r ? parseInt(r.dataset.remaining, 10) : 0;
    }

    function hasCompatiblePackage() {
        return form.querySelectorAll('input[name="package_id"]:not(:disabled)').length > 0;
    }

    function refresh() {
        var n = count(),
            isPackage = method() === 'package';
        var effectivePkg = syncPackageCompatibility(n);
        var targetLabel = getTargetFullLabel(resolveTargetClassType(n));
        var amount = '<em>Rp</em>' + (price * n).toLocaleString('en-US');
        subtotalEl.innerHTML = amount;
        totalEl.innerHTML = amount;
        totalBox.hidden = isPackage;
        packagesBox.hidden = !isPackage;

        if (typeof window.bdRefreshVoucher === 'function') {
            window.bdRefreshVoucher(price, n, isPackage);
        }

        var pkgInvalid = false;
        if (isPackage) {
            if (!hasCompatiblePackage() || !effectivePkg) {
                packageError.textContent = 'No compatible active package available for ' + targetLabel + '.';
                pkgInvalid = true;
            } else if (pkgRemaining() < n) {
                packageError.textContent = 'Your package does not have enough remaining sessions.';
                pkgInvalid = true;
            } else {
                packageError.textContent = '';
            }
        } else {
            packageError.textContent = '';
        }

        addBtn.disabled = form.dataset.full === '1' || n >= max || n >= bookingLimit;
        if (max === 1) {
            addBtn.title = 'Only 1 spot left in this class';
        } else if (n >= bookingLimit) {
            addBtn.title = 'Maximum ' + bookingLimit + ' participants per booking';
        } else {
            addBtn.title = '';
        }

        var isVoucherInvalid = !isPackage && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid();
        continueBtn.disabled = checking || !agree.checked || form.dataset.full === '1' || isVoucherInvalid || pkgInvalid;
    }

    if (clearPkgBtn) {
        clearPkgBtn.addEventListener('click', function () {
            form.querySelectorAll('input[name="package_id"]').forEach(function (r) {
                r.checked = false;
            });
            refresh();
        });
    }

    function setOpen(card, open) {
        var btn = card.querySelector('[data-toggle-attendee]');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.getElementById(btn.getAttribute('aria-controls')).hidden = !open;
    }

    async function syncHoldQuantity(newQty) {
        var fd = new FormData();
        fd.append('action', 'update_count');
        fd.append('schedule_id', scheduleId);
        fd.append('qty', String(newQty));

        var res = await fetch('api-hold-slot.php', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin'
        });
        return await res.json();
    }

    addBtn.addEventListener('click', async function () {
        var nextQty = count() + 1;
        if (count() >= max || nextQty > bookingLimit || addBtn.disabled) return;

        addBtn.disabled = true;
        var origText = addBtn.textContent;
        addBtn.textContent = 'Reserving spot...';

        try {
            var result = await syncHoldQuantity(nextQty);
            if (result.status !== 'ok') {
                alert(result.message || 'No more spots available in this class.');
                max = count();
                addBtn.textContent = origText;
                refresh();
                return;
            }

            if (slotsBadge) {
                slotsBadge.textContent = result.remaining_others + ' / ' + totalSlots + ' Left';
            }

            list.querySelectorAll('[data-attendee]').forEach(function (c) {
                setOpen(c, false);
            });

            list.insertAdjacentHTML(
                'beforeend',
                tpl.innerHTML.split('__INDEX__').join(String(nextIndex++))
            );

            updateAttendeeTitles();
        } catch (err) {
            console.error(err);
            alert('Failed to reserve additional spot. Please try again.');
        } finally {
            addBtn.textContent = origText;
            refresh();
        }
    });

    list.addEventListener('click', async function (e) {
        var toggle = e.target.closest('[data-toggle-attendee]');
        if (toggle) {
            setOpen(toggle.closest('[data-attendee]'), toggle.getAttribute('aria-expanded') !== 'true');
            return;
        }
        var rm = e.target.closest('[data-remove-attendee]');
        if (rm) {
            rm.closest('[data-attendee]').remove();
            updateAttendeeTitles();
            refresh();

            try {
                var result = await syncHoldQuantity(count());
                if (result.status === 'ok' && slotsBadge) {
                    slotsBadge.textContent = result.remaining_others + ' / ' + totalSlots + ' Left';
                }
            } catch (err) {
                console.error('Failed to release hold:', err);
            }
        }
    });

    if (backBtn) {
        backBtn.addEventListener('click', async function (e) {
            e.preventDefault();
            var targetHref = backBtn.getAttribute('href');
            try {
                var fd = new FormData();
                fd.append('action', 'release');
                await fetch('api-hold-slot.php', {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    keepalive: true
                });
            } catch (err) {
                console.error(err);
            }
            window.location.href = targetHref;
        });
    }

    list.addEventListener('input', function (e) {
        var input = e.target;
        if (input.type === 'tel') {
            input.value = input.value.replace(/[^\d+]/g, '');
        }
        if (input.matches('[data-attendee-name]')) {
            updateAttendeeTitles();
        }
    });

    form.addEventListener('change', function (e) {
        if (e.target.matches('input[name="payment_method"], input[name="package_id"], #bd-agree')) {
            refresh();
        }
    });

    document.addEventListener('bd:voucher-changed', function () {
        refresh();
    });

    function setChecking(on) {
        checking = on;
        continueBtn.classList.toggle('is-loading', on);
        continueBtn.setAttribute('aria-busy', on ? 'true' : 'false');

        var spinner = continueBtn.querySelector('.bd-spinner');
        if (on) {
            if (!spinner) {
                spinner = document.createElement('span');
                spinner.className = 'bd-spinner';
                spinner.setAttribute('aria-hidden', 'true');
                continueBtn.insertBefore(spinner, continueBtn.firstChild);
            }
            if (continueLabel) continueLabel.textContent = 'Verifying slot & price...';
        } else {
            if (spinner) spinner.remove();
            if (continueLabel) continueLabel.textContent = continueText;
        }
        var isInvalidPackage = method() === 'package' && (!resolveEffectivePackageRadio() || pkgRemaining() < count());
        var isVoucherInvalid = method() !== 'package' && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid();
        continueBtn.disabled = on || !agree.checked || form.dataset.full === '1' || isInvalidPackage || isVoucherInvalid;
    }

    function showSlotModal(mode, text) {
        var isFull = mode === 'full';
        lastFocus = document.activeElement;
        slotTitle.textContent = isFull ? 'This slot is no longer available' : 'We could not verify your booking';
        slotText.textContent = text || (isFull ?
            'Oops, sorry! The last spot in your selected class has just been booked. Kindly choose another available class.' :
            'Please try again in a moment.');
        slotBack.hidden = !isFull;
        slotRefresh.textContent = isFull ? 'Refresh Availability' : 'Try Again';
        slotRefresh.dataset.mode = mode;
        slotModal.hidden = false;
        slotModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('bd-modal-open');
        slotRefresh.focus();
    }

    function hideSlotModal() {
        slotModal.hidden = true;
        slotModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('bd-modal-open');
        if (lastFocus && lastFocus.focus) lastFocus.focus();
    }

    slotRefresh.addEventListener('click', function () {
        if (slotRefresh.dataset.mode === 'full') {
            window.location.reload();
        } else {
            hideSlotModal();
        }
    });
    slotModal.addEventListener('click', function (e) {
        if (e.target === slotModal) hideSlotModal();
    });
    document.addEventListener('keydown', function (e) {
        if (slotModal.hidden) return;
        if (e.key === 'Escape') {
            hideSlotModal();
        } else if (e.key === 'Tab') {
            var items = [slotBack, slotRefresh].filter(function (el) {
                return !el.hidden;
            });
            var first = items[0],
                last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });

    window.addEventListener('pageshow', function (e) {
        if (e.persisted) setChecking(false);
    });

    function checkSlotAndContinue() {
        if (checking) return;

        if (method() === 'dropin' && typeof window.bdIsVoucherValid === 'function' && !window.bdIsVoucherValid()) {
            return;
        }

        setChecking(true);

        var fd = new FormData(form);
        var n = count();
        var finalTotal = (method() === 'dropin' && typeof window.bdGetFinalTotal === 'function') ?
            window.bdGetFinalTotal(price, n) :
            (price * n);

        if (method() === 'package') {
            var effPkg = resolveEffectivePackageRadio();
            if (effPkg && effPkg.value) {
                fd.set('package_id', effPkg.value);
                fd.set('user_package_id', effPkg.value);
            }
        }

        fd.append('attendee_count', String(n));
        fd.append('expected_total', String(finalTotal));

        var ctrl = window.AbortController ? new AbortController() : null;
        var timer = setTimeout(function () {
            if (ctrl) ctrl.abort();
        }, 15000);

        fetch(endpoint, {
                method: 'POST',
                body: fd,
                headers: {
                    'X-Requested-With': 'fetch',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin',
                signal: ctrl ? ctrl.signal : undefined
            })
            .then(function (res) {
                return res.json().catch(function () {
                    return {
                        status: 'error'
                    };
                });
            })
            .then(function (d) {
                clearTimeout(timer);
                d = d || {};

                if (d.status === 'available' && d.redirect) {
                    if (continueLabel) continueLabel.textContent = 'Slot & price verified! Redirecting...';
                    window.location.href = d.redirect;
                    return;
                }

                setChecking(false);

                if (d.status === 'full') {
                    showSlotModal('full', d.message);
                } else if (d.status === 'unauthenticated' && d.redirect) {
                    window.location.href = d.redirect + (d.redirect.indexOf('?') > -1 ? '&' : '?') +
                        'redirect=' + encodeURIComponent(location.pathname + location.search);
                } else if (d.status === 'invalid') {
                    form.submit();
                } else {
                    showSlotModal('error', d.message);
                }
            })
            .catch(function () {
                clearTimeout(timer);
                setChecking(false);
                showSlotModal('error', 'We could not reach the server. Please check your connection and try again.');
            });
    }

    form.addEventListener('submit', function (e) {
        if (method() === 'package' && (!resolveEffectivePackageRadio() || pkgRemaining() < count())) {
            e.preventDefault();
            packagesBox.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            return;
        }
        if (!form.checkValidity()) {
            e.preventDefault();
            list.querySelectorAll('[data-attendee]').forEach(function (c) {
                if (c.querySelector(':invalid')) setOpen(c, true);
            });
            form.reportValidity();
            return;
        }
        if (!agree.checked) {
            e.preventDefault();
            agree.focus();
            return;
        }

        e.preventDefault();
        if (!window.fetch || !window.FormData) {
            form.submit();
            return;
        }
        checkSlotAndContinue();
    });

    updateAttendeeTitles();
    refresh();
});