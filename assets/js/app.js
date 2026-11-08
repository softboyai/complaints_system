/**
 * app.js - Core application JavaScript
 * Handles: dark mode, sidebar, toasts, modals, notifications,
 *          session timeout, upload zone, FAQ accordion.
 */

// ============================================================
// Dark Mode
// ============================================================
(function () {
  'use strict';

  function applyDark(on) {
    document.documentElement.classList.toggle('dark', on);
    document.body.classList.toggle('dark', on);
  }

  // Apply saved preference immediately (before first paint).
  const saved = localStorage.getItem('mku_dark');
  if (saved === 'true') applyDark(true);

  document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('dark-toggle');
    if (!toggle) return;

    toggle.addEventListener('click', function () {
      const isDark = document.body.classList.toggle('dark');
      document.documentElement.classList.toggle('dark', isDark);
      localStorage.setItem('mku_dark', isDark ? 'true' : 'false');
    });
  });
})();


// ============================================================
// Toast System
// ============================================================
function showToast(message, type) {
  type = type || 'success';
  const container = document.getElementById('toast-container');
  if (!container) return;

  const toast = document.createElement('div');
  toast.className = 'toast toast--' + type;
  toast.setAttribute('role', 'alert');
  toast.setAttribute('aria-live', 'assertive');
  toast.innerHTML =
    '<span>' + escapeHtml(message) + '</span>' +
    '<button class="toast__close" aria-label="Close toast">&times;</button>';

  container.appendChild(toast);

  toast.querySelector('.toast__close').addEventListener('click', function () {
    dismissToast(toast);
  });

  // Auto dismiss after 4 seconds.
  setTimeout(function () { dismissToast(toast); }, 4000);
}

function dismissToast(toast) {
  if (!toast || !toast.parentNode) return;
  toast.style.animation = 'toast-out 0.25s ease forwards';
  setTimeout(function () { toast.remove(); }, 280);
}

function escapeHtml(str) {
  var d = document.createElement('div');
  d.appendChild(document.createTextNode(str));
  return d.innerHTML;
}


