<?php
/**
 * Manage Bills Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Manage Bills';

// Pagination settings
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? 'active');
$typeFilter = sanitize($_GET['type'] ?? '');

// Handle delete/status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        $billId = (int)($_POST['bill_id'] ?? 0);

        if ($billId > 0) {
            try {
                $db = getDB();

                if ($action === 'delete') {
                    $stmt = $db->prepare("UPDATE ver_bills SET status = 'deleted', updated_by = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id'], $billId]);
                    logAdminActivity($_SESSION['admin_id'], 'delete_bill', 'ver_bills', $billId);
                    setFlashMessage('success', 'Bill deleted successfully.');
                } elseif ($action === 'toggle_status') {
                    $stmt = $db->prepare("SELECT status FROM ver_bills WHERE id = ?");
                    $stmt->execute([$billId]);
                    $current = $stmt->fetch();

                    if ($current) {
                        $newStatus = $current['status'] === 'active' ? 'inactive' : 'active';
                        $stmt = $db->prepare("UPDATE ver_bills SET status = ?, updated_by = ? WHERE id = ?");
                        $stmt->execute([$newStatus, $_SESSION['admin_id'], $billId]);
                        logAdminActivity($_SESSION['admin_id'], 'toggle_bill_status', 'ver_bills', $billId, ['status' => $current['status']], ['status' => $newStatus]);
                        setFlashMessage('success', 'Bill status updated.');
                    }
                }
            } catch (PDOException $e) {
                error_log("Bill action error: " . $e->getMessage());
                setFlashMessage('error', 'An error occurred. Please try again.');
            }
        }

        header('Location: manage-bills.php?' . http_build_query(['search' => $search, 'status' => $statusFilter, 'type' => $typeFilter, 'page' => $page]));
        exit;
    }
}

// Fetch bills
try {
    $db = getDB();

    // Build query
    $where = [];
    $params = [];

    if ($statusFilter && $statusFilter !== 'all') {
        $where[] = "b.status = ?";
        $params[] = $statusFilter;
    } else {
        $where[] = "b.status != 'deleted'";
    }

    if ($typeFilter) {
        $where[] = "b.bill_type = ?";
        $params[] = $typeFilter;
    }

    if ($search) {
        $where[] = "(b.bill_number LIKE ? OR b.vendor_name LIKE ? OR b.pan_number LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = implode(' AND ', $where);

    // Count total
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ver_bills b WHERE {$whereClause}");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // Fetch bills
    $stmt = $db->prepare("
        SELECT b.*, a.name as admin_name
        FROM ver_bills b
        LEFT JOIN admins a ON b.created_by = a.id
        WHERE {$whereClause}
        ORDER BY b.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $bills = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Bills fetch error: " . $e->getMessage());
    $bills = [];
    $total = 0;
    $totalPages = 0;
}

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="bg-white rounded-lg shadow">
    <!-- Header with Search -->
    <div class="p-6 border-b">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">All Bills</h3>
                <p class="text-sm text-gray-500">Total: <?php echo number_format($total); ?> bills</p>
            </div>

            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search bills..."
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">

                <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>

                <select name="type" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="">All Types</option>
                    <option value="general" <?php echo $typeFilter === 'general' ? 'selected' : ''; ?>>General</option>
                    <option value="vat" <?php echo $typeFilter === 'vat' ? 'selected' : ''; ?>>VAT Bill</option>
                    <option value="purchase" <?php echo $typeFilter === 'purchase' ? 'selected' : ''; ?>>Purchase</option>
                    <option value="sales" <?php echo $typeFilter === 'sales' ? 'selected' : ''; ?>>Sales</option>
                    <option value="expense" <?php echo $typeFilter === 'expense' ? 'selected' : ''; ?>>Expense</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-search mr-1"></i> Search
                </button>

                <a href="add-bill.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                    <i class="fas fa-plus mr-1"></i> Add New
                </a>
            </form>
        </div>
    </div>

    <!-- Bills Table -->
    <div class="overflow-x-auto">
        <?php if (empty($bills)): ?>
            <p class="text-gray-500 text-center py-12">No bills found</p>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Bill No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">PAN/Non-PAN</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date (BS)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($bills as $bill): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($bill['bill_number']); ?></div>
                                <?php if ($bill['vendor_name']): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars(truncateText($bill['vendor_name'], 25)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <span class="px-2 py-1 text-xs rounded-full bg-gray-100 text-gray-800">
                                    <?php echo ucfirst($bill['bill_type']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php if ($bill['is_non_pan']): ?>
                                    <span class="text-orange-600">Non-PAN</span>
                                <?php elseif ($bill['pan_number']): ?>
                                    <?php echo htmlspecialchars($bill['pan_number']); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600">
                                <?php
                                $amount = $bill['is_non_pan'] ? $bill['non_pan_amount'] : $bill['bill_amount'];
                                echo $amount ? 'Rs. ' . number_format($amount, 2) : '-';
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($bill['bill_date_bs']); ?></td>
                            <td class="px-6 py-4"><?php echo getStatusBadge($bill['status']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($bill['admin_name'] ?? 'Unknown'); ?>
                                <div class="text-xs text-gray-400"><?php echo formatDate($bill['created_at'], 'M d, Y'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="edit-bill.php?id=<?php echo $bill['id']; ?>"
                                       class="text-blue-600 hover:text-blue-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="../public/view-document.php?type=bill&id=<?php echo $bill['id']; ?>&admin=1"
                                       target="_blank"
                                       class="text-green-600 hover:text-green-800" title="View File">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <form method="POST" class="inline" onsubmit="return confirm('Change status?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-800" title="Toggle Status">
                                            <i class="fas fa-toggle-<?php echo $bill['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this bill?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="bill_id" value="<?php echo $bill['id']; ?>">
                                        <button type="submit" class="text-red-600 hover:text-red-800" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
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
            $baseUrl = 'manage-bills.php?' . http_build_query(['search' => $search, 'status' => $statusFilter, 'type' => $typeFilter]);
            echo generatePagination($page, $totalPages, $baseUrl);
            ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
