<?php
// Shared footer partial.
// Variables from including page:
//   $is_public   (bool) - true for public pages (landing, login, register)
//   $load_charts (bool) - true on dashboard pages that need Chart.js

$is_public   = $is_public   ?? !isset($_SESSION['user_id']);
$load_charts = $load_charts ?? false;
?>

<?php if ($is_public): ?>
  <!-- Public footer -->
  <footer class="public-footer">
    <div class="container">
      <div class="public-footer__inner">
        <div class="public-footer__brand">
          <a href="<?= APP_URL ?>/" class="public-footer__logo" aria-label="Mount Kigali University home">
            <img src="<?= APP_URL ?>/assets/images/MKUR-logo.png" alt="Mount Kigali University">
          </a>
          <p>A fair, transparent way to raise concerns and help improve the university experience.</p>
        </div>
        <nav class="public-footer__links" aria-label="Footer navigation">
          <h2 class="public-footer__heading">Explore</h2>
          <a href="<?= APP_URL ?>/#how-it-works">How it works</a>
          <a href="<?= APP_URL ?>/login.php">Log in</a>
          <a href="<?= APP_URL ?>/register.php">Create an account</a>
        </nav>
        <div class="public-footer__contact">
          <h2 class="public-footer__heading">Contact</h2>
          <a href="mailto:complaints@mku.ac.rw">complaints@mku.ac.rw</a>
          <span>Kigali, Rwanda</span>
        </div>
      </div>
      <div class="public-footer__bottom">
        <p class="public-footer__copy">&copy; <?= date('Y') ?> Mount Kigali University. All rights reserved.</p>
      </div>
    </div>
  </footer>

<?php else: ?>
      </main><!-- end .app-content -->
    </div><!-- end .app-main -->
  </div><!-- end .app-layout -->

  <!-- Session timeout warning modal -->
  <div class="modal" id="session-modal" role="dialog" aria-modal="true" aria-labelledby="session-modal-title" hidden>
    <div class="modal__overlay" aria-hidden="true"></div>
    <div class="modal__box modal__box--sm">
      <h2 class="modal__title" id="session-modal-title">Your session is about to expire</h2>
      <p class="modal__body">You will be logged out in <strong id="session-countdown">5:00</strong> due to inactivity.</p>
      <div class="modal__footer">
        <button class="btn btn--primary" id="session-extend">Stay logged in</button>
        <a href="<?= APP_URL ?>/logout.php" class="btn btn--ghost">Log out now</a>
      </div>
    </div>
  </div>
<?php endif; ?>

<!-- Toast container -->
<div id="toast-container" aria-live="polite" aria-atomic="true"></div>

<!-- Core JavaScript -->
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script src="<?= APP_URL ?>/assets/js/validation.js"></script>

<?php if ($load_charts): ?>
<script src="<?= APP_URL ?>/assets/js/chart.min.js"></script>
<script src="<?= APP_URL ?>/assets/js/charts.js"></script>
<?php endif; ?>

</body>
</html>
