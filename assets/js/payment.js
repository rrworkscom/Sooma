(function () {
  'use strict';

  var root = document.getElementById('pay-root');
  if (!root) return;

  var STATUS = {
    waiting: 'Waiting Payment',
    verifying: 'Waiting Verification',
    expired: 'Payment Expired',
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
  var packageForm = packageBox ? (packageBox.tagName === 'FORM' ? packageBox : packageBox.querySelector('form')) : null;
  var packageSelect = packageBox ? packageBox.querySelector('select[name*="package"], select') : null;
  var packageRadios = packageBox ? Array.prototype.slice.call(packageBox.querySelectorAll('input[type="radio"]')) : [];
  var packageCards = packageBox ? Array.prototype.slice.call(packageBox.querySelectorAll('[data-package-id]')) : [];
  var packageSubmitBtn = packageBox
    ? (packageBox.querySelector('[data-package-submit], button[type="submit"], .js-pay-package, #pay-package-btn') || packageBox.querySelector('button[type="button"], button'))
    : null;

  var selectedCardPackageId = '';
  var objectUrl = null;
  var timer = null;

  function pad(n) { return String(n).padStart(2, '0'); }
  function fmtTime(s) { return pad(Math.floor(s / 60)) + ':' + pad(s % 60); }
  function fmtSize(b) { return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

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

  function setPackageControlsDisabled(disabled) {
    if (!packageBox) return;
    packageBox.querySelectorAll('button, select, input').forEach(function (el) {
      if (el === fileInput || el === submitBtn || el === removeBtn || el.id === 'pay-copy-account') return;
      el.disabled = disabled;
    });
    packageCards.forEach(function (card) {
      card.setAttribute('aria-disabled', disabled ? 'true' : 'false');
    });
  }

  function getSelectedPackageValue() {
    if (packageSelect) return packageSelect.value;
    var checked = packageRadios.find(function (r) { return r.checked; });
    if (checked) return checked.value;
    return selectedCardPackageId;
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
        say('Payment time has expired. Please create a new booking.', 'error');
      }
    }
    tick();
    if (status === 'waiting') timer = setInterval(tick, 500);
  }
  function stopTimer() { if (timer) { clearInterval(timer); timer = null; } }

  function clearPreview() {
    if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; }
    if (fileInput) fileInput.value = '';
    if (preview) preview.hidden = true;
    if (submitBtn) submitBtn.hidden = true;
    if (thumb) thumb.innerHTML = '<i class="ph ph-file"></i>';
  }

  function showPreview(file) {
    if (objectUrl) URL.revokeObjectURL(objectUrl);
    thumb.innerHTML = '';
    objectUrl = URL.createObjectURL(file);
    var img = document.createElement('img');
    img.src = objectUrl;
    img.alt = '';
    thumb.appendChild(img);
    fileName.textContent = file.name;
    fileSize.textContent = fmtSize(file.size);
    preview.hidden = false;
    removeBtn.hidden = false;
    submitBtn.hidden = false;
  }

  function onFileChosen() {
    var file = fileInput.files && fileInput.files[0];
    if (!file) { clearPreview(); return; }
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
    if (status !== 'waiting') { e.preventDefault(); return; }
    if (!fileInput.files || !fileInput.files[0]) {
      e.preventDefault();
      say('Please choose a payment receipt first.', 'error');
      return;
    }
    if (!window.fetch || !window.FormData) return;
    e.preventDefault();

    submitBtn.disabled = true;
    submitBtn.querySelector('span').textContent = 'Uploading...';

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
          submitBtn.querySelector('span').textContent = 'Submit Receipt';
        }
      })
      .catch(function () {
        say('Network error. Please check your connection and try again.', 'error');
        submitBtn.disabled = false;
        submitBtn.querySelector('span').textContent = 'Submit Receipt';
      });
  }

  function handlePackagePayment(e) {
    if (status !== 'waiting') {
      if (e) e.preventDefault();
      return;
    }

    var pkgVal = getSelectedPackageValue();
    if ((packageSelect || packageRadios.length > 0 || packageCards.length > 0) && !pkgVal) {
      if (e) e.preventDefault();
      say('Please select a package first.', 'error');
      return;
    }

    var endpoint =
      (packageForm && packageForm.getAttribute('action')) ||
      (packageBox && packageBox.dataset.endpoint) ||
      (packageSubmitBtn && packageSubmitBtn.dataset.endpoint) ||
      (form && form.getAttribute('action')) ||
      '';

    if (!endpoint || !window.fetch || !window.FormData) {
      return;
    }

    if (e) e.preventDefault();

    var fd = packageForm ? new FormData(packageForm) : new FormData();
    fd.set('payment_method', 'package');
    if (pkgVal) {
      fd.set('package_id', pkgVal);
    }
    if (root.dataset.bookingId && !fd.has('booking_id')) {
      fd.set('booking_id', root.dataset.bookingId);
    }

    var spanEl = packageSubmitBtn ? packageSubmitBtn.querySelector('span') : null;
    var origLabel = spanEl ? spanEl.textContent : (packageSubmitBtn ? packageSubmitBtn.textContent : 'Confirm Booking');

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

  if (packageBox && status === 'waiting') {
    setPackageControlsDisabled(false);

    if (packageSelect) {
      packageSelect.addEventListener('change', function () {
        say('', '');
      });
    }
    packageRadios.forEach(function (radio) {
      radio.addEventListener('change', function () {
        say('', '');
      });
    });
    packageCards.forEach(function (card) {
      card.addEventListener('click', function () {
        if (status !== 'waiting') return;
        selectedCardPackageId = card.dataset.packageId || '';
        packageCards.forEach(function (c) { c.classList.remove('is-selected', 'selected'); });
        card.classList.add('is-selected');
        say('', '');
      });
    });

    if (packageForm) {
      packageForm.addEventListener('submit', handlePackagePayment);
    } else if (packageSubmitBtn) {
      packageSubmitBtn.addEventListener('click', handlePackagePayment);
    }
  }

  if (form && status === 'waiting') {
    fileInput.addEventListener('change', onFileChosen);
    removeBtn.addEventListener('click', function () { clearPreview(); say('', ''); });
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