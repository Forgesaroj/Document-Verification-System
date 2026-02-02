<?php
/**
 * Manage Documents Page
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Manage Documents';

// Pagination settings
$perPage = 15;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

// Search and filter
$search = sanitize($_GET['search'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? 'active');

// Handle delete/status change
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('error', 'Invalid request. Please try again.');
    } else {
        $action = $_POST['action'] ?? '';
        $docId = (int)($_POST['doc_id'] ?? 0);

        if ($docId > 0) {
            try {
                $db = getDB();

                if ($action === 'delete') {
                    // Soft delete
                    $stmt = $db->prepare("UPDATE ver_documents SET status = 'deleted', updated_by = ? WHERE id = ?");
                    $stmt->execute([$_SESSION['admin_id'], $docId]);
                    logAdminActivity($_SESSION['admin_id'], 'delete_document', 'ver_documents', $docId);
                    setFlashMessage('success', 'Document deleted successfully.');
                } elseif ($action === 'toggle_status') {
                    // Get current status
                    $stmt = $db->prepare("SELECT status FROM ver_documents WHERE id = ?");
                    $stmt->execute([$docId]);
                    $current = $stmt->fetch();

                    if ($current) {
                        $newStatus = $current['status'] === 'active' ? 'inactive' : 'active';
                        $stmt = $db->prepare("UPDATE ver_documents SET status = ?, updated_by = ? WHERE id = ?");
                        $stmt->execute([$newStatus, $_SESSION['admin_id'], $docId]);
                        logAdminActivity($_SESSION['admin_id'], 'toggle_document_status', 'ver_documents', $docId, ['status' => $current['status']], ['status' => $newStatus]);
                        setFlashMessage('success', 'Document status updated.');
                    }
                }
            } catch (PDOException $e) {
                error_log("Document action error: " . $e->getMessage());
                setFlashMessage('error', 'An error occurred. Please try again.');
            }
        }

        header('Location: manage-documents.php?' . http_build_query(['search' => $search, 'status' => $statusFilter, 'page' => $page]));
        exit;
    }
}

// Fetch documents
try {
    $db = getDB();

    // Build query
    $where = [];
    $params = [];

    if ($statusFilter && $statusFilter !== 'all') {
        $where[] = "d.status = ?";
        $params[] = $statusFilter;
    } else {
        $where[] = "d.status != 'deleted'";
    }

    if ($search) {
        $where[] = "(d.document_number LIKE ? OR d.issued_by LIKE ? OR d.issued_to LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    $whereClause = implode(' AND ', $where);

    // Count total
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM ver_documents d WHERE {$whereClause}");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    $totalPages = ceil($total / $perPage);

    // Fetch documents
    $stmt = $db->prepare("
        SELECT d.*, a.name as admin_name
        FROM ver_documents d
        LEFT JOIN admins a ON d.created_by = a.id
        WHERE {$whereClause}
        ORDER BY d.created_at DESC
        LIMIT {$perPage} OFFSET {$offset}
    ");
    $stmt->execute($params);
    $documents = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Documents fetch error: " . $e->getMessage());
    $documents = [];
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
                <h3 class="text-lg font-semibold text-gray-800">All Documents</h3>
                <p class="text-sm text-gray-500">Total: <?php echo number_format($total); ?> documents</p>
            </div>

            <form method="GET" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                       placeholder="Search documents..."
                       class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">

                <select name="status" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                </select>

                <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    <i class="fas fa-search mr-1"></i> Search
                </button>

                <a href="add-document.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-center">
                    <i class="fas fa-plus mr-1"></i> Add New
                </a>
            </form>
        </div>
    </div>

    <!-- Documents Table -->
    <div class="overflow-x-auto">
        <?php if (empty($documents)): ?>
            <p class="text-gray-500 text-center py-12">No documents found</p>
        <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Document No.</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Issued By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date (BS)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date (AD)</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Created By</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($documents as $doc): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="font-medium text-gray-800"><?php echo htmlspecialchars($doc['document_number']); ?></div>
                                <?php if ($doc['document_title']): ?>
                                    <div class="text-xs text-gray-500"><?php echo htmlspecialchars(truncateText($doc['document_title'], 30)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($doc['issued_by']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo htmlspecialchars($doc['document_date_bs']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?php echo formatDate($doc['document_date_ad']); ?></td>
                            <td class="px-6 py-4"><?php echo getStatusBadge($doc['status']); ?></td>
                            <td class="px-6 py-4 text-sm text-gray-500">
                                <?php echo htmlspecialchars($doc['admin_name'] ?? 'Unknown'); ?>
                                <div class="text-xs text-gray-400"><?php echo formatDate($doc['created_at'], 'M d, Y'); ?></div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex items-center space-x-2">
                                    <a href="edit-document.php?id=<?php echo $doc['id']; ?>"
                                       class="text-blue-600 hover:text-blue-800" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>

                                    <a href="../public/view-document.php?type=document&id=<?php echo $doc['id']; ?>&admin=1"
                                       target="_blank"
                                       class="text-green-600 hover:text-green-800" title="View File">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <form method="POST" class="inline" onsubmit="return confirm('Change status?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                        <button type="submit" class="text-yellow-600 hover:text-yellow-800" title="Toggle Status">
                                            <i class="fas fa-toggle-<?php echo $doc['status'] === 'active' ? 'on' : 'off'; ?>"></i>
                                        </button>
                                    </form>

                                    <form method="POST" class="inline" onsubmit="return confirmDelete('Delete this document?');">
                                        <?php echo csrfField(); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
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
            $baseUrl = 'manage-documents.php?' . http_build_query(['search' => $search, 'status' => $statusFilter]);
            echo generatePagination($page, $totalPages, $baseUrl);
            ?>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