// ============================================================
// Main DOMContentLoaded
// ============================================================
document.addEventListener('DOMContentLoaded', function () {

  // ----------------------------------------------------------
  // Sidebar toggle
  // ----------------------------------------------------------
  const sidebar        = document.getElementById('sidebar');
  const sidebarToggle  = document.getElementById('sidebar-toggle');
  const sidebarClose   = document.getElementById('sidebar-close');
  const sidebarOverlay = document.getElementById('sidebar-overlay');

  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add('sidebar--open');
    if (sidebarOverlay) {
      sidebarOverlay.classList.add('sidebar-overlay--show');
      sidebarOverlay.removeAttribute('aria-hidden');
    }
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'true');
  }

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove('sidebar--open');
    if (sidebarOverlay) {
      sidebarOverlay.classList.remove('sidebar-overlay--show');
      sidebarOverlay.setAttribute('aria-hidden', 'true');
    }
    if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
  }

  if (sidebarToggle) sidebarToggle.addEventListener('click', openSidebar);
  if (sidebarClose)  sidebarClose.addEventListener('click', closeSidebar);
  if (sidebarOverlay) sidebarOverlay.addEventListener('click', closeSidebar);

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      closeSidebar();
      closeAllDropdowns();
    }
  });


  // ----------------------------------------------------------
  // Dropdown helpers (notifications + avatar)
  // ----------------------------------------------------------
  function closeAllDropdowns() {
    var dropdowns = document.querySelectorAll('.notifications-dropdown, .avatar-dropdown');
    dropdowns.forEach(function (d) { d.setAttribute('hidden', ''); });
    var btns = document.querySelectorAll('[aria-haspopup="true"]');
    btns.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
  }

  document.addEventListener('click', function (e) {
    if (!e.target.closest('.notifications-wrap') && !e.target.closest('.avatar-wrap')) {
      closeAllDropdowns();
    }
  });


  // ----------------------------------------------------------
  // Notifications bell
  // ----------------------------------------------------------
  var notifBtn      = document.getElementById('notifications-btn');
  var notifDropdown = document.getElementById('notifications-dropdown');
  var notifBadge    = document.getElementById('notif-badge');
  var notifList     = document.getElementById('notif-list');
  var markAllBtn    = document.getElementById('notif-mark-all');

  function loadNotifications() {
    if (!notifList) return;
    fetch(window.APP_URL + '/api/notifications.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        // Update badge.
        if (notifBadge) {
          if (data.unread > 0) {
            notifBadge.textContent = data.unread > 99 ? '99+' : data.unread;
            notifBadge.style.display = 'flex';
          } else {
            notifBadge.style.display = 'none';
          }
        }

        // Render list.
        if (!data.notifications || data.notifications.length === 0) {
          notifList.innerHTML = '<div class="notif-empty">No notifications</div>';
          return;
        }

        notifList.innerHTML = data.notifications.map(function (n) {
          var cls = n.is_read ? 'notif-item' : 'notif-item notif-item--unread';
          var link = n.link ? n.link : '#';
          return '<a href="' + escapeHtml(link) + '" class="' + cls + '" data-id="' + n.id + '">' +
            (n.is_read ? '' : '<span class="notif-item__dot"></span>') +
            '<div><div class="notif-item__msg">' + escapeHtml(n.message) + '</div>' +
            '<div class="notif-item__time">' + escapeHtml(n.created_at ? n.created_at.substring(0, 16) : '') + '</div></div>' +
            '</a>';
        }).join('');

        // Mark as read on click.
        notifList.querySelectorAll('.notif-item').forEach(function (item) {
          item.addEventListener('click', function () {
            var id = item.dataset.id;
            if (id && item.classList.contains('notif-item--unread')) {
              var fd = new FormData();
              fd.append('action', 'mark_read');
              fd.append('id', id);
              fd.append('csrf_token', window.CSRF_TOKEN || '');
              fetch(window.APP_URL + '/api/notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' });
              item.classList.remove('notif-item--unread');
              var dot = item.querySelector('.notif-item__dot');
              if (dot) dot.remove();
            }
          });
        });
      })
      .catch(function () {
        if (notifList) notifList.innerHTML = '<div class="notif-empty">Could not load notifications.</div>';
      });
  }

  if (notifBtn) {
    notifBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = notifDropdown && !notifDropdown.hidden;
      closeAllDropdowns();
      if (!isOpen && notifDropdown) {
        notifDropdown.removeAttribute('hidden');
        notifBtn.setAttribute('aria-expanded', 'true');
        loadNotifications();
      }
    });
  }

  if (markAllBtn) {
    markAllBtn.addEventListener('click', function () {
      var fd = new FormData();
      fd.append('action', 'mark_all_read');
      fd.append('csrf_token', window.CSRF_TOKEN || '');
      fetch(window.APP_URL + '/api/notifications.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function () { loadNotifications(); });
    });
  }

  // Avatar dropdown.
  var avatarBtn = document.getElementById('avatar-btn');
  var avatarDrop = document.getElementById('avatar-dropdown');

  if (avatarBtn) {
    avatarBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isOpen = avatarDrop && !avatarDrop.hidden;
      closeAllDropdowns();
      if (!isOpen && avatarDrop) {
        avatarDrop.removeAttribute('hidden');
        avatarBtn.setAttribute('aria-expanded', 'true');
      }
    });
  }

  // Load unread count on page load.
  if (notifBadge) {
    fetch(window.APP_URL + '/api/notifications.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (data.unread > 0) {
          notifBadge.textContent = data.unread > 99 ? '99+' : data.unread;
          notifBadge.style.display = 'flex';
        }
      })
      .catch(function () {});
  }


  // ----------------------------------------------------------
  // Session timeout warning
  // ----------------------------------------------------------
  var SESSION_TIMEOUT_MS = 25 * 60 * 1000; // Warn at 25 min
  var LOGOUT_TIMEOUT_MS  = 30 * 60 * 1000; // Force logout at 30 min
  var sessionModal = document.getElementById('session-modal');
  var extendBtn    = document.getElementById('session-extend');
  var countdown    = document.getElementById('session-countdown');

  if (sessionModal) {
    var warnTimer, logoutTimer, countdownInterval;

    function resetSessionTimers() {
      clearTimeout(warnTimer);
      clearTimeout(logoutTimer);
      clearInterval(countdownInterval);

      warnTimer = setTimeout(function () {
        sessionModal.removeAttribute('hidden');
        var secs = 5 * 60;
        countdown.textContent = '5:00';
        countdownInterval = setInterval(function () {
          secs--;
          var m = Math.floor(secs / 60);
          var s = secs % 60;
          countdown.textContent = m + ':' + (s < 10 ? '0' : '') + s;
          if (secs <= 0) { clearInterval(countdownInterval); }
        }, 1000);
      }, SESSION_TIMEOUT_MS);

      logoutTimer = setTimeout(function () {
        window.location.href = window.APP_URL + '/logout.php';
      }, LOGOUT_TIMEOUT_MS);
    }

    if (extendBtn) {
      extendBtn.addEventListener('click', function () {
        sessionModal.setAttribute('hidden', '');
        clearInterval(countdownInterval);
        // Ping server to extend session.
        fetch(window.APP_URL + '/api/notifications.php', { credentials: 'same-origin' }).catch(function () {});
        resetSessionTimers();
      });
    }

    // Reset timers on user activity.
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(function (ev) {
      document.addEventListener(ev, resetSessionTimers, { passive: true });
    });

    resetSessionTimers();
  }


  // ----------------------------------------------------------
  // File upload drag-and-drop zone
  // ----------------------------------------------------------
  var uploadZone    = document.getElementById('upload-zone');
  var uploadInput   = document.getElementById('attachments');
  var uploadPreview = document.getElementById('upload-preview');

  if (uploadZone && uploadInput) {
    uploadZone.addEventListener('click', function (e) {
      if (e.target !== uploadInput) uploadInput.click();
    });

    uploadZone.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); uploadInput.click(); }
    });

    uploadZone.addEventListener('dragover', function (e) {
      e.preventDefault();
      uploadZone.classList.add('upload-zone--drag');
    });

    uploadZone.addEventListener('dragleave', function () {
      uploadZone.classList.remove('upload-zone--drag');
    });

    uploadZone.addEventListener('drop', function (e) {
      e.preventDefault();
      uploadZone.classList.remove('upload-zone--drag');
      // Create a DataTransfer and assign dropped files to input.
      var dt = new DataTransfer();
      var existing = Array.from(uploadInput.files || []);
      var dropped  = Array.from(e.dataTransfer.files);
      existing.concat(dropped).slice(0, 5).forEach(function (f) { dt.items.add(f); });
      uploadInput.files = dt.files;
      renderPreview(uploadInput.files);
    });

    uploadInput.addEventListener('change', function () {
      renderPreview(uploadInput.files);
    });

    function renderPreview(files) {
      if (!uploadPreview) return;
      uploadPreview.innerHTML = '';
      Array.from(files).forEach(function (f, i) {
        var item = document.createElement('div');
        item.className = 'upload-preview__item';
        item.innerHTML =
          '<span>' + escapeHtml(f.name) + '</span>' +
          '<span style="color:var(--text-muted);font-size:0.8rem">' + formatBytes(f.size) + '</span>' +
          '<button type="button" class="upload-preview__remove" data-index="' + i + '" aria-label="Remove ' + escapeHtml(f.name) + '">&times;</button>';
        uploadPreview.appendChild(item);
      });

      uploadPreview.querySelectorAll('.upload-preview__remove').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var idx = parseInt(btn.dataset.index);
          var dt  = new DataTransfer();
          Array.from(uploadInput.files).forEach(function (f, i) {
            if (i !== idx) dt.items.add(f);
          });
          uploadInput.files = dt.files;
          renderPreview(uploadInput.files);
        });
      });
    }
  }


  // ----------------------------------------------------------
  // Character counter for textareas
  // ----------------------------------------------------------
  var desc = document.getElementById('description');
  var descChars = document.getElementById('desc-chars');
  if (desc && descChars) {
    function updateCharCount() { descChars.textContent = desc.value.length; }
    desc.addEventListener('input', updateCharCount);
    updateCharCount();
  }


  // ----------------------------------------------------------
  // FAQ accordion
  // ----------------------------------------------------------
  document.querySelectorAll('.faq-item__q').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var item = btn.closest('.faq-item');
      var isOpen = item.classList.contains('faq-item--open');
      // Close all.
      document.querySelectorAll('.faq-item').forEach(function (i) {
        i.classList.remove('faq-item--open');
        i.querySelector('.faq-item__q').setAttribute('aria-expanded', 'false');
      });
      if (!isOpen) {
        item.classList.add('faq-item--open');
        btn.setAttribute('aria-expanded', 'true');
      }
    });
  });


  // ----------------------------------------------------------
  // Help page tabs
  // ----------------------------------------------------------
  document.querySelectorAll('.help-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.help-tab').forEach(function (t) {
        t.classList.remove('help-tab--active');
        t.setAttribute('aria-selected', 'false');
      });
      document.querySelectorAll('.help-content').forEach(function (c) {
        c.classList.remove('help-content--active');
      });
      tab.classList.add('help-tab--active');
      tab.setAttribute('aria-selected', 'true');
      var target = document.getElementById(tab.dataset.target);
      if (target) target.classList.add('help-content--active');
    });
  });


  // ----------------------------------------------------------
  // Star rating widget (interactive)
  // ----------------------------------------------------------
  document.querySelectorAll('.star-rating').forEach(function (widget) {
    var labels = widget.querySelectorAll('.star-rating__label');
    var radios = widget.querySelectorAll('input[type="radio"]');

    function updateVisual() {
      var checked = widget.querySelector('input:checked');
      var val = checked ? parseInt(checked.value) : 0;
      labels.forEach(function (lbl, idx) {
        var svg = lbl.querySelector('svg');
        if (svg) svg.style.color = idx < val ? 'var(--warning)' : 'var(--border)';
      });
    }

    radios.forEach(function (radio) {
      radio.addEventListener('change', updateVisual);
    });

    labels.forEach(function (lbl, idx) {
      lbl.addEventListener('mouseenter', function () {
        labels.forEach(function (l, i) {
          var svg = l.querySelector('svg');
          if (svg) svg.style.color = i <= idx ? 'var(--warning)' : 'var(--border)';
        });
      });
    });

    widget.addEventListener('mouseleave', updateVisual);
    updateVisual();
  });


  // ----------------------------------------------------------
  // Report download buttons: spinner while loading
  // ----------------------------------------------------------
  document.querySelectorAll('.report-download-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var label   = btn.querySelector('.btn__label');
      var spinner = btn.querySelector('.btn__spinner');
      if (label)   label.setAttribute('hidden', '');
      if (spinner) spinner.removeAttribute('hidden');
      setTimeout(function () {
        if (label)   label.removeAttribute('hidden');
        if (spinner) spinner.setAttribute('hidden', '');
        showToast('Download started.', 'success');
      }, 2000);
    });
  });


  // ----------------------------------------------------------
  // Form submit: show spinner on submit button
  // ----------------------------------------------------------
  document.querySelectorAll('form').forEach(function (form) {
    form.addEventListener('submit', function () {
      var btn = form.querySelector('[type="submit"]');
      if (!btn) return;
      var label   = btn.querySelector('.btn__label');
      var spinner = btn.querySelector('.btn__spinner');
      if (label)   label.setAttribute('hidden', '');
      if (spinner) spinner.removeAttribute('hidden');
      btn.disabled = true;
      // Re-enable after 10s to prevent permanent lockout on validation failure.
      setTimeout(function () {
        btn.disabled = false;
        if (label)   label.removeAttribute('hidden');
        if (spinner) spinner.setAttribute('hidden', '');
      }, 10000);
    });
  });

}); // end DOMContentLoaded


// ============================================================
// Utilities
// ============================================================
function formatBytes(bytes) {
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024)    return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
}
