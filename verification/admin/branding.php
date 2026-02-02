<?php
/**
 * Branding Settings Page
 * Upload and manage company logo and favicon
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Branding Settings';
$errors = [];
$success = '';

// Define upload paths
$uploadDir = __DIR__ . '/../uploads/';
$logoPath = $uploadDir . 'logo.png';
$faviconPath = $uploadDir . 'favicon.ico';

// Create uploads directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!@mkdir($uploadDir, 0755, true)) {
        $errors[] = 'Unable to create uploads directory.';
    }
}

// Check if uploads directory is writable
if (is_dir($uploadDir) && !is_writable($uploadDir)) {
    $errors[] = 'Uploads directory is not writable. Please check folder permissions.';
}

// Allowed file types
$allowedLogoTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$allowedFaviconTypes = ['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/gif'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // Upload Logo
        if ($action === 'upload_logo') {
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['logo'];
                $fileType = mime_content_type($file['tmp_name']);

                if (!in_array($fileType, $allowedLogoTypes)) {
                    $errors[] = 'Invalid logo file type. Please upload PNG, JPG, GIF, or WEBP.';
                } elseif ($file['size'] > 2 * 1024 * 1024) { // 2MB max
                    $errors[] = 'Logo file is too large. Maximum size is 2MB.';
                } else {
                    // Check image dimensions
                    $imageInfo = @getimagesize($file['tmp_name']);
                    if ($imageInfo === false) {
                        $errors[] = 'Invalid image file.';
                    } else {
                        // Check if GD library is available
                        if (extension_loaded('gd')) {
                            // Convert to PNG for consistency
                            $image = null;
                            switch ($fileType) {
                                case 'image/jpeg':
                                    $image = @imagecreatefromjpeg($file['tmp_name']);
                                    break;
                                case 'image/png':
                                    $image = @imagecreatefrompng($file['tmp_name']);
                                    break;
                                case 'image/gif':
                                    $image = @imagecreatefromgif($file['tmp_name']);
                                    break;
                                case 'image/webp':
                                    if (function_exists('imagecreatefromwebp')) {
                                        $image = @imagecreatefromwebp($file['tmp_name']);
                                    }
                                    break;
                            }

                            if ($image) {
                                // Preserve transparency for PNG
                                imagesavealpha($image, true);

                                // Save as PNG
                                if (@imagepng($image, $logoPath)) {
                                    imagedestroy($image);
                                    $success = 'Logo uploaded successfully!';
                                    logAdminActivity($_SESSION['admin_id'], 'upload_logo', 'branding', null, null, ['file' => 'logo.png']);
                                } else {
                                    $errors[] = 'Failed to save logo file. Check folder permissions.';
                                }
                            } else {
                                $errors[] = 'Failed to process image file.';
                            }
                        } else {
                            // GD not available - just copy the file directly
                            // For PNG files, copy directly; for others, just save with .png extension
                            if (move_uploaded_file($file['tmp_name'], $logoPath)) {
                                $success = 'Logo uploaded successfully!';
                                logAdminActivity($_SESSION['admin_id'], 'upload_logo', 'branding', null, null, ['file' => 'logo.png']);
                            } else {
                                $errors[] = 'Failed to save logo file. Check folder permissions.';
                            }
                        }
                    }
                }
            } else {
                $errors[] = 'Please select a logo file to upload.';
            }
        }

        // Upload Favicon
        if ($action === 'upload_favicon') {
            if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['favicon'];
                $fileType = mime_content_type($file['tmp_name']);
                $fileName = strtolower($file['name']);

                // Check if it's an ICO file by extension (mime detection is unreliable for ICO)
                $isIco = pathinfo($fileName, PATHINFO_EXTENSION) === 'ico';

                if (!$isIco && !in_array($fileType, $allowedFaviconTypes)) {
                    $errors[] = 'Invalid favicon file type. Please upload ICO, PNG, or GIF.';
                } elseif ($file['size'] > 500 * 1024) { // 500KB max
                    $errors[] = 'Favicon file is too large. Maximum size is 500KB.';
                } else {
                    // For ICO files, just copy directly
                    if ($isIco || $fileType === 'image/x-icon' || $fileType === 'image/vnd.microsoft.icon') {
                        if (move_uploaded_file($file['tmp_name'], $faviconPath)) {
                            $success = 'Favicon uploaded successfully!';
                            logAdminActivity($_SESSION['admin_id'], 'upload_favicon', 'branding', null, null, ['file' => 'favicon.ico']);
                        } else {
                            $errors[] = 'Failed to save favicon file.';
                        }
                    } else {
                        // For PNG/GIF, just copy with .ico extension
                        if (move_uploaded_file($file['tmp_name'], $faviconPath)) {
                            $success = 'Favicon uploaded successfully!';
                            logAdminActivity($_SESSION['admin_id'], 'upload_favicon', 'branding', null, null, ['file' => 'favicon.ico']);
                        } else {
                            $errors[] = 'Failed to save favicon file.';
                        }
                    }
                }
            } else {
                $errors[] = 'Please select a favicon file to upload.';
            }
        }

        // Delete Logo
        if ($action === 'delete_logo') {
            if (file_exists($logoPath)) {
                if (unlink($logoPath)) {
                    $success = 'Logo deleted successfully.';
                    logAdminActivity($_SESSION['admin_id'], 'delete_logo', 'branding', null, null, []);
                } else {
                    $errors[] = 'Failed to delete logo.';
                }
            }
        }

        // Delete Favicon
        if ($action === 'delete_favicon') {
            if (file_exists($faviconPath)) {
                if (unlink($faviconPath)) {
                    $success = 'Favicon deleted successfully.';
                    logAdminActivity($_SESSION['admin_id'], 'delete_favicon', 'branding', null, null, []);
                } else {
                    $errors[] = 'Failed to delete favicon.';
                }
            }
        }
    }
}

// Check current files
$hasLogo = file_exists($logoPath);
$hasFavicon = file_exists($faviconPath);

include __DIR__ . '/../includes/admin_header.php';
?>

<div class="max-w-4xl mx-auto">
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Company Logo -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-image text-primary-blue mr-2"></i>Company Logo
            </h3>

            <div class="mb-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center bg-gray-50" style="min-height: 150px;">
                    <?php if ($hasLogo): ?>
                        <img src="../uploads/logo.png?v=<?php echo time(); ?>" alt="Company Logo"
                             class="max-h-32 mx-auto mb-2" style="max-width: 200px;">
                        <p class="text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>Logo uploaded</p>
                    <?php else: ?>
                        <div class="text-gray-400 py-8">
                            <i class="fas fa-image text-4xl mb-2"></i>
                            <p>No logo uploaded</p>
                            <p class="text-xs">Default hexagonal logo will be used</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="upload_logo">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Upload New Logo
                    </label>
                    <input type="file" name="logo" accept=".png,.jpg,.jpeg,.gif,.webp"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">PNG, JPG, GIF, or WEBP. Max 2MB. Recommended: 200x200px</p>
                </div>

                <div class="flex space-x-2">
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-primary-blue text-white rounded-lg hover:bg-blue-700 text-sm">
                        <i class="fas fa-upload mr-1"></i> Upload Logo
                    </button>
                    <?php if ($hasLogo): ?>
                        <button type="submit" name="action" value="delete_logo"
                                onclick="return confirm('Are you sure you want to delete the logo?')"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Favicon -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-star text-primary-red mr-2"></i>Favicon
            </h3>

            <div class="mb-4">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center bg-gray-50" style="min-height: 150px;">
                    <?php if ($hasFavicon): ?>
                        <div class="flex items-center justify-center py-4">
                            <div class="bg-white border rounded p-4 shadow-sm">
                                <img src="../uploads/favicon.ico?v=<?php echo time(); ?>" alt="Favicon"
                                     class="mx-auto" style="width: 32px; height: 32px;">
                            </div>
                            <div class="bg-gray-100 border rounded p-2 ml-4">
                                <img src="../uploads/favicon.ico?v=<?php echo time(); ?>" alt="Favicon"
                                     class="mx-auto" style="width: 16px; height: 16px;">
                            </div>
                        </div>
                        <p class="text-sm text-green-600"><i class="fas fa-check-circle mr-1"></i>Favicon uploaded</p>
                    <?php else: ?>
                        <div class="text-gray-400 py-8">
                            <i class="fas fa-star text-4xl mb-2"></i>
                            <p>No favicon uploaded</p>
                            <p class="text-xs">Browser default will be used</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="upload_favicon">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Upload New Favicon
                    </label>
                    <input type="file" name="favicon" accept=".ico,.png,.gif"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">ICO, PNG, or GIF. Max 500KB. Recommended: 32x32px or 16x16px</p>
                </div>

                <div class="flex space-x-2">
                    <button type="submit"
                            class="flex-1 px-4 py-2 bg-primary-red text-white rounded-lg hover:bg-red-700 text-sm">
                        <i class="fas fa-upload mr-1"></i> Upload Favicon
                    </button>
                    <?php if ($hasFavicon): ?>
                        <button type="submit" name="action" value="delete_favicon"
                                onclick="return confirm('Are you sure you want to delete the favicon?')"
                                class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm">
                            <i class="fas fa-trash"></i>
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Preview Section -->
    <div class="bg-white rounded-lg shadow p-6 mt-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
            <i class="fas fa-eye text-purple-600 mr-2"></i>Preview
        </h3>

        <p class="text-sm text-gray-600 mb-4">See how your branding appears on the portal:</p>

        <!-- Header Preview -->
        <div class="border rounded-lg overflow-hidden">
            <div class="bg-primary-red text-white px-4 py-3">
                <div class="flex items-center space-x-3">
                    <?php if ($hasLogo): ?>
                        <img src="../uploads/logo.png?v=<?php echo time(); ?>" alt="Logo" class="h-10 w-auto">
                    <?php else: ?>
                        <div class="w-10 h-10">
                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                                <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                                <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                            </svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h1 class="text-lg font-bold">
                            <span class="text-white">COMPANY</span>
                            <span class="text-primary-teal font-normal">APPAREL</span>
                        </h1>
                        <p class="text-xs text-red-200">VERIFICATION PORTAL</p>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-gray-100 text-center text-sm text-gray-500">
                Header Preview
            </div>
        </div>

        <!-- Browser Tab Preview -->
        <div class="mt-4">
            <p class="text-sm text-gray-600 mb-2">Browser Tab Preview:</p>
            <div class="inline-flex items-center bg-gray-200 rounded-t px-3 py-2 text-sm">
                <?php if ($hasFavicon): ?>
                    <img src="../uploads/favicon.ico?v=<?php echo time(); ?>" alt="Favicon" class="w-4 h-4 mr-2">
                <?php else: ?>
                    <i class="fas fa-globe text-gray-400 mr-2"></i>
                <?php endif; ?>
                <span class="text-gray-700">Company | Verification Portal</span>
                <i class="fas fa-times text-gray-400 ml-4"></i>
            </div>
        </div>
    </div>

    <!-- Help Section -->
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">
        <h4 class="text-sm font-semibold text-blue-800 mb-2">
            <i class="fas fa-info-circle mr-2"></i>Tips for Best Results
        </h4>
        <ul class="text-sm text-blue-700 space-y-1">
            <li><i class="fas fa-check mr-2"></i><strong>Logo:</strong> Use a square image with transparent background (PNG). Recommended size: 200x200px</li>
            <li><i class="fas fa-check mr-2"></i><strong>Favicon:</strong> Use .ICO format for best browser compatibility. Size: 32x32px or 16x16px</li>
            <li><i class="fas fa-check mr-2"></i>Clear your browser cache after uploading to see changes immediately</li>
            <li><i class="fas fa-check mr-2"></i>The logo is used in the header, PDF documents, and footer</li>
        </ul>
    </div>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
