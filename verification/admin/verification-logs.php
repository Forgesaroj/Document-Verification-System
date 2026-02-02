<?php
/**
 * Verification Logs Page
 * Shows both verified and unverified (spam) OTP requests
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Verification Logs';

// Pagination settings
$perPage = 25;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Filters
$typeFilter = sanitize($_GET['type'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');
$dateFrom = sanitize($_GET['date_from'] ?? '');
$dateTo = sanitize($_GET['date_to'] ?? '');
$search = sanitize($_GET['search'] ?? '');

// Fetch logs from OTP requests table (shows all - verified and unverified)
try {
    $db = getDB();

    // Build query
    $where = ['1=1'];
    $params = [];

    if ($typeFilter) {
        $where[] = "verification_type = ?";
        $params[] = $typeFilter;
    }

    if ($statusFilter === 'verified') {
        $where[] = "is_verified = 1";
    } elseif ($statusFilter === 'spam') {
        $where[] = "is_verified = 0 AND expires_at < NOW()";
    } elseif ($statusFilter === 'pending') {
        $where[] = "is_verified = 0 AND expires_at >= NOW()";
    }

    if ($dateFrom) {
        $where[] = "DATE(created_at) >= ?";
        $params[] = $dateFrom;
    }

    if ($dateTo) {
        $where[] = "DATE(created_at) <= ?";
        $params[] = $dateTo;
    }

    if ($search) {
        $where[] = "(email LIKE ? OR reference_number LIKE ? OR ip_address LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = implode(' AND ', $where);

    // Count total
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ver_otp_requests WHERE {$whereClause}");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // Fetch logs
    $stmt = $db->prepare("
        SELECT *,
            CASE
                WHEN is_verified = 1 THEN 'verified'
                WHEN is_verified = 0 AND expires_at < NOW() THEN 'spam'
                ELSE 'pending'
            END as status
        FROM ver_otp_requests
        WHERE {$whereClause}
        ORDER BY created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get stats
    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_otp_requests WHERE is_verified = 1 AND DATE(created_at) = CURDATE()");
    $todayVerified = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_otp_requests WHERE is_verified = 0 AND expires_at < NOW() AND DATE(created_at) = CURDATE()");
    $todaySpam = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_otp_requests WHERE is_verified = 1");
    $totalVerified = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_otp_requests WHERE is_verified = 0 AND expires_at < NOW()");
    $totalSpam = $stmt->fetch()['count'];

} catch (PDOException $e) {
    error_log("Logs fetch error: " . $e->getMessage());
    $logs = [];
    $total = 0;
    $totalPages = 0;
    $todayVerified = $todaySpam = $totalVerified = $totalSpam = 0;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-check-circle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Verified Today</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($todayVerified); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-red-100 text-red-600">
                <i class="fas fa-ban text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Spam Today</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($todaySpam); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-shield-alt text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Verified</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalVerified); ?></p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-orange-100 text-orange-600">
                <i class="fas fa-exclamation-triangle text-xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Spam</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalSpam); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="bg-white rounded-lg shadow">
    <!-- Filters -->
    <div class="p-6 border-b">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4">
            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                   placeholder="Search email, reference, IP..."
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">

            <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">All Types</option>
                <option value="document" <?php echo $typeFilter === 'document' ? 'selected' : ''; ?>>Document</option>
                <option value="bill" <?php echo $typeFilter === 'bill' ? 'selected' : ''; ?>>Bill</option>
            </select>

            <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                <option value="">All Status</option>
                <option value="verified" <?php echo $statusFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
                <option value="spam" <?php echo $statusFilter === 'spam' ? 'selected' : ''; ?>>Spam/Unverified</option>
                <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
            </select>

            <input type="date" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>"
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                   placeholder="From Date">

            <input type="date" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>"
                   class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                   placeholder="To Date">

            <div class="flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                    <i class="fas fa-filter mr-1"></i> Filter
                </button>
                <a href="verification-logs.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>

    <!-- Results Count -->
    <div class="px-6 py-3 bg-gray-50 border-b">
        <p class="text-sm text-gray-600">
            Showing <?php echo number_format(count($logs)); ?> of <?php echo number_format($total); ?> records
        </p>
    </div>

    <!-- Logs Table -->
    <div class="overflow-x-auto">
        <?php if (empty($logs)): ?>
            <p class="text-gray-500 text-center py-12">No verification logs found</p>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Attempts</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($logs as $log): ?>
                        <tr class="hover:bg-gray-50 <?php echo $log['status'] === 'spam' ? 'bg-red-50' : ''; ?>">
                            <td class="px-6 py-4 text-sm text-gray-800">
                                <?php echo formatDate($log['created_at'], 'M d, Y'); ?>
                                <div class="text-xs text-gray-500"><?php echo formatDate($log['created_at'], 'H:i:s'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($log['status'] === 'verified'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check mr-1"></i>Verified
                                    </span>
                                <?php elseif ($log['status'] === 'spam'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-ban mr-1"></i>Spam
                                    </span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-clock mr-1"></i>Pending
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php if ($log['verification_type'] === 'document'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Document</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-purple-100 text-purple-800">Bill</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-800">
                                <?php echo htmlspecialchars($log['reference_number']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php echo htmlspecialchars($log['email']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($log['ip_address']); ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo $log['attempts']; ?> / 3
                                <?php if ($log['attempts'] >= 3): ?>
                                    <span class="text-red-500 ml-1" title="Max attempts reached">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="p-4 border-t">
            <?php
            $baseUrl = 'verification-logs.php?' . http_build_query([
                'search' => $search,
                'type' => $typeFilter,
                'status' => $statusFilter,
                'date_from' => $dateFrom,
                'date_to' => $dateTo
            ]);
            echo generatePagination($page, $totalPages, $baseUrl);
            ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
