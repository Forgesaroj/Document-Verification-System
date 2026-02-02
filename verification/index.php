<?php
/**
 * Verification Portal - Landing Page
 * Company
 */

define('VERIFICATION_PORTAL', true);

// Check if Bills module is enabled and load page content
$billsEnabled = true;
$pageContent = [];
try {
    require_once __DIR__ . '/config/db.php';
    require_once __DIR__ . '/includes/functions.php';
    $billsEnabled = isBillsModuleEnabled();
    $pageContent = getPageContent();
} catch (Exception $e) {
    // If database is unavailable, use defaults
    $billsEnabled = true;
    $pageContent = [
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
}

// Adjust content based on bills module status
$heroTitle = $billsEnabled ? $pageContent['hero_title'] : str_replace(' & Bill', '', $pageContent['hero_title']);
$heroSubtitle = $billsEnabled ? $pageContent['hero_subtitle'] : str_replace('documents and bills', 'documents', $pageContent['hero_subtitle']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($heroTitle); ?> | <?php echo htmlspecialchars($pageContent['company_name'] . ' ' . $pageContent['company_name_suffix']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($heroSubtitle); ?>">
    <?php if (file_exists(__DIR__ . '/uploads/favicon.ico')): ?>
    <link rel="icon" type="image/x-icon" href="uploads/favicon.ico?v=<?php echo filemtime(__DIR__ . '/uploads/favicon.ico'); ?>">
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
        .hero-pattern {
            background-color: #D72828;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='100' height='100' viewBox='0 0 100 100'%3E%3Cg fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.05'%3E%3Cpath opacity='.5' d='M96 95h4v1h-4v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4h-9v4h-1v-4H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15v-9H0v-1h15V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h9V0h1v15h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9h4v1h-4v9zm-1 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm9-10v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-10 0v-9h-9v9h9zm-9-10h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9zm10 0h9v-9h-9v9z'/%3E%3Cpath d='M6 5V0H5v5H0v1h5v94h1V6h94V5H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }
        .verification-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .verification-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- Header - Matching Website Design -->
    <header class="bg-primary-red text-white shadow-lg">
        <div class="max-w-6xl mx-auto px-4 py-3">
            <div class="flex items-center justify-between">
                <!-- Logo and Brand -->
                <a href="<?php echo htmlspecialchars($pageContent['website_url']); ?>" class="flex items-center space-x-3">
                    <!-- Logo -->
                    <div class="w-12 h-12 flex-shrink-0">
                        <?php if (file_exists(__DIR__ . '/uploads/logo.png')): ?>
                        <img src="uploads/logo.png?v=<?php echo filemtime(__DIR__ . '/uploads/logo.png'); ?>" alt="Logo" class="h-12 w-auto">
                        <?php else: ?>
                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                            <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                            <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                            <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                        </svg>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold tracking-wide">
                            <span class="text-white"><?php echo htmlspecialchars($pageContent['company_name']); ?></span>
                            <span class="text-primary-teal font-normal"><?php echo htmlspecialchars($pageContent['company_name_suffix']); ?></span>
                        </h1>
                        <p class="text-xs text-red-200 tracking-wider">VERIFICATION PORTAL</p>
                    </div>
                </a>

                <!-- Navigation -->
                <nav class="hidden md:flex items-center space-x-6">
                    <a href="<?php echo htmlspecialchars($pageContent['website_url']); ?>" class="text-red-100 hover:text-white transition text-sm">
                        <i class="fas fa-globe mr-1"></i> Website
                    </a>
                    <a href="public/verify-document.php" class="text-red-100 hover:text-white transition text-sm">
                        <i class="fas fa-file-alt mr-1"></i> Verify Document
                    </a>
                    <?php if ($billsEnabled): ?>
                    <a href="public/verify-bill.php" class="text-red-100 hover:text-white transition text-sm">
                        <i class="fas fa-receipt mr-1"></i> Verify Bill
                    </a>
                    <?php endif; ?>
                    <a href="admin/login.php" class="flex items-center px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition text-sm">
                        <i class="fas fa-lock mr-2"></i> Admin
                    </a>
                </nav>

                <!-- Mobile Menu Button -->
                <button class="md:hidden text-white" onclick="document.getElementById('mobileMenu').classList.toggle('hidden')">
                    <i class="fas fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div id="mobileMenu" class="hidden md:hidden mt-4 pb-2 border-t border-red-400 pt-4">
                <div class="flex flex-col space-y-2">
                    <a href="<?php echo htmlspecialchars($pageContent['website_url']); ?>" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-globe mr-2"></i> Website
                    </a>
                    <a href="public/verify-document.php" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-file-alt mr-2"></i> Verify Document
                    </a>
                    <?php if ($billsEnabled): ?>
                    <a href="public/verify-bill.php" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-receipt mr-2"></i> Verify Bill
                    </a>
                    <?php endif; ?>
                    <a href="admin/login.php" class="text-red-100 hover:text-white py-2">
                        <i class="fas fa-lock mr-2"></i> Admin Login
                    </a>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero-pattern text-white py-20">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <div class="w-20 h-20 mx-auto mb-6">
                <?php if (file_exists(__DIR__ . '/uploads/logo.png')): ?>
                <img src="uploads/logo.png?v=<?php echo filemtime(__DIR__ . '/uploads/logo.png'); ?>" alt="Logo" class="h-20 w-auto mx-auto">
                <?php else: ?>
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                    <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                    <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                </svg>
                <?php endif; ?>
            </div>
            <h2 class="text-4xl md:text-5xl font-bold mb-4"><?php echo htmlspecialchars($heroTitle); ?></h2>
            <p class="text-xl text-red-100 max-w-2xl mx-auto">
                <?php echo htmlspecialchars($heroSubtitle); ?>
            </p>
            <p class="text-sm text-red-200 mt-4 tracking-wider">
                <?php echo htmlspecialchars($pageContent['hero_tagline']); ?>
            </p>
        </div>
    </section>

    <!-- Verification Options -->
    <section class="max-w-6xl mx-auto px-4 py-16 -mt-16">
        <div class="grid <?php echo $billsEnabled ? 'md:grid-cols-2' : 'md:grid-cols-1 max-w-xl mx-auto'; ?> gap-8">
            <!-- Document Verification Card -->
            <a href="public/verify-document.php" class="verification-card block">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-primary-blue to-blue-600 p-6">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4">
                            <i class="fas fa-file-alt text-white text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Verify Document</h3>
                        <p class="text-blue-100 mt-2">
                            Verify certificates, letters, and other official documents
                        </p>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Enter document number
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Verify via email OTP
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                View secure document copy
                            </li>
                        </ul>
                        <div class="mt-6 flex items-center text-primary-blue font-medium">
                            <span>Verify Now</span>
                            <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </div>
                </div>
            </a>

            <?php if ($billsEnabled): ?>
            <!-- Bill Verification Card -->
            <a href="public/verify-bill.php" class="verification-card block">
                <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
                    <div class="bg-gradient-to-r from-green-500 to-green-600 p-6">
                        <div class="w-16 h-16 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mb-4">
                            <i class="fas fa-receipt text-white text-3xl"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-white">Verify Bill</h3>
                        <p class="text-green-100 mt-2">
                            Verify invoices, purchase bills, and payment receipts
                        </p>
                    </div>
                    <div class="p-6">
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Enter bill number
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                Verify via email OTP
                            </li>
                            <li class="flex items-center">
                                <i class="fas fa-check-circle text-green-500 mr-3"></i>
                                View secure bill copy
                            </li>
                        </ul>
                        <div class="mt-6 flex items-center text-green-600 font-medium">
                            <span>Verify Now</span>
                            <i class="fas fa-arrow-right ml-2"></i>
                        </div>
                    </div>
                </div>
            </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Features Section -->
    <section class="bg-white py-16">
        <div class="max-w-6xl mx-auto px-4">
            <h3 class="text-2xl font-bold text-gray-800 text-center mb-12">
                Secure Verification System
            </h3>
            <div class="grid md:grid-cols-4 gap-8">
                <div class="text-center">
                    <div class="w-14 h-14 bg-primary-red/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-lock text-primary-red text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Secure</h4>
                    <p class="text-sm text-gray-500">OTP verification ensures only authorized access</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-bolt text-green-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Fast</h4>
                    <p class="text-sm text-gray-500">Instant verification in just a few clicks</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-primary-teal/10 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-shield-alt text-primary-teal text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Protected</h4>
                    <p class="text-sm text-gray-500">View-only mode prevents unauthorized copying</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-history text-orange-600 text-xl"></i>
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Audited</h4>
                    <p class="text-sm text-gray-500">All verifications are logged for security</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-16 bg-gray-50">
        <div class="max-w-6xl mx-auto px-4">
            <h3 class="text-2xl font-bold text-gray-800 text-center mb-12">
                How It Works
            </h3>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="relative text-center">
                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center text-white font-bold text-xl mx-auto mb-4">
                        1
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Enter Number</h4>
                    <p class="text-gray-500">
                        Enter the document or bill number you want to verify.
                        The number is usually printed on the document.
                    </p>
                </div>
                <div class="relative text-center">
                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center text-white font-bold text-xl mx-auto mb-4">
                        2
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">Verify Email</h4>
                    <p class="text-gray-500">
                        Provide your email address to receive a one-time
                        verification code (OTP) for security.
                    </p>
                </div>
                <div class="relative text-center">
                    <div class="w-12 h-12 bg-primary-red rounded-full flex items-center justify-center text-white font-bold text-xl mx-auto mb-4">
                        3
                    </div>
                    <h4 class="font-semibold text-gray-800 mb-2">View Document</h4>
                    <p class="text-gray-500">
                        After verification, view the authentic document
                        in our secure, view-only document viewer.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="bg-white py-16">
        <div class="max-w-6xl mx-auto px-4 text-center">
            <h3 class="text-2xl font-bold text-gray-800 mb-4">Need Help?</h3>
            <p class="text-gray-600 mb-6">
                If you have any questions about document verification or need assistance,
                please contact our support team.
            </p>
            <div class="flex flex-wrap justify-center gap-6">
                <a href="mailto:<?php echo htmlspecialchars($pageContent['contact_email']); ?>" class="flex items-center text-primary-red hover:text-primary-red-dark">
                    <i class="fas fa-envelope mr-2"></i><?php echo htmlspecialchars($pageContent['contact_email']); ?>
                </a>
                <span class="text-gray-300 hidden md:inline">|</span>
                <a href="tel:<?php echo htmlspecialchars($pageContent['contact_phone2']); ?>" class="flex items-center text-primary-red hover:text-primary-red-dark">
                    <i class="fas fa-phone mr-2"></i><?php echo htmlspecialchars($pageContent['contact_phone2']); ?>
                </a>
                <span class="text-gray-300 hidden md:inline">|</span>
                <a href="https://wa.me/<?php echo htmlspecialchars($pageContent['whatsapp_number']); ?>" class="flex items-center text-green-600 hover:text-green-700">
                    <i class="fab fa-whatsapp mr-2"></i>WhatsApp
                </a>
            </div>
        </div>
    </section>

    <!-- Footer - Matching Website Design -->
    <footer class="bg-primary-dark text-white">
        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Company Info -->
                <div>
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10">
                            <?php if (file_exists(__DIR__ . '/uploads/logo.png')): ?>
                            <img src="uploads/logo.png?v=<?php echo filemtime(__DIR__ . '/uploads/logo.png'); ?>" alt="Logo" class="h-10 w-auto">
                            <?php else: ?>
                            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                <polygon points="50,5 93,27 93,73 50,95 7,73 7,27" fill="#1A3647" stroke="#008BB0" stroke-width="3"/>
                                <polygon points="50,18 78,35 78,65 50,82 22,65 22,35" fill="none" stroke="#008BB0" stroke-width="2"/>
                                <text x="50" y="58" fill="#fff" font-size="28" font-weight="bold" font-family="Arial" text-anchor="middle">T</text>
                            </svg>
                            <?php endif; ?>
                        </div>
                        <div>
                            <h3 class="font-bold"><?php echo htmlspecialchars($pageContent['company_name']); ?> <span class="text-primary-teal font-normal"><?php echo htmlspecialchars($pageContent['company_name_suffix']); ?></span></h3>
                            <p class="text-xs text-gray-400"><?php echo htmlspecialchars($pageContent['company_tagline']); ?></p>
                        </div>
                    </div>
                    <p class="text-gray-400 text-sm"><?php echo htmlspecialchars($billsEnabled ? $pageContent['footer_text'] : str_replace('& Bill', '', $pageContent['footer_text'])); ?></p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="font-semibold mb-4 text-primary-teal">Quick Links</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="public/verify-document.php" class="hover:text-white transition"><i class="fas fa-file-alt mr-2"></i>Verify Document</a></li>
                        <?php if ($billsEnabled): ?>
                        <li><a href="public/verify-bill.php" class="hover:text-white transition"><i class="fas fa-receipt mr-2"></i>Verify Bill</a></li>
                        <?php endif; ?>
                        <li><a href="<?php echo htmlspecialchars($pageContent['website_url']); ?>" class="hover:text-white transition"><i class="fas fa-globe mr-2"></i>Main Website</a></li>
                        <li><a href="admin/login.php" class="hover:text-white transition"><i class="fas fa-lock mr-2"></i>Admin Portal</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div>
                    <h4 class="font-semibold mb-4 text-primary-teal">Contact Us</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><i class="fas fa-phone mr-2 text-primary-teal"></i><?php echo htmlspecialchars($pageContent['contact_phone1']); ?></li>
                        <li><i class="fas fa-phone mr-2 text-primary-teal"></i><?php echo htmlspecialchars($pageContent['contact_phone2']); ?></li>
                        <li><i class="fas fa-envelope mr-2 text-primary-teal"></i><?php echo htmlspecialchars($pageContent['contact_email']); ?></li>
                        <li><i class="fas fa-map-marker-alt mr-2 text-primary-teal"></i><?php echo htmlspecialchars($pageContent['contact_address']); ?></li>
                    </ul>
                </div>
            </div>

            <!-- Social & Copyright -->
            <div class="border-t border-gray-700 mt-8 pt-6 flex flex-col md:flex-row items-center justify-between">
                <div class="flex space-x-4 mb-4 md:mb-0">
                    <a href="<?php echo htmlspecialchars($pageContent['facebook_url']); ?>" target="_blank" class="w-10 h-10 bg-primary-blue rounded-full flex items-center justify-center hover:bg-blue-600 transition">
                        <i class="fab fa-facebook-f"></i>
                    </a>
                    <a href="https://wa.me/<?php echo htmlspecialchars($pageContent['whatsapp_number']); ?>" target="_blank" class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center hover:bg-green-600 transition">
                        <i class="fab fa-whatsapp"></i>
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($pageContent['contact_email']); ?>" class="w-10 h-10 bg-primary-red rounded-full flex items-center justify-center hover:bg-red-700 transition">
                        <i class="fas fa-envelope"></i>
                    </a>
                </div>
                <p class="text-gray-500 text-sm">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($pageContent['company_name'] . ' ' . $pageContent['company_name_suffix']); ?>. All rights reserved. | PAN: <?php echo htmlspecialchars($pageContent['pan_number']); ?>
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
