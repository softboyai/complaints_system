/**
 * validation.js
 * Client-side form validation mirroring the PHP rules in includes/validator.php.
 * Validates on blur and on submit. Shows inline error messages.
 */

(function () {
  'use strict';

  // Disposable email domains (subset - server checks a larger list).
  var DISPOSABLE_DOMAINS = [
    'mailinator.com', 'guerrillamail.com', 'tempmail.com', 'throwam.com',
    'yopmail.com', 'trashmail.com', 'fakeinbox.com', '10minutemail.com'
  ];

  // ============================================================
  // Validation Rules
  // ============================================================

  function validateName(value) {
    value = value.trim();
    if (!value) return 'This field is required.';
    if (value.length < 2 || value.length > 50) return 'Must be between 2 and 50 characters.';
    if (!/^[a-zA-ZÀ-ÿ\s'\-]+$/u.test(value)) return 'Only letters, spaces, hyphens and apostrophes are allowed.';
    return '';
  }

  function validateRegNumber(value) {
    value = value.trim();
    if (!value) return 'Registration number is required.';
    if (value.length < 6 || value.length > 20) return 'Must be between 6 and 20 characters.';
    if (!/^[a-zA-Z0-9\/\-]+$/.test(value)) return 'Use letters, numbers, slashes and hyphens only.';
    return '';
  }

  function validateEmail(value) {
    value = value.trim().toLowerCase();
    if (!value) return 'Email address is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) return 'Enter a valid email address.';
    var domain = value.split('@')[1] || '';
    if (DISPOSABLE_DOMAINS.indexOf(domain) !== -1) return 'Disposable email addresses are not allowed.';
    return '';
  }

  function validatePhone(value) {
    value = value.trim();
    if (!value) return ''; // Optional
    if (!/^\+?[0-9]{9,15}$/.test(value)) return 'Enter a valid phone number, for example +250788123456.';
    return '';
  }

  function validatePassword(value, fullName, email) {
    if (!value) return 'Password is required.';
    if (value.length < 8) return 'Password must be at least 8 characters.';
    if (/\s/.test(value)) return 'Password must not contain spaces.';
    if (!/[A-Z]/.test(value)) return 'Include at least one uppercase letter.';
    if (!/[a-z]/.test(value)) return 'Include at least one lowercase letter.';
    if (!/[0-9]/.test(value)) return 'Include at least one number.';
    if (!/[^a-zA-Z0-9]/.test(value)) return 'Include at least one special character.';
    var lower = value.toLowerCase();
    var firstName = (fullName || '').toLowerCase().split(' ')[0] || '';
    var emailPrefix = ((email || '').toLowerCase().split('@')[0]) || '';
    if (firstName.length > 2 && lower.includes(firstName)) return 'Password must not contain your name.';
    if (emailPrefix.length > 2 && lower.includes(emailPrefix)) return 'Password must not contain your email address.';
    return '';
  }

  function validatePasswordMatch(pw1, pw2) {
    if (pw1 !== pw2) return 'Passwords do not match.';
    return '';
  }

  function validateComplaintTitle(value) {
    value = value.trim();
    if (!value) return 'Title is required.';
    if (value.length < 5 || value.length > 100) return 'Title must be between 5 and 100 characters.';
    return '';
  }

  function validateComplaintDescription(value) {
    value = value.trim();
    if (!value) return 'Description is required.';
    if (value.length < 20 || value.length > 2000) return 'Description must be between 20 and 2000 characters.';
    return '';
  }

  function validateRequired(value, label) {
    if (!value.trim()) return (label || 'This field') + ' is required.';
    return '';
  }

  // ============================================================
  // Password Strength Meter
  // ============================================================
  function passwordStrength(value) {
    if (!value) return { score: 0, label: '' };
    var score = 0;
    if (value.length >= 8)   score++;
    if (value.length >= 12)  score++;
    if (/[A-Z]/.test(value)) score++;
    if (/[a-z]/.test(value)) score++;
    if (/[0-9]/.test(value)) score++;
    if (/[^a-zA-Z0-9]/.test(value)) score++;

    if (score <= 2) return { score: score, label: 'Weak',   color: 'var(--error)',   width: '25%'  };
    if (score <= 3) return { score: score, label: 'Fair',   color: 'var(--warning)', width: '50%'  };
    if (score <= 4) return { score: score, label: 'Good',   color: '#0099cc',        width: '75%'  };
    return              { score: score, label: 'Strong', color: 'var(--success)', width: '100%' };
  }

  function attachStrengthMeter(input) {
    var fill  = document.getElementById('strength-fill');
    var label = document.getElementById('strength-label');
    if (!fill || !label) return;

    input.addEventListener('input', function () {
      var result = passwordStrength(input.value);
      fill.style.width      = result.width || '0';
      fill.style.background = result.color || 'var(--border)';
      label.textContent     = result.label || '';
      label.style.color     = result.color || 'var(--text-muted)';
    });
  }

  // ============================================================
  // Show/hide password toggles
  // ============================================================
  function attachPasswordToggles() {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var targetId = btn.dataset.target;
        var input    = document.getElementById(targetId);
        if (!input) return;
        var isText = input.type === 'text';
        input.type = isText ? 'password' : 'text';
        var eyeIcon    = btn.querySelector('.icon-eye, svg:first-child');
        btn.setAttribute('aria-label', isText ? 'Show password' : 'Hide password');
      });
    });
  }

  // ============================================================
  // Field error helpers
  // ============================================================
  function showError(fieldId, message) {
    var input = document.getElementById(fieldId);
    var err   = document.getElementById(fieldId + '-error');
    if (input) {
      input.classList.toggle('form-input--error', !!message);
      input.setAttribute('aria-invalid', message ? 'true' : 'false');
    }
    if (err) err.textContent = message || '';
  }

  function clearError(fieldId) { showError(fieldId, ''); }

  // ============================================================
  // Register Form
  // ============================================================
  function setupRegisterForm() {
    var form = document.getElementById('register-form');
    if (!form) return;

    var nameInput     = document.getElementById('full_name');
    var regInput      = document.getElementById('reg_number');
    var emailInput    = document.getElementById('email');
    var phoneInput    = document.getElementById('phone');
    var passInput     = document.getElementById('password');
    var confirmInput  = document.getElementById('confirm_password');

    if (passInput) attachStrengthMeter(passInput);

    function getNameVal()  { return nameInput    ? nameInput.value    : ''; }
    function getEmailVal() { return emailInput   ? emailInput.value   : ''; }

    if (nameInput)    nameInput.addEventListener('blur',    function () { showError('full_name',        validateName(nameInput.value)); });
    if (regInput)     regInput.addEventListener('blur',     function () { showError('reg_number',       validateRegNumber(regInput.value)); });
    if (emailInput)   emailInput.addEventListener('blur',   function () { showError('email',            validateEmail(emailInput.value)); });
    if (phoneInput)   phoneInput.addEventListener('blur',   function () { showError('phone',            validatePhone(phoneInput.value)); });
    if (passInput)    passInput.addEventListener('blur',    function () { showError('password',         validatePassword(passInput.value, getNameVal(), getEmailVal())); });
    if (confirmInput) confirmInput.addEventListener('blur', function () { showError('confirm_password', validatePasswordMatch(passInput ? passInput.value : '', confirmInput.value)); });

    form.addEventListener('submit', function (e) {
      var errors = false;
      var checks = [
        { id: 'full_name',        fn: function () { return validateName(nameInput ? nameInput.value : ''); } },
        { id: 'reg_number',       fn: function () { return validateRegNumber(regInput ? regInput.value : ''); } },
        { id: 'email',            fn: function () { return validateEmail(emailInput ? emailInput.value : ''); } },
        { id: 'phone',            fn: function () { return validatePhone(phoneInput ? phoneInput.value : ''); } },
        { id: 'password',         fn: function () { return validatePassword(passInput ? passInput.value : '', getNameVal(), getEmailVal()); } },
        { id: 'confirm_password', fn: function () { return validatePasswordMatch(passInput ? passInput.value : '', confirmInput ? confirmInput.value : ''); } },
      ];

      checks.forEach(function (c) {
        var err = c.fn();
        showError(c.id, err);
        if (err) errors = true;
      });

      if (errors) e.preventDefault();
    });
  }

  // ============================================================
  // Login Form
  // ============================================================
  function setupLoginForm() {
    var form = document.getElementById('login-form');
    if (!form) return;

    var emailInput = document.getElementById('email');
    var passInput  = document.getElementById('password');

    if (emailInput) emailInput.addEventListener('blur', function () { showError('email', validateEmail(emailInput.value)); });

    form.addEventListener('submit', function (e) {
      var errors = false;
      var emailErr = validateEmail(emailInput ? emailInput.value : '');
      showError('email', emailErr);
      if (emailErr) errors = true;
      if (!passInput || !passInput.value.trim()) {
        showError('password', 'Password is required.');
        errors = true;
      }
      if (errors) e.preventDefault();
    });
  }

  // ============================================================
  // Forgot Password Form
  // ============================================================
  function setupForgotForm() {
    var form = document.getElementById('forgot-form');
    if (!form) return;
    var emailInput = document.getElementById('email');
    if (emailInput) emailInput.addEventListener('blur', function () { showError('email', validateEmail(emailInput.value)); });
    form.addEventListener('submit', function (e) {
      var err = validateEmail(emailInput ? emailInput.value : '');
      showError('email', err);
      if (err) e.preventDefault();
    });
  }

  // ============================================================
  // Reset Password Form
  // ============================================================
  function setupResetForm() {
    var form = document.getElementById('reset-form');
    if (!form) return;
    var passInput    = document.getElementById('password');
    var confirmInput = document.getElementById('confirm_password');

    if (passInput) {
      attachStrengthMeter(passInput);
      passInput.addEventListener('blur', function () {
        showError('password', validatePassword(passInput.value, '', ''));
      });
    }
    if (confirmInput) {
      confirmInput.addEventListener('blur', function () {
        showError('confirm_password', validatePasswordMatch(passInput ? passInput.value : '', confirmInput.value));
      });
    }

    form.addEventListener('submit', function (e) {
      var errors = false;
      var p1err = validatePassword(passInput ? passInput.value : '', '', '');
      var p2err = validatePasswordMatch(passInput ? passInput.value : '', confirmInput ? confirmInput.value : '');
      showError('password', p1err);
      showError('confirm_password', p2err);
      if (p1err || p2err) { errors = true; e.preventDefault(); }
    });
  }

  // ============================================================
  // Complaint Submit Form
  // ============================================================
  function setupComplaintForm() {
    var form = document.getElementById('complaint-form');
    if (!form) return;

    var titleInput = document.getElementById('title');
    var descInput  = document.getElementById('description');
    var catSelect  = document.getElementById('category');

    if (titleInput) titleInput.addEventListener('blur', function () { showError('title', validateComplaintTitle(titleInput.value)); });
    if (descInput)  descInput.addEventListener('blur',  function () { showError('description', validateComplaintDescription(descInput.value)); });
    if (catSelect)  catSelect.addEventListener('blur',  function () { showError('category', validateRequired(catSelect.value, 'Category')); });

    form.addEventListener('submit', function (e) {
      var errors = false;
      var titleErr = validateComplaintTitle(titleInput ? titleInput.value : '');
      var descErr  = validateComplaintDescription(descInput ? descInput.value : '');
      var catErr   = validateRequired(catSelect ? catSelect.value : '', 'Category');
      showError('title', titleErr);
      showError('description', descErr);
      showError('category', catErr);
      if (titleErr || descErr || catErr) { errors = true; e.preventDefault(); }
    });
  }

  // ============================================================
  // Profile / Password Change Forms
  // ============================================================
  function setupProfileForms() {
    // Password strength on new_password field (profile page).
    var newPass = document.getElementById('new_password');
    if (newPass) attachStrengthMeter(newPass);

    var confirmPass = document.getElementById('confirm_password');
    if (newPass && confirmPass) {
      confirmPass.addEventListener('blur', function () {
        showError('confirm_password', validatePasswordMatch(newPass.value, confirmPass.value));
      });
    }
  }

  // ============================================================
  // Init
  // ============================================================
  document.addEventListener('DOMContentLoaded', function () {
    attachPasswordToggles();
    setupRegisterForm();
    setupLoginForm();
    setupForgotForm();
    setupResetForm();
    setupComplaintForm();
    setupProfileForms();
  });

})();
