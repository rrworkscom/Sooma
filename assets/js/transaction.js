(function () {
    'use strict';

    var overlay = document.getElementById('bookingModal');
    var dataEl  = document.getElementById('transactions-data');
    if (!overlay || !dataEl) return;

    var data = {};
    try {
        data = JSON.parse(dataEl.textContent || '{}');
    } catch (err) {
        console.warn('[transaction.js] Data transaksi tidak valid:', err);
    }

    var modal     = overlay.querySelector('.modal');
    var body      = overlay.querySelector('.modal-body');
    var closeBtn  = overlay.querySelector('.modal__close');
    var attendees = overlay.querySelector('[data-list="attendees"]');
    var openBtns  = document.querySelectorAll('.btn-show-details');

    var USER_ICON = 'assets/icons/modal-user.svg';
    var lastFocus = null;
    var pressedOnBackdrop = false;

    function fill(item) {
        overlay.querySelectorAll('[data-field]').forEach(function (el) {
            var value = item[el.getAttribute('data-field')];
            if (value === undefined || value === null) value = '';

            if (el.tagName === 'IMG') {
                el.src = value;
                el.alt = item.class_name ? item.class_name + ' class' : '';
            } else {
                el.textContent = value;
            }
        });

        attendees.textContent = '';
        (item.attendees || []).forEach(function (person) {
            var row  = document.createElement('div');
            row.className = 'attendee-row';

            var icon = document.createElement('img');
            icon.src = USER_ICON;
            icon.alt = '';
            icon.width = 14;
            icon.height = 14;

            var name = document.createElement('p');
            name.textContent = person.name + ' ';

            if (person.is_you) {
                var you = document.createElement('span');
                you.className = 'attendee-row__you';
                you.textContent = '(You)';
                name.appendChild(you);
            }

            row.appendChild(icon);
            row.appendChild(name);
            attendees.appendChild(row);
        });
    }

    function openModal(id, trigger) {
        var item = data[id];
        if (!item) {
            console.warn('[transaction.js] Transaksi tidak ditemukan:', id);
            return;
        }

        fill(item);
        lastFocus = trigger || document.activeElement;

        var scrollbar = window.innerWidth - document.documentElement.clientWidth;
        if (scrollbar > 0) document.body.style.paddingRight = scrollbar + 'px';
        document.body.classList.add('modal-open');

        overlay.classList.add('is-open');
        overlay.setAttribute('aria-hidden', 'false');
        body.scrollTop = 0;               
        closeBtn.focus({ preventScroll: true });
    }

    function closeModal() {
        if (!overlay.classList.contains('is-open')) return;

        overlay.classList.remove('is-open');
        overlay.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        document.body.style.paddingRight = '';

        if (lastFocus && typeof lastFocus.focus === 'function') {
            lastFocus.focus({ preventScroll: true });
        }
    }

    openBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-id'), btn);
        });
    });

    closeBtn.addEventListener('click', closeModal);

    overlay.addEventListener('mousedown', function (e) {
        pressedOnBackdrop = (e.target === overlay);
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay && pressedOnBackdrop) closeModal();
        pressedOnBackdrop = false;
    });

    document.addEventListener('keydown', function (e) {
        if (!overlay.classList.contains('is-open')) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeModal();
            return;
        }

        if (e.key === 'Tab') {
            var focusable = modal.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );
            if (!focusable.length) return;

            var first = focusable[0];
            var last  = focusable[focusable.length - 1];

            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        }
    });
})();