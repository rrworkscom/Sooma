<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__) . '/login-regist/connection.php';

$serviceInIncludes = dirname(__DIR__) . '/includes/dashboard-service.php';
$serviceInApi      = __DIR__ . '/api/dashboard-service.php';
require_once file_exists($serviceInIncludes) ? $serviceInIncludes : $serviceInApi;

$dashboardData = getDashboardPayload($pdo, $_SESSION['csrf_token']);

$pageTitle  = 'Booking Management';
$bodyClass  = 'admin-booking-page';
$headerType = 'admin';
$headerLogo = 'black';
$basePath   = '../assets/';

require_once __DIR__ . '/../includes/header.php';

function book_e(?string $val): string
{
    return htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');
}

function book_is_private_accepted(array $b): bool
{
    $idStr        = (string) ($b['id'] ?? '');
    $isPrivateReq = !empty($b['is_private_request']) || str_starts_with($idStr, 'req-');
    $reqStatus    = strtolower(trim((string) ($b['private_status'] ?? $b['booking_status'] ?? $b['request_status'] ?? $b['approval_status'] ?? '')));

    if (in_array($reqStatus, ['accepted', 'approved', 'confirmed', 'completed', 'waiting_payment', 'waiting_verification'], true)) {
        return true;
    }
    if (!empty($b['is_accepted']) || !empty($b['private_accepted'])) {
        return true;
    }
    if (!$isPrivateReq && $idStr !== '') {
        return true;
    }
    return strtolower(trim((string) ($b['status'] ?? ''))) === 'confirmed';
}

function book_render_class_type(array $b): string
{
    $rawType = trim((string) ($b['class_type'] ?? $b['type_class'] ?? $b['type'] ?? 'Regular'));
    if ($rawType === '') {
        $rawType = 'Regular';
    }
    if (strcasecmp($rawType, 'private') === 0) {
        if (book_is_private_accepted($b)) {
            return '<span class="class-type-text" title="Private class (Accepted)">Private</span>';
        }
        return '<span class="class-type-text class-type-text--pending" title="Private class (Waiting for approval)">'
             . '<span>Private</span>'
             . '<i class="ph-bold ph-clock-countdown class-type-text__icon" aria-hidden="true"></i>'
             . '</span>';
    }
    return book_e(ucfirst(strtolower($rawType)));
}

$bkpi = $dashboardData['booking_kpi'];
$bookingCards = [
    'total_bookings' => [
        'label' => 'Total Booking',
        'note'  => 'All reservations',
        'mod'   => 'bookings',
        'icon'  => 'ph-package',
    ],
    'check' => [
        'label' => 'Check',
        'note'  => 'Receipts to verify',
        'mod'   => 'pending',
        'icon'  => 'ph-magnifying-glass',
    ],
    'confirmed' => [
        'label' => 'Confirmed',
        'note'  => 'Approved bookings',
        'mod'   => 'revenue',
        'icon'  => 'ph-check-circle',
    ],
    'rejected' => [
        'label' => 'Rejected',
        'note'  => 'From total bookings',
        'mod'   => 'rejected',
        'icon'  => 'ph-x-circle',
    ],
];

$bookingsList = (array) $dashboardData['bookings'];
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Nunito+Sans:wght@600&family=Poppins:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/booking-management.css">

