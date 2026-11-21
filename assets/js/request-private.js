(function () {
    'use strict';

    var form = document.getElementById('rp-form');
    var successView = document.getElementById('rp-success-view');
    var editAgainBtn = document.getElementById('rp-edit-again');
    var tncModal = document.getElementById('tnc-modal');
    var openTnc = document.getElementById('bd-open-tnc');
    var acceptTnc = document.getElementById('tnc-accept');
    var agreeBox = document.getElementById('rp-agree');
    var submitBtn = document.getElementById('rp-submit');

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

    if (acceptTnc && tncModal) {
        acceptTnc.addEventListener('click', function () {
            closeTncModal();
            if (agreeBox) {
                agreeBox.checked = true;
                if (submitBtn) submitBtn.disabled = false;
            }
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

    var maxAttendees = parseInt(form.dataset.max, 10) || 8;
    var classSelect = document.getElementById('rp-class');
    var dateInput = document.getElementById('rp-date');
    var dayDisplay = document.getElementById('rp-day-display');
    var timeSelect = document.getElementById('rp-time');
    var coachSelect = document.getElementById('rp-coach');

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

    function attendeeCount() {
        return attendeeList ? attendeeList.querySelectorAll('[data-attendee]').length : 1;
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
        var opt = classSelect.options[classSelect.selectedIndex];
        var unitPrice = parseInt(opt.dataset.price, 10) || 0;
        var qty = attendeeCount();
        var total = unitPrice * qty;
        var readableDate = formatDateReadable(dateInput.value);
        var shortDate = formatDateShort(dateInput.value);
        var readableTime = formatTimeRange(timeSelect.value);
        var coachText = coachSelect.value ? 'Coach ' + coachSelect.value : 'Any Available Coach';
        var peopleText = qty + (qty === 1 ? ' Person' : ' People');

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
        sumLevel.textContent = opt.dataset.level;
        slotsBadge.innerHTML = peopleText + ' &bull; Private';

        rateLabel.innerHTML = 'Subtotal (' + qty + ' &times; Rp' + unitPrice.toLocaleString('en-US') + ')';
        subtotalEl.innerHTML = '<em>Rp</em>' + total.toLocaleString('en-US');
        totalEl.innerHTML = '<em>Rp</em>' + total.toLocaleString('en-US');

        if (addAttendeeBtn) {
            addAttendeeBtn.disabled = qty >= maxAttendees;
            addAttendeeBtn.title = qty >= maxAttendees ? 'Maximum ' + maxAttendees + ' participants per private request' : '';
        }

        submitBtn.disabled = !agreeBox.checked;
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

    [classSelect, dateInput, timeSelect, coachSelect, agreeBox].forEach(function (el) {
        if (el) el.addEventListener('change', updateSummary);
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();

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
        var peopleText = qty + (qty === 1 ? ' Person' : ' People');

        var names = [];
        cards.forEach(function (c, i) {
            var n = c.querySelector('[data-attendee-name]');
            names.push(n && n.value.trim() ? n.value.trim() : 'Attendee ' + (i + 1));
        });

        var leadPhoneInput = cards[0] ? cards[0].querySelector('input[type="tel"]') : null;
        var leadPhone = leadPhoneInput ? leadPhoneInput.value.trim() : '-';

        document.getElementById('rp-res-hero').style.backgroundImage = "url('" + opt.dataset.image + "')";
        document.getElementById('rp-res-title').textContent = opt.dataset.name;
        document.getElementById('rp-res-category').textContent = opt.dataset.category + ' Class';
        document.getElementById('rp-res-date').textContent = readableDate;
        document.getElementById('rp-res-time').textContent = readableTime;
        document.getElementById('rp-res-coach').textContent = coachText;
        document.getElementById('rp-res-level').textContent = opt.dataset.level;
        document.getElementById('rp-res-badge').innerHTML = peopleText + ' &bull; Private';

        document.getElementById('rp-res-details').innerHTML =
            '<strong>Submitted Details:</strong><br>' +
            '&bull; Class: ' + opt.dataset.name + ' (' + peopleText + ')<br>' +
            '&bull; Schedule: ' + readableDate + ', ' + readableTime + '<br>' +
            '&bull; Coach Preference: ' + coachText + '<br>' +
            '&bull; Participants: ' + names.join(', ') + '<br>' +
            '&bull; Lead Contact Phone: ' + leadPhone;

        form.hidden = true;
        if (successView) {
            successView.hidden = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });

    if (editAgainBtn) {
        editAgainBtn.addEventListener('click', function () {
            if (successView) successView.hidden = true;
            form.hidden = false;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    updateAttendeeTitles();
    updateSummary();
})();