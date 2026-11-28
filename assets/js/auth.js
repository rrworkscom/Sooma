(function () {
    'use strict';

    const layout = document.querySelector('.auth-layout');
    if (!layout) return;

    layout.addEventListener('click', function (event) {
        const btn = event.target.closest('.password-toggle-btn');
        if (!btn || !layout.contains(btn)) return;

        const wrap  = btn.closest('.password-input-wrap');
        const input = wrap ? wrap.querySelector('input') : null;
        if (!input) return;

        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';

        const icon = btn.querySelector('i');
        if (icon) {
            icon.classList.remove(show ? 'ph-eye' : 'ph-eye-slash');
            icon.classList.add(show ? 'ph-eye-slash' : 'ph-eye');
        }

        btn.setAttribute('aria-pressed', show ? 'true' : 'false');
        btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
        input.focus();
    });

    const pollBox = document.getElementById('reset-poll-box');
    if (pollBox) {
        const pollUrl    = pollBox.getAttribute('data-poll-url') || 'auth/check-reset-status.php';
        const statusText = document.getElementById('poll-status-text');
        let isRedirecting = false;

        const checkVerificationStatus = async function () {
            if (isRedirecting) return;

            try {
                const response = await fetch(pollUrl, {
                    method: 'GET',
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!response.ok) return;

                const data = await response.json();
                if (data && data.verified && data.redirect_url) {
                    isRedirecting = true;
                    if (statusText) {
                        statusText.textContent = 'Email verified! Redirecting to Set New Password...';
                    }
                    window.location.href = data.redirect_url;
                }
            } catch (err) {
            }
        };

        const pollInterval = window.setInterval(checkVerificationStatus, 2000);

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                checkVerificationStatus();
            }
        });

        window.addEventListener('beforeunload', function () {
            window.clearInterval(pollInterval);
        });
    }

    const resetForm = document.getElementById('reset-password-form');
    if (resetForm) {
        const newPassInput     = document.getElementById('new-password');
        const confirmPassInput = document.getElementById('confirm-password');
        const clientErrorBox   = document.getElementById('client-reset-error');

        const showResetError = function (message) {
            if (!clientErrorBox) return;
            clientErrorBox.textContent = message;
            clientErrorBox.classList.remove('is-hidden');
        };

        const hideResetError = function () {
            if (!clientErrorBox) return;
            clientErrorBox.textContent = '';
            clientErrorBox.classList.add('is-hidden');
        };

        confirmPassInput.addEventListener('input', hideResetError);
        newPassInput.addEventListener('input', hideResetError);

        resetForm.addEventListener('submit', function (event) {
            if (newPassInput.value.length < 8) {
                event.preventDefault();
                showResetError('Password must be at least 8 characters long.');
                newPassInput.focus();
                return;
            }

            if (newPassInput.value !== confirmPassInput.value) {
                event.preventDefault();
                showResetError('New password and confirmation password do not match.');
                confirmPassInput.focus();
            }
        });
    }

    const registerForm = document.getElementById('register-form');
    if (registerForm) {
        const validateUrl   = registerForm.getAttribute('data-validate-url') || 'auth/validate-signup.php';
        const fullNameInput = document.getElementById('full-name');
        const emailInput    = document.getElementById('register-email');
        const phoneInput    = document.getElementById('phone');
        const passwordInput = document.getElementById('register-password');
        const confirmInput  = document.getElementById('register-password2');
        const termsInput    = document.getElementById('register-terms');
        const submitBtn     = document.getElementById('register-submit-btn');

        const state = {
            fullName: false,
            email: false,
            phone: false,
            password: false,
            confirm: false
        };

        const setFieldFeedback = function (inputEl, feedbackId, status, message) {
            const feedbackEl = document.getElementById(feedbackId);
            const fieldWrap  = inputEl ? inputEl.closest('.field') : null;

            if (fieldWrap) {
                fieldWrap.classList.remove('has-error', 'has-valid');
                if (status === 'error') fieldWrap.classList.add('has-error');
                if (status === 'valid') fieldWrap.classList.add('has-valid');
            }

            if (!feedbackEl) return;

            feedbackEl.classList.remove('is-hidden', 'is-error', 'is-valid', 'is-checking');

            if (!message) {
                feedbackEl.textContent = '';
                feedbackEl.classList.add('is-hidden');
                return;
            }

            feedbackEl.textContent = message;
            if (status === 'error') feedbackEl.classList.add('is-error');
            if (status === 'valid') feedbackEl.classList.add('is-valid');
            if (status === 'checking') feedbackEl.classList.add('is-checking');
        };

        const validateFullName = function () {
            const val = fullNameInput.value.trim();
            if (!val) {
                state.fullName = false;
                setFieldFeedback(fullNameInput, 'error-full-name', 'error', 'Full name is required.');
                return false;
            }
            state.fullName = true;
            setFieldFeedback(fullNameInput, 'error-full-name', '', '');
            return true;
        };

        let emailTimer = null;
        let emailAbort = null;

        const validateEmail = async function () {
            const val = emailInput.value.trim().toLowerCase();
            const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;

            if (!val) {
                state.email = false;
                setFieldFeedback(emailInput, 'error-register-email', 'error', 'Email is required.');
                return false;
            }

            if (!emailRegex.test(val)) {
                state.email = false;
                setFieldFeedback(emailInput, 'error-register-email', 'error', 'Invalid email format (e.g., name@gmail.com).');
                return false;
            }

            if (emailAbort) {
                emailAbort.abort();
            }
            emailAbort = new AbortController();

            setFieldFeedback(emailInput, 'error-register-email', 'checking', 'Verifying email existence...');

            try {
                const res = await fetch(validateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ field: 'email', value: val }),
                    signal: emailAbort.signal
                });
                const data = await res.json();

                if (data.valid) {
                    state.email = true;
                    setFieldFeedback(emailInput, 'error-register-email', 'valid', data.message || 'Email is valid and available.');
                    return true;
                } else {
                    state.email = false;
                    setFieldFeedback(emailInput, 'error-register-email', 'error', data.message || 'Invalid or non-existent email.');
                    return false;
                }
            } catch (err) {
                if (err.name === 'AbortError') return false;
                state.email = true;
                setFieldFeedback(emailInput, 'error-register-email', '', '');
                return true;
            }
        };

        let phoneTimer = null;
        let phoneAbort = null;

        const validatePhone = async function () {
            const raw = phoneInput.value.trim();

            if (!raw) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Phone number is required.');
                return false;
            }

            if (/[a-zA-Z]/.test(raw)) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Phone number cannot contain letters. Use digits only.');
                return false;
            }

            if (!/^\+?[0-9\s\-\(\)]+$/.test(raw)) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Invalid characters in phone number. Use digits only (e.g., 081234567890).');
                return false;
            }

            let normalized = raw.replace(/[\s\-\(\)]+/g, '');
            if (normalized.startsWith('+62')) {
                normalized = '0' + normalized.slice(3);
            } else if (normalized.startsWith('62') && normalized.length >= 11) {
                normalized = '0' + normalized.slice(2);
            }

            const digitsOnly = normalized.replace(/^\+/, '');

            if (digitsOnly.length < 10) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Phone number is too short (minimum 10 digits required).');
                return false;
            }

            if (digitsOnly.length > 15) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Phone number is too long (maximum 15 digits allowed).');
                return false;
            }

            if (!/^(08[0-9]{8,12}|\+[1-9][0-9]{9,14})$/.test(normalized)) {
                state.phone = false;
                setFieldFeedback(phoneInput, 'error-phone', 'error', 'Invalid phone number format. Start with 08... or +62...');
                return false;
            }

            if (phoneAbort) {
                phoneAbort.abort();
            }
            phoneAbort = new AbortController();

            try {
                const res = await fetch(validateUrl, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ field: 'phone', value: raw }),
                    signal: phoneAbort.signal
                });
                const data = await res.json();

                if (data.valid) {
                    state.phone = true;
                    setFieldFeedback(phoneInput, 'error-phone', 'valid', data.message || 'Phone number is valid.');
                    return true;
                } else {
                    state.phone = false;
                    setFieldFeedback(phoneInput, 'error-phone', 'error', data.message || 'Invalid phone number.');
                    return false;
                }
            } catch (err) {
                if (err.name === 'AbortError') return false;
                state.phone = true;
                setFieldFeedback(phoneInput, 'error-phone', '', '');
                return true;
            }
        };

        const validatePassword = function () {
            const val = passwordInput.value;

            if (!val) {
                state.password = false;
                setFieldFeedback(passwordInput, 'error-register-password', 'error', 'Password is required (minimum 8 characters).');
                return false;
            }

            if (val.length < 8) {
                state.password = false;
                setFieldFeedback(
                    passwordInput,
                    'error-register-password',
                    'error',
                    'Password must be at least 8 characters long (' + val.length + '/8).'
                );
                return false;
            }

            state.password = true;
            setFieldFeedback(passwordInput, 'error-register-password', 'valid', 'Password meets the 8-character minimum.');
            return true;
        };

        const validateConfirmPassword = function () {
            const pass    = passwordInput.value;
            const confirm = confirmInput.value;

            if (!confirm) {
                state.confirm = false;
                setFieldFeedback(confirmInput, 'error-register-password2', 'error', 'Please re-enter your password.');
                return false;
            }

            if (pass !== confirm) {
                state.confirm = false;
                setFieldFeedback(confirmInput, 'error-register-password2', 'error', 'Passwords do not match.');
                return false;
            }

            state.confirm = true;
            setFieldFeedback(confirmInput, 'error-register-password2', 'valid', 'Passwords match.');
            return true;
        };

        const validateTerms = function () {
            const termsFeedback = document.getElementById('error-terms');
            if (!termsInput.checked) {
                if (termsFeedback) {
                    termsFeedback.textContent = 'Please agree to the Terms & Conditions and Privacy Policy.';
                    termsFeedback.classList.remove('is-hidden');
                    termsFeedback.classList.add('is-error');
                }
                return false;
            }
            if (termsFeedback) {
                termsFeedback.textContent = '';
                termsFeedback.classList.add('is-hidden');
                termsFeedback.classList.remove('is-error');
            }
            return true;
        };

        fullNameInput.addEventListener('blur', validateFullName);
        fullNameInput.addEventListener('input', function () {
            if (fullNameInput.value.trim()) validateFullName();
        });

        emailInput.addEventListener('input', function () {
            window.clearTimeout(emailTimer);
            emailTimer = window.setTimeout(function () {
                validateEmail();
            }, 450);
        });
        emailInput.addEventListener('blur', function () {
            window.clearTimeout(emailTimer);
            validateEmail();
        });

        phoneInput.addEventListener('input', function () {
            window.clearTimeout(phoneTimer);
            phoneTimer = window.setTimeout(function () {
                validatePhone();
            }, 350);
        });
        phoneInput.addEventListener('blur', function () {
            window.clearTimeout(phoneTimer);
            validatePhone();
        });

        passwordInput.addEventListener('input', function () {
            validatePassword();
            if (confirmInput.value.length > 0) {
                validateConfirmPassword();
            }
        });

        confirmInput.addEventListener('input', validateConfirmPassword);
        termsInput.addEventListener('change', validateTerms);

        registerForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const nameOk    = validateFullName();
            const passOk    = validatePassword();
            const confirmOk = validateConfirmPassword();
            const termsOk   = validateTerms();
            const [emailOk, phoneOk] = await Promise.all([
                validateEmail(),
                validatePhone()
            ]);

            if (!nameOk) {
                fullNameInput.focus();
                return;
            }
            if (!emailOk) {
                emailInput.focus();
                return;
            }
            if (!phoneOk) {
                phoneInput.focus();
                return;
            }
            if (!passOk) {
                passwordInput.focus();
                return;
            }
            if (!confirmOk) {
                confirmInput.focus();
                return;
            }
            if (!termsOk) {
                termsInput.focus();
                return;
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Creating account...';
            }
            registerForm.submit();
        });
    }

    const loadingForms = layout.querySelectorAll('form[data-submit-loading]');
    loadingForms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (event.defaultPrevented) return;

            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = form.getAttribute('data-submit-loading') || 'Processing...';
            }
        });
    });
})();