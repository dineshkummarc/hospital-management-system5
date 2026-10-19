<?php
/**
 * Top navbar + role-based sidebar.
 * Include AFTER header.php, and after auth.php has already run requireRole().
 */
$pdo = $pdo ?? getDB();
$role = currentRole();
$unreadCount = isLoggedIn() ? getUnreadNotificationCount($pdo, currentUserId()) : 0;
$notifications = isLoggedIn() ? getRecentNotifications($pdo, currentUserId()) : [];

$navLinks = [
    'patient' => [
        ['dashboard.php', 'fa-gauge', 'Dashboard'],
        ['appointments.php', 'fa-calendar-check', 'Appointments'],
        ['book_appointment.php', 'fa-calendar-plus', 'Book Appointment'],
        ['medical_records.php', 'fa-file-medical', 'Medical Records'],
        ['bills.php', 'fa-file-invoice-dollar', 'Bills'],
        ['profile.php', 'fa-user', 'Profile'],
    ],
    'doctor' => [
        ['dashboard.php', 'fa-gauge', 'Dashboard'],
        ['appointments.php', 'fa-calendar-check', 'Appointments'],
        ['patients.php', 'fa-users', 'Patients'],
        ['medical_records.php', 'fa-file-medical', 'Medical Records'],
        ['profile.php', 'fa-user', 'Profile'],
    ],
    'admin' => [
        ['dashboard.php', 'fa-gauge', 'Dashboard'],
        ['patients.php', 'fa-users', 'Patients'],
        ['doctors.php', 'fa-user-doctor', 'Doctors'],
        ['staff.php', 'fa-id-badge', 'Staff'],
        ['wards.php', 'fa-hospital', 'Wards'],
        ['beds.php', 'fa-bed', 'Beds'],
        ['billing.php', 'fa-file-invoice-dollar', 'Billing'],
        ['reports.php', 'fa-chart-line', 'Reports'],
        ['audit_logs.php', 'fa-clipboard-list', 'Audit Logs'],
    ],
];

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-lg hms-navbar sticky-top">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold" href="<?= esc(roleHomePage($role)) ?>">
      <i class="fa-solid fa-hospital-user me-2"></i>HMS
    </a>
    <button class="navbar-toggler d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarCollapse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="ms-auto d-flex align-items-center gap-3">
      <div class="dropdown">
        <a class="text-white position-relative" href="#" role="button" data-bs-toggle="dropdown"
           onclick="fetch('/hms/api/notifications/mark_read.php', {method:'POST'}).catch(()=>{})">
          <i class="fa-solid fa-bell fs-5"></i>
          <?php if ($unreadCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
              <?= $unreadCount ?>
            </span>
          <?php endif; ?>
        </a>
        <ul class="dropdown-menu dropdown-menu-end notif-dropdown">
          <li><h6 class="dropdown-header">Notifications</h6></li>
          <?php if (empty($notifications)): ?>
            <li><span class="dropdown-item-text text-muted small">No notifications yet.</span></li>
          <?php else: ?>
            <?php foreach ($notifications as $n): ?>
              <li>
                <div class="dropdown-item-text small <?= $n['is_read'] ? 'text-muted' : '' ?>">
                  <strong><?= esc($n['title']) ?></strong><br>
                  <?= esc($n['message']) ?><br>
                  <span class="text-muted"><?= formatDateTime($n['created_at']) ?></span>
                </div>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
      <span class="text-white small d-none d-md-inline">
        <i class="fa-solid fa-user-circle me-1"></i><?= esc($_SESSION['name'] ?? '') ?>
        <span class="badge bg-light text-dark text-capitalize ms-1"><?= esc($role ?? '') ?></span>
      </span>
      <a href="/hms/logout.php" class="btn btn-sm btn-outline-light"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
    </div>
  </div>
</nav>

<div class="d-flex hms-layout">
  <aside class="hms-sidebar collapse d-lg-block" id="sidebarCollapse">
    <nav class="nav flex-column p-3">
      <?php foreach ($navLinks[$role] ?? [] as [$href, $icon, $label]): ?>
        <a class="nav-link <?= $currentPage === $href ? 'active' : '' ?>" href="<?= esc($href) ?>">
          <i class="fa-solid <?= esc($icon) ?> me-2"></i><?= esc($label) ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <main class="hms-content flex-grow-1 p-3 p-md-4">
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
      <div class="alert alert-<?= esc($flash['type']) ?> alert-dismissible fade show" role="alert">
        <?= esc($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