<main class="dashboard-page"
      data-fetch-endpoint="./api/dashboard-data.php"
      data-verify-endpoint="./api/verif-payment.php"
      data-delete-endpoint="./api/delete-booking.php">

    <header class="page-header">
        <h1 class="dashboard-title">Booking Management</h1>
        <p class="dashboard-subtitle">Manage and track all studio reservations and attendee logs</p>
    </header>

    <section class="summary-grid" aria-label="Booking statistics">
        <?php foreach ($bookingCards as $key => $meta):
            $stat = $bkpi[$key];
            $dir  = $stat['dir'] === 'down' ? 'down' : 'up';
        ?>
            <article class="stat-card" data-booking-kpi="<?= book_e($key) ?>">
                <h2 class="stat-card__label"><?= book_e($meta['label']) ?></h2>
                <p class="stat-card__value"><?= book_e($stat['value']) ?></p>
                <p class="stat-card__trend stat-card__trend--<?= book_e($dir) ?>">
                    <i class="ph ph-trend-<?= book_e($dir) ?>" style="font-size: 24px;"></i>
                    <span>
                        <strong><?= book_e($stat['pct']) ?></strong>
                        <span class="stat-card__note"><?= book_e($meta['note']) ?></span>
                    </span>
                </p>
                <div class="stat-card__icon stat-card__icon--<?= book_e($meta['mod']) ?>">
                    <i class="ph-fill <?= book_e($meta['icon']) ?>" style="font-size: 26px;"></i>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <h2 class="section-title" style="margin-top: 20px;">Filter &amp; Search Bar</h2>

    <div class="filter-bar" role="search">
        <label class="filter-control filter-control--search">
            <i class="ph ph-magnifying-glass filter-control__icon" aria-hidden="true"></i>
            <input type="search" id="filterSearch" placeholder="Search name, id ..." autocomplete="off"
                aria-label="Search by booking ID or customer name">
        </label>

        <div class="filter-control filter-control--date">
            <i class="ph ph-calendar-blank filter-control__icon" aria-hidden="true"></i>
            <span class="filter-control__text" id="filterDateText">Date</span>
            <button type="button" class="filter-control__clear" id="filterDateClear" aria-label="Clear date filter" hidden>&times;</button>
            <input type="date" id="filterDate" aria-label="Filter by date">
        </div>

        <label class="filter-control filter-control--status">
            <i class="ph ph-tag filter-control__icon" aria-hidden="true"></i>
            <select id="filterStatus" aria-label="Filter by payment status">
                <option value="">Status</option>
                <option value="check">Check</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="rejected">Rejected</option>
            </select>
            <i class="ph ph-caret-down filter-control__chevron" aria-hidden="true"></i>
        </label>
    </div>

    <section class="table-card" aria-label="Bookings">
        <div class="table-card__scroll">
            <table class="bookings-table">
                <colgroup>
                    <col style="width:10.5%">
                    <col style="width:13.5%">
                    <col style="width:14.5%">
                    <col style="width:11.5%">
                    <col style="width:12.5%">
                    <col style="width:14.5%">
                    <col style="width:15.0%">
                    <col style="width:8.0%">
                </colgroup>
                <thead>
                    <tr>
                        <th scope="col">Booking ID</th>
                        <th scope="col">Name</th>
                        <th scope="col">Class &amp; Level</th>
                        <th scope="col">Type Class</th>
                        <th scope="col">Date</th>
                        <th scope="col">Medical Note</th>
                        <th scope="col">Payment Receipt</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody id="bookingsBody">
                    <?php foreach ($bookingsList as $b):
                        $statusKey   = $b['status'];
                        $statusLabel = $dashboardData['statusLabels'][$statusKey] ?? ucfirst($statusKey);
                    ?>
                        <tr data-id="<?= book_e($b['id']) ?>"
                            data-name="<?= book_e(strtolower($b['name'])) ?>"
                            data-date="<?= book_e($b['date_iso']) ?>"
                            data-status="<?= book_e($statusKey) ?>">
                            <td><?= book_e($b['display_id']) ?></td>
                            <td><?= book_e($b['name']) ?></td>
                            <td><?= book_e($b['class_name']) ?><br><?= book_e($b['level']) ?></td>
                            <td class="js-class-type-cell"><?= book_render_class_type($b) ?></td>
                            <td><?= $b['date_table'] ?></td>
                            <td><?= book_e($b['medical']) ?></td>
                            <td>
                                <button type="button"
                                        class="status-badge status-badge--<?= book_e($statusKey) ?> js-open-receipt"
                                        data-id="<?= book_e($b['id']) ?>"
                                        aria-haspopup="dialog">
                                    <?= book_e($statusLabel) ?>
                                </button>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="icon-btn js-view-detail" data-id="<?= book_e($b['id']) ?>" aria-haspopup="dialog" title="View detail">
                                        <i class="ph-fill ph-eye" style="font-size: 24px;"></i>
                                    </button>
                                    <button type="button" class="icon-btn js-delete" data-id="<?= book_e($b['id']) ?>" title="Delete">
                                        <i class="ph-fill ph-trash" style="font-size: 23px;"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr class="bookings-empty" id="bookingsEmpty" <?= !empty($bookingsList) ? 'hidden' : '' ?>>
                        <td colspan="8">No bookings found.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <nav class="table-pagination" id="pagination" aria-label="Bookings pagination">
            <button type="button" class="page-arrow" id="pagePrev" aria-label="Previous page">
                <i class="ph-bold ph-caret-left" style="font-size: 20px;" aria-hidden="true"></i>
            </button>
            <span class="page-numbers" id="pageNumbers"></span>
            <button type="button" class="page-arrow" id="pageNext" aria-label="Next page">
                <i class="ph-bold ph-caret-right" style="font-size: 20px;" aria-hidden="true"></i>
            </button>
        </nav>
    </section>
