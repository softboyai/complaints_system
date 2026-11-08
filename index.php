<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// Redirect authenticated users to their dashboard.
if (!empty($_SESSION['user_id'])) {
    $role = $_SESSION['user_role'] ?? '';
    $dest = match($role) {
        'admin'  => APP_URL . '/admin/dashboard.php',
        'staff'  => APP_URL . '/staff/dashboard.php',
        default  => APP_URL . '/student/dashboard.php',
    };
    header('Location: ' . $dest);
    exit;
}

$page_title = 'Mount Kigali University Complaints and Feedback System';
$is_public  = true;
include __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="hero" id="hero">
  <div class="container">
    <div class="hero__eyebrow">Mount Kigali University</div>
    <h1 class="hero__title">Your voice matters<br><span>at MKU</span></h1>
    <p class="hero__sub">
      Submit complaints, track their progress and give feedback on your university experience.
      Transparent, fair and straightforward.
    </p>
    <div class="hero__actions">
      <a href="<?= APP_URL ?>/register.php" class="btn btn--primary btn--lg">Get started</a>
      <a href="<?= APP_URL ?>/login.php"    class="btn btn--ghost  btn--lg">Log in</a>
    </div>
  </div>
</section>

<!-- How it works -->
<section class="section" id="how-it-works">
  <div class="container">
    <h2 class="section__title">Submit a complaint in minutes</h2>
    <p class="section__sub">Three simple steps to make your voice heard and get a response.</p>
    <div class="steps-grid">
      <div class="step-card">
        <div class="step-card__num">1</div>
        <h3>Create an account</h3>
        <p>Register with your student registration number and set up a secure password.</p>
      </div>
      <div class="step-card">
        <div class="step-card__num">2</div>
        <h3>Submit your complaint</h3>
        <p>Describe your concern, choose a category and priority, and attach any supporting documents.</p>
      </div>
      <div class="step-card">
        <div class="step-card__num">3</div>
        <h3>Track and get a response</h3>
        <p>Follow the status in real time and receive responses from the responsible department.</p>
      </div>
    </div>
  </div>
</section>

<!-- Features -->
<section class="section section--alt" id="features">
  <div class="container">
    <h2 class="section__title">Everything you need in one place</h2>
    <p class="section__sub">A complete system built for students, staff and university management.</p>
    <div class="features-grid">
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('file-text') ?></div>
        <h3>Track every complaint</h3>
        <p>Each complaint gets a unique tracking ID. Follow the full status history from submission to resolution.</p>
      </div>
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('bell') ?></div>
        <h3>Real-time notifications</h3>
        <p>Get in-app notifications whenever your complaint status changes or a staff member replies.</p>
      </div>
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('lock') ?></div>
        <h3>Anonymous submissions</h3>
        <p>Submit a complaint anonymously. Your identity stays hidden from staff while the issue is investigated.</p>
      </div>
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('download') ?></div>
        <h3>PDF receipts</h3>
        <p>Download a printable PDF receipt for any complaint, including the full status timeline and responses.</p>
      </div>
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('star') ?></div>
        <h3>Feedback and ratings</h3>
        <p>Rate how your complaint was resolved and give feedback on courses, lecturers and services.</p>
      </div>
      <div class="feature-card">
        <div class="feature-card__icon"><?= icon('bar-chart') ?></div>
        <h3>Reports for management</h3>
        <p>Admins and staff can download full PDF and Excel reports filtered by date, department and status.</p>
      </div>
    </div>
  </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
