(function () {
    'use strict';

    var main = document.querySelector('.profile-page');

    function showAlert(type, message) {
        if (!main) return;

        var old = main.querySelector('.profile-alert');
        if (old) old.remove();

        var box = document.createElement('div');
        box.className = 'profile-alert profile-alert--' + type;
        box.setAttribute('role', type === 'error' ? 'alert' : 'status');

        var icon = document.createElement('i');
        icon.className = type === 'error' ? 'ph-fill ph-warning-circle' : 'ph-fill ph-check-circle';
        icon.setAttribute('aria-hidden', 'true');

        var text = document.createElement('span');
        text.textContent = message;

        box.appendChild(icon);
        box.appendChild(text);
        main.insertBefore(box, main.firstChild);
        box.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function guardSubmit(form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('.btn-save-sm');
            if (btn) {
                btn.disabled = true;
                btn.textContent = 'Saving…';
            }
        });
    }

    var avatarBtn = document.getElementById('btnEditAvatar');
    var avatarInput = document.getElementById('avatarInput');
    var avatarForm = document.getElementById('avatarUploadForm');
    var avatarWrap = document.querySelector('.profile-card__avatar-wrap');

    var ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    var MAX_BYTES = 2 * 1024 * 1024;
    function previewAvatar(file) {
        var holder = document.querySelector('.profile-card__avatar');
        if (!holder) return;

        var img = holder.querySelector('img');
        if (!img) {
            holder.textContent = '';
            holder.classList.remove('is-empty');
            img = document.createElement('img');
            img.alt = '';
            holder.appendChild(img);
        }
        img.src = URL.createObjectURL(file);
    }

    if (avatarBtn && avatarInput && avatarForm) {
        avatarBtn.addEventListener('click', function () {
            avatarInput.click();
        });

        avatarInput.addEventListener('change', function () {
            var file = avatarInput.files && avatarInput.files[0];
            if (!file) return;

            if (ALLOWED_TYPES.indexOf(file.type) === -1) {
                avatarInput.value = '';
                showAlert('error', 'Only JPG, PNG, or WEBP images are allowed.');
                return;
            }
            if (file.size > MAX_BYTES) {
                avatarInput.value = '';
                showAlert('error', 'Image size must be less than 2MB.');
                return;
            }

            previewAvatar(file);
            if (avatarWrap) avatarWrap.classList.add('is-uploading');
            avatarForm.submit();
        });

        window.addEventListener('pageshow', function (e) {
            if (e.persisted && avatarWrap) avatarWrap.classList.remove('is-uploading');
        });
    }

    function setPeekButton(btn, shown) {
        var icon = btn.querySelector('i');
        if (icon) icon.className = shown ? 'ph ph-eye-slash' : 'ph ph-eye';
        var label = shown ? 'Hide password' : 'Show password';
        btn.setAttribute('aria-pressed', shown ? 'true' : 'false');
        btn.setAttribute('aria-label', label);
        btn.setAttribute('title', label);
    }

    var accountEditors = [];

    document.querySelectorAll('.account-item').forEach(function (item) {
        var view = item.querySelector('.account-view');
        var wrap = item.querySelector('.account-form-wrap');
        var editBtn = item.querySelector('.js-edit-account');
        var cancelBtn = item.querySelector('.js-cancel-account');
        var form = wrap ? wrap.querySelector('form') : null;
        var input = wrap ? wrap.querySelector('input.inline-input') : null;
        if (!view || !wrap || !editBtn || !cancelBtn || !form || !input) return;

        var key = item.getAttribute('data-account-field');
        var original = input.value;
        var peekText = item.querySelector('[data-password-view]');
        var peekViewBtn = item.querySelector('.js-peek-view');
        var peekInputBtn = wrap.querySelector('.js-peek-input');
        var PEEK_MASK = '\u2022\u2022\u2022\u2022\u2022\u2022\u2022\u2022';
        var viewShown = false;

        var renderViewPeek = function () {
            if (!peekText) return;
            var secret = peekText.getAttribute('data-secret') || '';

            if (!viewShown) {
                peekText.textContent = peekText.getAttribute('data-mask') || PEEK_MASK;
                peekText.classList.remove('is-fallback');
            } else if (secret !== '') {
                peekText.textContent = secret;
                peekText.classList.remove('is-fallback');
            } else {
                peekText.textContent = peekText.getAttribute('data-fallback') || '';
                peekText.classList.add('is-fallback');
            }

            if (peekViewBtn) setPeekButton(peekViewBtn, viewShown);
        };

        var hideViewSecret = function () {
            viewShown = false;
            renderViewPeek();
        };

        var resetInputPeek = function () {
            if (peekInputBtn) {
                input.type = 'password';
                setPeekButton(peekInputBtn, false);
            }
        };

        if (peekText && peekViewBtn) {
            peekViewBtn.addEventListener('click', function () {
                viewShown = !viewShown;
                renderViewPeek();
            });
        }

        if (peekInputBtn) {
            peekInputBtn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                setPeekButton(peekInputBtn, show);
                input.focus();
            });
        }

        if (key === 'email') {
            input.setAttribute('autocomplete', 'email');
        } else if (key === 'phone_number') {
            input.setAttribute('autocomplete', 'tel');
            input.setAttribute('inputmode', 'tel');
            input.setAttribute('pattern', '\\+?[0-9 \\-]{8,20}');
            input.setAttribute('title', '8–20 digits. May start with + and contain spaces or dashes.');
        } else if (key === 'password') {
            input.setAttribute('autocomplete', 'new-password');
            input.setAttribute('minlength', '8');
            input.setAttribute('title', 'At least 8 characters.');
        }

        function open() {
            accountEditors.forEach(function (ed) {
                if (ed.item !== item) ed.close(true, true);
            });
            hideViewSecret();
            view.hidden = true;
            wrap.hidden = false;
            input.focus();
            if (key !== 'password') input.select();
        }

        function close(restore, silent) {
            if (wrap.hidden) return;
            wrap.hidden = true;
            view.hidden = false;
            resetInputPeek();
            if (restore) input.value = original;
            if (!silent) editBtn.focus();
        }

        editBtn.addEventListener('click', open);
        cancelBtn.addEventListener('click', function () { close(true); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                close(true);
            }
        });
        if (key === 'password') {
            form.addEventListener('submit', function (e) {
                if (input.value.length < 8) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    showAlert('error', 'Password must be at least 8 characters long.');
                    input.focus();
                }
            });
        }
        guardSubmit(form);

        accountEditors.push({ item: item, close: close });
    });

    var editPersonalBtn = document.getElementById('btnEditPersonal');
    var cancelPersonalBtn = document.getElementById('btnCancelPersonal');
    var personalView = document.getElementById('personalViewMode');
    var personalForm = document.getElementById('personalEditMode');

    if (editPersonalBtn && personalView && personalForm) {
        var firstField = personalForm.querySelector('input:not([type="hidden"]), select');

        function openPersonal() {
            personalView.hidden = true;
            editPersonalBtn.hidden = true;
            personalForm.hidden = false;
            if (firstField) firstField.focus();
        }

        function closePersonal() {
            personalForm.reset();
            personalForm.hidden = true;
            personalView.hidden = false;
            editPersonalBtn.hidden = false;
            editPersonalBtn.focus();
        }

        editPersonalBtn.addEventListener('click', openPersonal);
        if (cancelPersonalBtn) cancelPersonalBtn.addEventListener('click', closePersonal);

        personalForm.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                closePersonal();
            }
        });
        guardSubmit(personalForm);
    }
})();