</main>

<div class="dash-modal-overlay" id="receiptModal" aria-hidden="true">
    <div class="dash-modal" role="dialog" aria-modal="true" aria-labelledby="receiptModalTitle" tabindex="-1">
        <header class="dash-modal__header">
            <div>
                <p class="dash-modal__eyebrow">Verify Payment Receipt</p>
                <h2 class="dash-modal__title" id="receiptModalTitle" data-field="display_id"></h2>
            </div>
            <button type="button" class="dash-modal__close" aria-label="Close" data-close>&times;</button>
        </header>

        <div class="dash-modal__body">
            <dl class="info-grid info-grid--compact">
                <div>
                    <dt>Booking ID</dt>
                    <dd data-field="display_id"></dd>
                </div>
                <div>
                    <dt>Customer</dt>
                    <dd data-field="name"></dd>
                </div>
                <div>
                    <dt>Schedule</dt>
                    <dd data-field="schedule"></dd>
                </div>
                <div>
                    <dt>Coach</dt>
                    <dd data-field="coach"></dd>
                </div>
                <div>
                    <dt>Total Payment</dt>
                    <dd class="info-grid__amount" data-field="amount"></dd>
                </div>
            </dl>

            <figure class="receipt-figure">
                <img id="receiptImage" src="" alt="Payment receipt" hidden>
                <p class="receipt-figure__empty" id="receiptEmpty" hidden>No payment receipt has been uploaded yet.</p>
            </figure>

            <div class="receipt-feedback" id="receiptFeedback">
                <label for="rejectionReason" class="receipt-feedback__label">
                    Admin Note / Rejection Reason <span class="receipt-feedback__hint">(Required if rejected)</span>
                </label>
                <textarea
                    id="rejectionReason"
                    class="receipt-feedback__input"
                    rows="3"
                    placeholder="e.g., Nominal transfer tidak sesuai, bukti buram, atau salah rekening tujuan..."></textarea>
                <p class="receipt-feedback__error" id="rejectionError" hidden>Please provide a reason before rejecting this payment.</p>
            </div>
        </div>

        <footer class="dash-modal__footer">
            <p class="dash-modal__note" id="receiptNote" hidden></p>
            <button type="button" class="btn-secondary" id="btnCloseReceipt" data-close hidden>Close</button>
            <button type="button" class="btn-reject" id="btnReject">Reject</button>
            <button type="button" class="btn-checked" id="btnApprove">Approve</button>
        </footer>
    </div>
</div>

<div class="dash-modal-overlay" id="detailModal" aria-hidden="true">
    <div class="dash-modal" role="dialog" aria-modal="true" aria-labelledby="detailModalTitle" tabindex="-1">
        <header class="dash-modal__header">
            <div>
                <p class="dash-modal__eyebrow">Booking Detail</p>
                <h2 class="dash-modal__title" id="detailModalTitle" data-field="display_id"></h2>
            </div>
            <button type="button" class="dash-modal__close" aria-label="Close" data-close>&times;</button>
        </header>

        <div class="dash-modal__body">
            <dl class="info-grid">
                <div>
                    <dt>Booking ID</dt>
                    <dd data-field="display_id"></dd>
                </div>
                <div>
                    <dt>Customer Name</dt>
                    <dd data-field="name"></dd>
                </div>
                <div>
                    <dt>Class &amp; Level</dt>
                    <dd data-field="class_level"></dd>
                </div>
                <div>
                    <dt>Type Class</dt>
                    <dd id="detailClassType"></dd>
                </div>
                <div>
                    <dt>Schedule</dt>
                    <dd data-field="schedule"></dd>
                </div>
                <div>
                    <dt>Medical Note</dt>
                    <dd data-field="medical"></dd>
                </div>
                <div>
                    <dt>Coach</dt>
                    <dd data-field="coach"></dd>
                </div>
                <div>
                    <dt>Payment Status</dt>
                    <dd><span class="status-badge status-badge--static" id="detailStatus"></span></dd>
                </div>
            </dl>
        </div>

        <footer class="dash-modal__footer">
            <button type="button" class="btn-secondary" data-close>Close</button>
        </footer>
    </div>
</div>

<p class="dash-toast" id="dashToast" role="status" aria-live="polite" hidden></p>

<script type="application/json" id="dashboard-data">
    <?= json_encode($dashboardData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>
</script>
<script src="./assets/js/booking-management.js"></script>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>