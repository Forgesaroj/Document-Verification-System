<?php
/**
 * Admin Header Template
 * Styled to match example.com website design
 */

// Prevent direct access
if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : ''; ?>Admin Panel | Company Verification</title>
    <?php if (file_exists(__DIR__ . '/../uploads/favicon.ico')): ?>
    <link rel="icon" type="image/x-icon" href="../uploads/favicon.ico?v=<?php echo filemtime(__DIR__ . '/../uploads/favicon.ico'); ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'primary-red': '#D72828',
                        'primary-red-dark': '#B82020',
                        'primary-blue': '#1E73BE',
                        'primary-dark': '#1A3647',
                        'primary-teal': '#008BB0',
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-link.active {
            background-color: rgba(215, 40, 40, 0.1);
            border-left: 3px solid #D72828;
            color: #D72828;
        }
        .sidebar-link:hover {
            background-color: rgba(215, 40, 40, 0.05);
        }
        .logo-hex {
            width: 40px;
            height: 40px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Top Red Header Bar - Matching Website -->
    <header class="bg-primary-red text-white shadow-lg">
        <div class="flex items-center justify-between px-4 py-3">
            <!-- Logo and Brand -->
            <div class="flex items-center space-x-3">
                <!-- Logo -->
                <div class="logo-hex flex-shrink-0">
                    <?php if (file_exists(__DIR__ . '/../uploads/logo.png')): ?>
                    <img src="../uploads/logo.png?v=<?php echo filemtime(__DIR__ . '/../uploads/logo.png'); ?>" alt="Logo" class="h-10 w-auto">
                    <?php else: ?>
                    <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                        <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                        <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                        <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                    </svg>
                    <?php endif; ?>
                </div>
                <div>
                    <h1 class="text-xl font-bold tracking-wide">
                        <span class="text-white">COMPANY</span>
                        <span class="text-primary-teal font-normal">PORTAL</span>
                    </h1>
                    <p class="text-xs text-red-200 tracking-wider">VERIFICATION PORTAL</p>
                </div>
            </div>

            <!-- Right Side - User Info & Actions -->
            <div class="flex items-center space-x-6">
                <!-- Website Link -->
                <a href="https://www.example.com" target="_blank"
                   class="hidden md:flex items-center text-sm text-red-100 hover:text-white transition">
                    <i class="fas fa-globe mr-2"></i>
                    <span>Visit Website</span>
                </a>

                <!-- User Info -->
                <div class="flex items-center space-x-3">
                    <div class="text-right hidden sm:block">
                        <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?></p>
                        <p class="text-xs text-red-200">Administrator</p>
                    </div>
                    <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="fas fa-user text-white"></i>
                    </div>
                </div>

                <!-- Logout -->
                <a href="logout.php"
                   class="flex items-center px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition text-sm">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>
    </header>

    <div class="flex" style="height: calc(100vh - 64px);">
        <!-- Sidebar -->
        <aside class="w-64 bg-white shadow-lg flex-shrink-0 overflow-y-auto">
            <!-- Quick Stats -->
            <div class="p-4 bg-gray-50 border-b">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Today's Date</span>
                    <span class="font-medium text-gray-700"><?php echo date('M d, Y'); ?></span>
                </div>
            </div>

            <nav class="py-4">
                <a href="dashboard.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt w-5 text-primary-red"></i>
                    <span class="ml-3">Dashboard</span>
                </a>

                <div class="px-4 py-2 mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Documents</p>
                </div>
                <a href="create-document.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'create-document' ? 'active' : ''; ?>">
                    <i class="fas fa-file-signature w-5 text-primary-blue"></i>
                    <span class="ml-3">Create Document</span>
                </a>
                <a href="add-document.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'add-document' ? 'active' : ''; ?>">
                    <i class="fas fa-cloud-upload-alt w-5 text-primary-blue"></i>
                    <span class="ml-3">Upload Document</span>
                </a>
                <a href="manage-documents.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'manage-documents' ? 'active' : ''; ?>">
                    <i class="fas fa-folder-open w-5 text-primary-blue"></i>
                    <span class="ml-3">Manage Documents</span>
                </a>

                <div class="px-4 py-2 mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Bills</p>
                </div>
                <a href="add-bill.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'add-bill' ? 'active' : ''; ?>">
                    <i class="fas fa-plus-circle w-5 text-green-600"></i>
                    <span class="ml-3">Add Bill</span>
                </a>
                <a href="import-bills.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'import-bills' ? 'active' : ''; ?>">
                    <i class="fas fa-file-excel w-5 text-green-600"></i>
                    <span class="ml-3">Import from Excel</span>
                </a>
                <a href="manage-bills.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'manage-bills' ? 'active' : ''; ?>">
                    <i class="fas fa-receipt w-5 text-green-600"></i>
                    <span class="ml-3">Manage Bills</span>
                </a>

                <div class="px-4 py-2 mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Reports</p>
                </div>
                <a href="verification-logs.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'verification-logs' ? 'active' : ''; ?>">
                    <i class="fas fa-history w-5 text-purple-600"></i>
                    <span class="ml-3">Verification Logs</span>
                </a>

                <div class="px-4 py-2 mt-4">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Settings</p>
                </div>
                <a href="branding.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'branding' ? 'active' : ''; ?>">
                    <i class="fas fa-palette w-5 text-primary-red"></i>
                    <span class="ml-3">Logo & Branding</span>
                </a>
                <a href="page-content.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'page-content' ? 'active' : ''; ?>">
                    <i class="fas fa-edit w-5 text-purple-600"></i>
                    <span class="ml-3">Page Content</span>
                </a>
                <a href="settings.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'settings' ? 'active' : ''; ?>">
                    <i class="fas fa-cog w-5 text-gray-500"></i>
                    <span class="ml-3">SMTP & OTP Settings</span>
                </a>
                <a href="manage-dates.php" class="sidebar-link flex items-center px-4 py-3 text-gray-700 <?php echo $currentPage === 'manage-dates' ? 'active' : ''; ?>">
                    <i class="fas fa-calendar-alt w-5 text-gray-500"></i>
                    <span class="ml-3">BS/AD Date Data</span>
                </a>
            </nav>

            <!-- Footer Links -->
            <div class="mt-auto p-4 border-t bg-gray-50">
                <div class="flex items-center justify-center space-x-4 text-gray-400">
                    <a href="https://www.facebook.com/yourpage" target="_blank" class="hover:text-primary-blue transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://wa.me/9779865005120" target="_blank" class="hover:text-green-500 transition">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:admin@example.com" class="hover:text-primary-red transition">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
                <p class="text-xs text-center text-gray-400 mt-2">© <?php echo date('Y'); ?> Company</p>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Page Title Bar -->
            <div class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800">
                        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?>
                    </h2>
                    <p class="text-sm text-gray-500 mt-1">
                        <a href="dashboard.php" class="hover:text-primary-red">Home</a>
                        <span class="mx-2">/</span>
                        <span><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Dashboard'; ?></span>
                    </p>
                </div>

                <!-- Quick Actions -->
                <div class="flex items-center space-x-3">
                    <a href="create-document.php" class="hidden md:flex items-center px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-primary-red-dark transition text-sm">
                        <i class="fas fa-plus mr-2"></i>
                        New Document
                    </a>
                </div>
            </div>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6 bg-gray-100">
                <?php echo displayFlashMessage(); ?>
