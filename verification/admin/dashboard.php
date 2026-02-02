<?php
/**
 * Admin Dashboard
 * Shows statistics and recent activity
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Dashboard';

// Fetch statistics
try {
    $db = getDB();

    // Total documents
    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_documents WHERE status = 'active'");
    $totalDocuments = $stmt->fetch()['count'] ?? 0;

    // Total bills
    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_bills WHERE status = 'active'");
    $totalBills = $stmt->fetch()['count'] ?? 0;

    // Total verifications today
    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_verification_logs WHERE DATE(verified_at) = CURDATE()");
    $verificationsToday = $stmt->fetch()['count'] ?? 0;

    // Total verifications all time
    $stmt = $db->query("SELECT COUNT(*) as count FROM ver_verification_logs");
    $totalVerifications = $stmt->fetch()['count'] ?? 0;

    // Recent documents (last 5)
    $stmt = $db->query("
        SELECT d.*, a.name as admin_name
        FROM ver_documents d
        LEFT JOIN admins a ON d.created_by = a.id
        WHERE d.status = 'active'
        ORDER BY d.created_at DESC
        LIMIT 5
    ");
    $recentDocuments = $stmt->fetchAll();

    // Recent bills (last 5)
    $stmt = $db->query("
        SELECT b.*, a.name as admin_name
        FROM ver_bills b
        LEFT JOIN admins a ON b.created_by = a.id
        WHERE b.status = 'active'
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    $recentBills = $stmt->fetchAll();

    // Recent verification logs (last 10)
    $stmt = $db->query("
        SELECT * FROM ver_verification_logs
        ORDER BY verified_at DESC
        LIMIT 10
    ");
    $recentLogs = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $totalDocuments = $totalBills = $verificationsToday = $totalVerifications = 0;
    $recentDocuments = $recentBills = $recentLogs = [];
}

include __DIR__ . '/../includes/admin_header.php';
?>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Total Documents -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-blue-100 text-blue-600">
                <i class="fas fa-file-alt text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Documents</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalDocuments); ?></p>
            </div>
        </div>
    </div>

    <!-- Total Bills -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-green-100 text-green-600">
                <i class="fas fa-receipt text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Bills</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalBills); ?></p>
            </div>
        </div>
    </div>

    <!-- Verifications Today -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-yellow-100 text-yellow-600">
                <i class="fas fa-check-circle text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Verifications Today</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($verificationsToday); ?></p>
            </div>
        </div>
    </div>

    <!-- Total Verifications -->
    <div class="bg-white rounded-lg shadow p-6">
        <div class="flex items-center">
            <div class="p-3 rounded-full bg-purple-100 text-purple-600">
                <i class="fas fa-chart-line text-2xl"></i>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-gray-500">Total Verifications</p>
                <p class="text-2xl font-semibold text-gray-800"><?php echo number_format($totalVerifications); ?></p>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Recent Documents -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Recent Documents</h3>
            <a href="manage-documents.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
        </div>
        <div class="p-6">
            <?php if (empty($recentDocuments)): ?>
                <p class="text-gray-500 text-center py-4">No documents found</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentDocuments as $doc): ?>
                        <div class="flex items-center justify-between pb-4 border-b last:border-0">
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($doc['document_number']); ?></p>
                                <p class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars($doc['issued_by']); ?> |
                                    <?php echo htmlspecialchars($doc['document_date_bs']); ?> BS
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400"><?php echo formatDate($doc['created_at'], 'M d, Y'); ?></p>
                                <p class="text-xs text-gray-500">by <?php echo htmlspecialchars($doc['admin_name'] ?? 'Unknown'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Bills -->
    <div class="bg-white rounded-lg shadow">
        <div class="px-6 py-4 border-b flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-800">Recent Bills</h3>
            <a href="manage-bills.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
        </div>
        <div class="p-6">
            <?php if (empty($recentBills)): ?>
                <p class="text-gray-500 text-center py-4">No bills found</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentBills as $bill): ?>
                        <div class="flex items-center justify-between pb-4 border-b last:border-0">
                            <div>
                                <p class="font-medium text-gray-800"><?php echo htmlspecialchars($bill['bill_number']); ?></p>
                                <p class="text-sm text-gray-500">
                                    <?php echo $bill['is_non_pan'] ? 'Non-PAN' : 'PAN: ' . htmlspecialchars($bill['pan_number'] ?? '-'); ?> |
                                    <?php echo htmlspecialchars($bill['bill_date_bs']); ?> BS
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-400"><?php echo formatDate($bill['created_at'], 'M d, Y'); ?></p>
                                <p class="text-xs text-gray-500">by <?php echo htmlspecialchars($bill['admin_name'] ?? 'Unknown'); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Recent Verification Activity -->
<div class="bg-white rounded-lg shadow mt-6">
    <div class="px-6 py-4 border-b flex justify-between items-center">
        <h3 class="text-lg font-semibold text-gray-800">Recent Verification Activity</h3>
        <a href="verification-logs.php" class="text-sm text-blue-600 hover:text-blue-800">View All</a>
    </div>
    <div class="overflow-x-auto">
        <?php if (empty($recentLogs)): ?>
            <p class="text-gray-500 text-center py-8">No verification activity yet</p>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">IP Address</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($recentLogs as $log): ?>
                        <tr>
                            <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($log['email']); ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php if ($log['verification_type'] === 'document'): ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-blue-100 text-blue-800">Document</span>
                                <?php else: ?>
                                    <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">Bill</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-800"><?php echo htmlspecialchars($log['reference_number']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo htmlspecialchars($log['ip_address']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500"><?php echo formatDate($log['verified_at'], 'M d, Y H:i'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
