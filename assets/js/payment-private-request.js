(function () {
  'use strict';

  var locked = false;

  function lockHistory() {
    if (!window.history || !window.history.pushState) return;
    window.history.pushState({ paymentLocked: true }, '', window.location.href);
    locked = true;
  }

  lockHistory();
  ['pointerdown', 'keydown', 'touchstart'].forEach(function (evt) {
    window.addEventListener(evt, function once() {
      if (!locked) lockHistory();
    }, { once: true, passive: true });
  });

  window.addEventListener('popstate', function () {
    window.history.pushState({ paymentLocked: true }, '', window.location.href);
  });

  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      window.location.reload();
    }
  });

  var copyBtn = document.getElementById('pay-copy-account');
  var accountEl = document.getElementById('pay-bank-account');
  var copyLabel = document.getElementById('pay-copy-label');

  function copyAccountNumber() {
    if (!accountEl) return;
    var acct = accountEl.textContent.trim();

    function showCopied() {
      if (copyBtn) copyBtn.classList.add('is-copied');
      if (copyLabel) copyLabel.textContent = 'Copied!';
      setTimeout(function () {
        if (copyBtn) copyBtn.classList.remove('is-copied');
        if (copyLabel) copyLabel.textContent = 'Copy';
      }, 1800);
    }

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(acct).then(showCopied).catch(fallbackCopy);
    } else {
      fallbackCopy();
    }

    function fallbackCopy() {
      var ta = document.createElement('textarea');
      ta.value = acct;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        showCopied();
      } catch (e) {}
      document.body.removeChild(ta);
    }
  }

  if (copyBtn) copyBtn.addEventListener('click', copyAccountNumber);
  if (accountEl) accountEl.addEventListener('click', copyAccountNumber);

  var root = document.getElementById('pay-root');
  if (!root) return;

  var STATUS = {
    waiting:   'Waiting Payment',
    verifying: 'Waiting Verification',
    expired:   'Payment Expired',
    confirmed: 'Booking Confirmed'
  };
  var MAX_BYTES = parseInt(root.dataset.max, 10) || 5 * 1024 * 1024;
  var ALLOWED = ['image/jpeg', 'image/png', 'image/webp'];

  var status = root.dataset.status || 'waiting';
  var isPackageMode = root.dataset.mode === 'package';
  var badge = document.getElementById('pay-badge');
  var badgeText = document.getElementById('pay-badge-text');
  var countdown = document.getElementById('pay-countdown');
  var doneLink = document.getElementById('pay-done-link');
  var message = document.getElementById('pay-message');

  var form = document.getElementById('pay-upload-form');
  var fileInput = document.getElementById('pay-receipt');
  var uploadBtn = document.getElementById('pay-upload-btn');
  var preview = document.getElementById('pay-preview');
  var thumb = document.getElementById('pay-thumb');
  var fileName = document.getElementById('pay-file-name');
  var fileSize = document.getElementById('pay-file-size');
  var removeBtn = document.getElementById('pay-remove');
  var submitBtn = document.getElementById('pay-submit');

  var packageBox = isPackageMode ? (document.querySelector('.pay-package-box') || root) : null;
  var packageForm = document.getElementById('pay-package-form');
  var clearPkgBtn = document.getElementById('pay-pkg-clear');
  var packageRadios = packageForm
    ? Array.prototype.slice.call(packageForm.querySelectorAll('input[name="user_package_id"]'))
    : [];
  var packageSubmitBtn = packageForm
    ? packageForm.querySelector('button[type="submit"]')
    : null;

  var objectUrl = null;
  var timer = null;

  function pad(n) { return String(n).padStart(2, '0'); }
  function fmtTime(s) { return pad(Math.floor(s / 60)) + ':' + pad(s % 60); }
  function fmtSize(b) {
    return b >= 1048576
      ? (b / 1048576).toFixed(1) + ' MB'
      : Math.max(1, Math.round(b / 1024)) + ' KB';
  }

  function say(text, type) {
    if (!message) return;
    message.textContent = text || '';
    message.className = 'pay-message' + (type ? ' is-' + type : '');
  }

  function setButtonLabel(btn, text) {
    if (!btn) return;
    var span = btn.querySelector('span');
    if (span) {
      span.textContent = text;
    } else {
      btn.textContent = text;
    }
  }

  function getEnabledPackageRadios() {
    return packageRadios.filter(function (r) {
      return !r.disabled;
    });
  }

  function getEffectivePackageRadio() {
    var enabled = getEnabledPackageRadios();
    if (!enabled.length) return null;

    var checked = enabled.find(function (r) { return r.checked; });
    if (checked) return checked;

    return enabled.find(function (r) { return r.dataset.earliest === '1'; }) || enabled[0];
  }

  function syncPackageHighlight() {
    if (!packageForm) return;
    var enabled = getEnabledPackageRadios();
    var checked = enabled.find(function (r) { return r.checked; });
    var earliest = enabled.find(function (r) { return r.dataset.earliest === '1'; }) || enabled[0];
    var isSingle = enabled.length <= 1;

    packageRadios.forEach(function (r) {
      var wrap = r.closest('.pay-pkg-option');
      if (wrap) {
        wrap.classList.toggle('is-auto-default', !checked && r === earliest);
      }
    });

    if (clearPkgBtn) {
      clearPkgBtn.hidden = isSingle || !checked;
    }

    if (packageSubmitBtn && status === 'waiting') {
      packageSubmitBtn.disabled = enabled.length === 0;
    }
  }

  function setPackageControlsDisabled(disabled) {
    if (!packageBox) return;
    packageBox.querySelectorAll('button, select, input').forEach(function (el) {
      if (el === fileInput || el === submitBtn || el === removeBtn || el.id === 'pay-copy-account') return;
      if (disabled) {
        el.disabled = true;
      } else if (el.type === 'radio') {
        var wrap = el.closest('.pay-pkg-option');
        el.disabled = wrap ? wrap.classList.contains('is-disabled') : false;
      } else {
        el.disabled = false;
      }
    });
    if (!disabled) {
      syncPackageHighlight();
    }
  }

  function lockUpload() {
    if (fileInput) fileInput.disabled = true;
    if (uploadBtn) {
      uploadBtn.classList.add('is-disabled');
      uploadBtn.setAttribute('aria-disabled', 'true');
      uploadBtn.tabIndex = -1;
    }
    if (submitBtn) submitBtn.hidden = true;
    if (removeBtn) removeBtn.hidden = true;
    setPackageControlsDisabled(true);
  }

  function enableFinishLink(nextStatus) {
    if (!doneLink) return;
    doneLink.hidden = false;
    doneLink.classList.remove('is-disabled');
    doneLink.setAttribute('aria-disabled', 'false');
    doneLink.removeAttribute('tabindex');
    doneLink.style.pointerEvents = 'auto';
    if (nextStatus === 'confirmed') {
      doneLink.setAttribute('href', '../customer/profile-transaction.php?tab=history');
    } else {
      doneLink.setAttribute('href', '../customer/profile-transaction.php?tab=pending');
    }
  }

  function setStatus(next) {
    status = next;
    root.dataset.status = next;
    badge.className = 'pay-badge pay-badge--' + next;
    badgeText.textContent = STATUS[next] || '';

    if (next !== 'waiting') lockUpload();
    if (next === 'expired') {
      stopTimer();
      countdown.textContent = '00:00';
      countdown.classList.remove('is-warning');
    }
    if (next === 'verifying' || next === 'confirmed') {
      stopTimer();
      countdown.textContent = '--:--';
      countdown.classList.remove('is-warning');
      if (next === 'confirmed' && packageSubmitBtn) {
        setButtonLabel(packageSubmitBtn, 'Processed');
      }
      enableFinishLink(next);
    }
  }

  function startTimer() {
    var endAt = Date.now() + (parseInt(root.dataset.remaining, 10) || 0) * 1000;
    function tick() {
      var s = Math.max(0, Math.ceil((endAt - Date.now()) / 1000));
      countdown.textContent = fmtTime(s);
      countdown.classList.toggle('is-warning', s > 0 && s <= 300);
      if (s <= 0) {
        setStatus('expired');
        say('Payment time has expired. Please submit a new private request.', 'error');
      }
    }
    tick();
    if (status === 'waiting') timer = setInterval(tick, 500);
  }

  function stopTimer() {
    if (timer) {
      clearInterval(timer);
      timer = null;
    }
  }

  function clearPreview() {
    if (objectUrl) {
      URL.revokeObjectURL(objectUrl);
      objectUrl = null;
    }
    if (fileInput) fileInput.value = '';
    if (preview) preview.hidden = true;
    if (submitBtn) submitBtn.hidden = true;
    if (thumb) thumb.innerHTML = '<i class="ph ph-file"></i>';
  }

  function showPreview(file) {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    thumb.innerHTML = '';
    if (file.type.indexOf('image/') === 0) {
      objectUrl = URL.createObjectURL(file);
      var img = document.createElement('img');
      img.src = objectUrl;
      img.alt = '';
      thumb.appendChild(img);
    } else {
      thumb.innerHTML = '<i class="ph ph-file"></i>';
    }
    fileName.textContent = file.name;
    fileSize.textContent = fmtSize(file.size);
    preview.hidden = false;
    removeBtn.hidden = false;
    submitBtn.hidden = false;
  }

  function onFileChosen() {
    var file = fileInput.files && fileInput.files[0];
    if (!file) {
      clearPreview();
      return;
    }
    if (ALLOWED.indexOf(file.type) === -1) {
      clearPreview();
      say('Only JPG, PNG, or WEBP images are allowed.', 'error');
      return;
    }
    if (file.size > MAX_BYTES) {
      clearPreview();
      say('File is too large. Maximum size is ' + fmtSize(MAX_BYTES) + '.', 'error');
      return;
    }
    say('', '');
    showPreview(file);
  }

  function onSubmit(e) {
    if (status !== 'waiting') {
      e.preventDefault();
      return;
    }
    if (!fileInput.files || !fileInput.files[0]) {
      e.preventDefault();
      say('Please choose a payment receipt first.', 'error');
      return;
    }
    if (!window.fetch || !window.FormData) return;
    e.preventDefault();

    submitBtn.disabled = true;
    setButtonLabel(submitBtn, 'Uploading...');

    var targetUrl = form.getAttribute('action') || window.location.href;

    fetch(targetUrl, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; })
          .then(function (data) { data.httpOk = res.ok; return data; });
      })
      .then(function (data) {
        if (data.ok && data.httpOk) {
          setStatus('verifying');
          fileSize.textContent = 'Submitted';
          say(data.message || 'Receipt uploaded.', 'success');
        } else {
          if (data.status === 'expired') setStatus('expired');
          say(data.message || 'Upload failed. Please try again.', 'error');
          submitBtn.disabled = false;
          setButtonLabel(submitBtn, 'Submit Receipt');
        }
      })
      .catch(function () {
        say('Network error. Please check your connection and try again.', 'error');
        submitBtn.disabled = false;
        setButtonLabel(submitBtn, 'Submit Receipt');
      });
  }

  function handlePackagePayment(e) {
    if (status !== 'waiting') {
      if (e) e.preventDefault();
      return;
    }

    var effectiveRadio = getEffectivePackageRadio();
    if (!effectiveRadio) {
      if (e) e.preventDefault();
      say('No compatible active package with enough remaining sessions is available.', 'error');
      return;
    }

    var endpoint = (packageForm && packageForm.getAttribute('action')) || window.location.href;
    if (!window.fetch || !window.FormData) {
      return;
    }

    if (e) e.preventDefault();

    var fd = new FormData(packageForm);
    fd.set('action', 'confirm_package');
    fd.set('user_package_id', effectiveRadio.value);
    fd.set('package_id', effectiveRadio.value);

    var origLabel = packageSubmitBtn
      ? (packageSubmitBtn.querySelector('span') ? packageSubmitBtn.querySelector('span').textContent : packageSubmitBtn.textContent)
      : 'Confirm Booking';

    setPackageControlsDisabled(true);
    setButtonLabel(packageSubmitBtn, 'Processing...');

    fetch(endpoint, {
      method: 'POST',
      body: fd,
      headers: { 'X-Requested-With': 'fetch', 'Accept': 'application/json' },
      credentials: 'same-origin'
    })
      .then(function (res) {
        return res.json().catch(function () { return { ok: false, message: 'Unexpected server response.' }; })
          .then(function (data) { data.httpOk = res.ok; return data; });
      })
      .then(function (data) {
        if ((data.ok || data.success) && data.httpOk) {
          var nextSt = data.status === 'verifying' ? 'verifying' : 'confirmed';
          setStatus(nextSt);
          say(data.message || 'Package payment applied.', 'success');
        } else {
          if (data.status === 'expired') {
            setStatus('expired');
            setButtonLabel(packageSubmitBtn, origLabel);
          } else {
            setPackageControlsDisabled(false);
            setButtonLabel(packageSubmitBtn, origLabel);
          }
          say(data.message || 'Unable to pay using this package.', 'error');
        }
      })
      .catch(function () {
        setPackageControlsDisabled(false);
        setButtonLabel(packageSubmitBtn, origLabel);
        say('Network error. Please check your connection and try again.', 'error');
      });
  }

  if (doneLink) {
    doneLink.addEventListener('click', function (e) {
      if (doneLink.classList.contains('is-disabled') || doneLink.getAttribute('aria-disabled') === 'true') {
        e.preventDefault();
      }
    });
  }

  if (packageForm) {
    packageRadios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        say('', '');
        syncPackageHighlight();
      });
    });

    if (clearPkgBtn) {
      clearPkgBtn.addEventListener('click', function () {
        packageRadios.forEach(function (r) {
          r.checked = false;
        });
        say('', '');
        syncPackageHighlight();
      });
    }

    syncPackageHighlight();

    if (status === 'waiting') {
      packageForm.addEventListener('submit', handlePackagePayment);
    }
  }

  if (form && status === 'waiting') {
    fileInput.addEventListener('change', onFileChosen);
    removeBtn.addEventListener('click', function () {
      clearPreview();
      say('', '');
    });
    form.addEventListener('submit', onSubmit);
    uploadBtn.addEventListener('keydown', function (e) {
      if ((e.key === 'Enter' || e.key === ' ') && status === 'waiting') {
        e.preventDefault();
        fileInput.click();
      }
    });
  }

  if (status === 'waiting') {
    startTimer();
  } else {
    setStatus(status);
    if (status === 'expired') countdown.textContent = '00:00';
  }
})();