<?php
/**
 * Page Content Management
 * Edit homepage and other page content
 */

define('VERIFICATION_PORTAL', true);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';

// Require login
requireAdminLogin();

$pageTitle = 'Page Content';
$errors = [];
$success = '';

// Default content values
$defaultContent = [
    'hero_title' => 'Document & Bill Verification',
    'hero_subtitle' => 'Verify the authenticity of documents and bills issued by Company through our secure online verification system.',
    'hero_tagline' => 'MERGING 33 YEARS OF EXPERTISE WITH MODERN PRECISION',
    'company_name' => 'COMPANY',
    'company_name_suffix' => 'APPAREL',
    'company_tagline' => 'Merging 33 years of expertise',
    'contact_email' => 'admin@example.com',
    'contact_phone1' => '+977-9863618347',
    'contact_phone2' => '+977-9865005120',
    'contact_address' => 'Gaidakot-6, Nawalparasi(east)',
    'whatsapp_number' => '9779865005120',
    'facebook_url' => 'https://www.facebook.com/yourpage',
    'website_url' => 'https://www.example.com',
    'pan_number' => '124441767',
    'footer_text' => 'Document & Bill Verification Portal for authentic document verification.'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        try {
            $db = getDB();

            $fields = [
                'hero_title', 'hero_subtitle', 'hero_tagline',
                'company_name', 'company_name_suffix', 'company_tagline',
                'contact_email', 'contact_phone1', 'contact_phone2', 'contact_address',
                'whatsapp_number', 'facebook_url', 'website_url', 'pan_number', 'footer_text'
            ];

            foreach ($fields as $field) {
                $value = $_POST[$field] ?? $defaultContent[$field];
                $key = 'content_' . $field;

                $stmt = $db->prepare("
                    INSERT INTO ver_settings (setting_key, setting_value, setting_type, setting_group, description, updated_by, updated_at)
                    VALUES (?, ?, 'text', 'page_content', ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE
                        setting_value = VALUES(setting_value),
                        updated_by = VALUES(updated_by),
                        updated_at = NOW()
                ");
                $stmt->execute([$key, $value, ucwords(str_replace('_', ' ', $field)), $_SESSION['admin_id']]);
            }

            logAdminActivity($_SESSION['admin_id'], 'update_page_content', 'ver_settings', null, null, ['fields' => $fields]);
            $success = 'Page content updated successfully!';
        } catch (PDOException $e) {
            error_log("Content save error: " . $e->getMessage());
            $errors[] = 'Failed to save content: ' . $e->getMessage();
        }
    }
}

// Use function from functions.php (force refresh to get fresh data after save)
$content = getPageContent(true);
// Merge with defaults for any missing keys
foreach ($defaultContent as $key => $value) {
    if (!isset($content[$key])) {
        $content[$key] = $value;
    }
}

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

    <form method="POST" class="space-y-6">
        <?php echo csrfField(); ?>

        <!-- Hero Section -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-star text-primary-red mr-2"></i>Hero Section
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hero Title</label>
                    <input type="text" name="hero_title" value="<?php echo htmlspecialchars($content['hero_title']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hero Subtitle</label>
                    <textarea name="hero_subtitle" rows="2"
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($content['hero_subtitle']); ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Hero Tagline</label>
                    <input type="text" name="hero_tagline" value="<?php echo htmlspecialchars($content['hero_tagline']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Company Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-building text-primary-blue mr-2"></i>Company Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name</label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($content['company_name']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Name Suffix</label>
                    <input type="text" name="company_name_suffix" value="<?php echo htmlspecialchars($content['company_name_suffix']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Company Tagline</label>
                    <input type="text" name="company_tagline" value="<?php echo htmlspecialchars($content['company_tagline']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">PAN Number</label>
                    <input type="text" name="pan_number" value="<?php echo htmlspecialchars($content['pan_number']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>

            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Footer Description</label>
                <textarea name="footer_text" rows="2"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"><?php echo htmlspecialchars($content['footer_text']); ?></textarea>
            </div>
        </div>

        <!-- Contact Information -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-address-book text-green-600 mr-2"></i>Contact Information
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Address</label>
                    <input type="email" name="contact_email" value="<?php echo htmlspecialchars($content['contact_email']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number 1</label>
                    <input type="text" name="contact_phone1" value="<?php echo htmlspecialchars($content['contact_phone1']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number 2</label>
                    <input type="text" name="contact_phone2" value="<?php echo htmlspecialchars($content['contact_phone2']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">WhatsApp Number</label>
                    <input type="text" name="whatsapp_number" value="<?php echo htmlspecialchars($content['whatsapp_number']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g. 9779865005120">
                    <p class="text-xs text-gray-500 mt-1">Include country code without + sign</p>
                </div>

                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <input type="text" name="contact_address" value="<?php echo htmlspecialchars($content['contact_address']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Social Links -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-800 mb-4">
                <i class="fas fa-share-alt text-purple-600 mr-2"></i>Social & Website Links
            </h3>

            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Website URL</label>
                    <input type="url" name="website_url" value="<?php echo htmlspecialchars($content['website_url']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Facebook Page URL</label>
                    <input type="url" name="facebook_url" value="<?php echo htmlspecialchars($content['facebook_url']); ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="submit"
                    class="px-6 py-3 bg-primary-red text-white rounded-lg hover:bg-primary-red-dark focus:ring-4 focus:ring-red-300">
                <i class="fas fa-save mr-2"></i>Save All Changes
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
