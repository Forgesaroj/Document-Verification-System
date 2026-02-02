<?php
/**
 * Create Document Page
 * Rich text editor with Microsoft-like features
 */

define('VERIFICATION_PORTAL', true);

// Enable error reporting for debugging
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/date_converter.php';
require_once __DIR__ . '/../includes/nepali_date_helper.php';
require_once __DIR__ . '/../includes/CompanyPDF.php';

// Require login
requireAdminLogin();

$pageTitle = 'Create Document';
$errors = [];
$success = '';
$documentId = null;

// Generate document number
function generateDocNumber() {
    $db = getDB();
    $year = date('Y');
    $prefix = "DOC-{$year}-";

    $stmt = $db->prepare("SELECT document_number FROM ver_documents WHERE document_number LIKE ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last = $stmt->fetch();

    if ($last) {
        $lastNum = (int)substr($last['document_number'], strlen($prefix));
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }

    return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
}

// Get current BS date
function getCurrentBSDate() {
    $adDate = date('Y-m-d');
    return convertAdToBs($adDate) ?: date('Y-m-d');
}

// Generate PDF document with letterhead
function generateDocumentPDF($docNumber, $subject, $content, $issuedBy, $issuedByPost, $issuedTo, $dateBs, $dateAd, $signatureName) {
    $pdf = new CompanyPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Format date for display
    $formattedDate = date('jS F Y', strtotime($dateAd));
    $formattedDateBs = $dateBs;

    // Add document header
    $pdf->addDocumentHeader($subject, '', $formattedDate, $docNumber);

    // Add "To Whom It May Concern" or recipient
    if ($issuedTo) {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(50, 50, 50);
        $pdf->Cell(0, 7, 'To: ' . $issuedTo, 0, 1);
        $pdf->Ln(3);
    }

    // Add main content
    $pdf->writeHTML($content);
    $pdf->Ln(5);

    // Add verification section
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->MultiCell(0, 5, 'For verification, you may refer to the following link:', 0, 'L');
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(0, 102, 153);
    $pdf->Cell(0, 5, 'https://verify.example.com', 0, 1);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->Ln(3);

    // Add disclaimer
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 5, 'This letter has been issued upon request and does not imply any legal obligation on the part of our company regarding its content.', 0, 'J');
    $pdf->Ln(3);

    // Add contact section
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->SetTextColor(50, 50, 50);
    $pdf->MultiCell(0, 5, 'Should you require any further information, please feel free to contact us at:', 0, 'L');
    $pdf->SetFont('Helvetica', '', 9);
    $pdf->Cell(0, 5, 'Phone: +977-78-590826 / +977-9865005120', 0, 1);
    $pdf->Cell(0, 5, 'Email: admin@example.com', 0, 1);
    $pdf->Ln(5);

    // Add signature section
    $sigName = $signatureName ?: $issuedBy;
    $pdf->addSignatureSection($sigName, $issuedByPost ?: 'Company', false);

    return $pdf;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create_document') {
            $documentNumber = sanitize($_POST['document_number'] ?? '');
            $subject = sanitize($_POST['subject'] ?? '');
            $content = $_POST['content'] ?? ''; // Don't sanitize HTML content
            $issuedBy = sanitize($_POST['issued_by'] ?? '');
            $issuedByPost = sanitize($_POST['issued_by_post'] ?? '');
            $issuedTo = sanitize($_POST['issued_to'] ?? '');
            $documentDateBs = sanitize($_POST['document_date_bs'] ?? '');
            $signatureName = sanitize($_POST['signature_name'] ?? '');
            $remarks = sanitize($_POST['remarks'] ?? '');

            // Validation
            if (empty($documentNumber)) {
                $errors[] = 'Document number is required.';
            }
            if (empty($subject)) {
                $errors[] = 'Subject is required.';
            }
            if (empty($content)) {
                $errors[] = 'Document content is required.';
            }
            if (empty($issuedBy)) {
                $errors[] = 'Issued by is required.';
            }
            if (empty($documentDateBs)) {
                $errors[] = 'Document date is required.';
            }

            // Convert date
            $documentDateAd = convertBsToAd($documentDateBs);
            if (!$documentDateAd) {
                $documentDateAd = date('Y-m-d');
            }

            // Check duplicate
            if (empty($errors)) {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM ver_documents WHERE document_number = ?");
                $stmt->execute([$documentNumber]);
                if ($stmt->fetch()) {
                    $errors[] = 'Document number already exists.';
                }
            }

            if (empty($errors)) {
                try {
                    $db = getDB();

                    // Create document data for storage
                    $documentData = [
                        'subject' => $subject,
                        'content' => $content,
                        'issued_by_post' => $issuedByPost,
                        'signature_name' => $signatureName,
                        'created_timestamp' => date('Y-m-d H:i:s'),
                        'verification_url' => 'https://verify.example.com'
                    ];

                    $uploadDir = __DIR__ . '/../uploads/documents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    // Generate PDF file with letterhead
                    try {
                        $pdf = generateDocumentPDF($documentNumber, $subject, $content, $issuedBy, $issuedByPost, $issuedTo, $documentDateBs, $documentDateAd, $signatureName);

                        $fileName = 'doc_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                        $pdf->Output($uploadDir . $fileName, 'F');
                    } catch (Exception $pdfError) {
                        error_log("PDF Generation Error: " . $pdfError->getMessage());
                        $errors[] = 'Failed to generate PDF: ' . $pdfError->getMessage();
                        throw $pdfError;
                    }

                    // Insert into database
                    $stmt = $db->prepare("
                        INSERT INTO ver_documents
                        (document_number, document_title, issued_by, issued_to, document_date_bs, document_date_ad, file_path, file_type, remarks, created_by, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'pdf', ?, ?, 'active')
                    ");

                    $issuedByFull = $issuedBy . ($issuedByPost ? ' | ' . $issuedByPost : '');

                    $stmt->execute([
                        $documentNumber,
                        $subject,
                        $issuedByFull,
                        $issuedTo ?: null,
                        $documentDateBs,
                        $documentDateAd,
                        $fileName,
                        $remarks ?: null,
                        $_SESSION['admin_id']
                    ]);

                    $documentId = $db->lastInsertId();

                    // Log activity
                    logAdminActivity($_SESSION['admin_id'], 'create_document', 'ver_documents', $documentId, null, $documentData);

                    $success = 'Document created successfully!';

                } catch (PDOException $e) {
                    error_log("Document create error: " . $e->getMessage());
                    $errors[] = 'Failed to create document. Please try again.';
                }
            }
        }
    }
}

function generateDocumentHTML($docNumber, $subject, $content, $issuedBy, $issuedByPost, $issuedTo, $dateBs, $dateAd, $signatureName) {
    $timestamp = date('Y-m-d H:i:s');
    $verificationUrl = 'https://verify.example.com';
    $formattedDate = date('jS F Y', strtotime($dateAd));

    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$subject} - {$docNumber}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #000;
            background: #fff;
            min-height: 297mm;
            width: 210mm;
            position: relative;
            margin: 0 auto;
        }

        /* Top Right Corner - Blue Ribbon */
        .corner-top-right {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 200px;
            overflow: hidden;
            z-index: 10;
        }
        .corner-top-right::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 80px 200px 0;
            border-color: transparent #1a3a5c transparent transparent;
        }
        .corner-top-right::after {
            content: '';
            position: absolute;
            top: 40px;
            right: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 0 50px 120px 0;
            border-color: transparent #0891b2 transparent transparent;
        }

        /* Bottom Left Corner - Blue Ribbon */
        .corner-bottom-left {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 200px;
            overflow: hidden;
            z-index: 10;
        }
        .corner-bottom-left::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 200px 0 0 80px;
            border-color: transparent transparent transparent #1a3a5c;
        }
        .corner-bottom-left::after {
            content: '';
            position: absolute;
            bottom: 40px;
            left: 0;
            width: 0;
            height: 0;
            border-style: solid;
            border-width: 120px 0 0 50px;
            border-color: transparent transparent transparent #0891b2;
        }

        .document-container {
            padding: 18mm 20mm 50mm 20mm;
            min-height: 297mm;
            position: relative;
        }

        /* Header */
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }
        .logo-area {
            width: 55px;
            height: 55px;
            margin-right: 12px;
            flex-shrink: 0;
        }
        .header-text {
            flex: 1;
        }
        .company-name {
            font-size: 20pt;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .company-name .company {
            color: #1a3a5c;
        }
        .company-name .apparel {
            color: #0891b2;
            font-weight: normal;
        }
        .company-tagline {
            font-size: 7pt;
            color: #555;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-top: 2px;
        }

        /* Date - Right aligned */
        .header-date {
            text-align: right;
            font-size: 10pt;
            margin-bottom: 15px;
        }

        /* Reference */
        .ref-number {
            font-size: 10pt;
            margin-bottom: 20px;
        }
        .ref-number strong {
            color: #000;
        }

        /* Subject - Centered */
        .subject-line {
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            margin: 25px 0;
            text-decoration: underline;
        }

        /* Content */
        .content {
            text-align: justify;
            font-size: 11pt;
            line-height: 1.7;
            margin-bottom: 20px;
        }
        .content p {
            margin-bottom: 15px;
        }

        /* Verification Link Section */
        .verification-section {
            margin: 20px 0;
            font-size: 10pt;
        }
        .verification-section p {
            margin-bottom: 5px;
        }
        .verification-link {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .verification-link .globe-icon {
            width: 16px;
            height: 16px;
        }
        .verification-link a {
            color: #0891b2;
            text-decoration: underline;
        }

        /* Disclaimer */
        .disclaimer {
            font-size: 10pt;
            margin: 20px 0;
            font-style: italic;
        }

        /* Contact Section */
        .contact-section {
            margin: 20px 0;
            font-size: 10pt;
        }
        .contact-section p {
            margin-bottom: 3px;
        }
        .contact-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 3px;
        }
        .contact-item .icon {
            font-weight: bold;
        }

        /* Signature Section */
        .signature-section {
            margin-top: 30px;
            display: flex;
            align-items: flex-start;
        }
        .signature-left {
            flex: 1;
        }
        .signature-left .label {
            margin-bottom: 40px;
        }
        .signature-left .name {
            font-weight: bold;
        }
        .signature-left .position {
            font-weight: bold;
        }
        .signature-left .company {
            font-weight: bold;
        }
        .stamp-area {
            width: 100px;
            height: 100px;
            margin-left: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Footer */
        .footer {
            position: absolute;
            bottom: 15mm;
            right: 20mm;
            text-align: right;
            font-size: 9pt;
            color: #333;
        }
        .footer-item {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            margin-bottom: 3px;
        }
        .footer-item .text {
            text-align: right;
            margin-right: 8px;
        }
        .footer-item .icon {
            width: 20px;
            height: 20px;
            background: #0891b2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .footer-item .icon svg {
            width: 11px;
            height: 11px;
            fill: #fff;
        }
        .pan-footer {
            margin-top: 5px;
            font-size: 8pt;
        }

        @media print {
            body {
                margin: 0;
                padding: 0;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .corner-top-right::before, .corner-top-right::after,
            .corner-bottom-left::before, .corner-bottom-left::after,
            .footer-item .icon {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media screen {
            body {
                box-shadow: 0 0 20px rgba(0,0,0,0.15);
                margin: 20px auto;
            }
        }
    </style>
</head>
<body>
    <!-- Blue corner decorations -->
    <div class="corner-top-right"></div>
    <div class="corner-bottom-left"></div>

    <div class="document-container">
        <!-- Header -->
        <div class="header">
            <div class="logo-area">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="50,2 95,25 95,75 50,98 5,75 5,25" fill="#1a3a5c" stroke="#0891b2" stroke-width="3"/>
                    <polygon points="50,15 80,32 80,68 50,85 20,68 20,32" fill="none" stroke="#0891b2" stroke-width="2"/>
                    <text x="38" y="55" fill="#fff" font-size="22" font-weight="bold" font-family="Arial">T</text>
                    <text x="52" y="65" fill="#0891b2" font-size="18" font-weight="bold" font-family="Arial">S</text>
                </svg>
            </div>
            <div class="header-text">
                <div class="company-name">
                    <span class="company">COMPANY</span> <span class="apparel">APPAREL</span>
                </div>
                <div class="company-tagline">MERGING 33 YEARS OF EXPERTISE WITH MODERN PRECISION.</div>
            </div>
        </div>

        <!-- Date -->
        <div class="header-date">
            <strong>Date:</strong> {$formattedDate}
        </div>

        <!-- Reference Number -->
        <div class="ref-number">
            <strong>Ref:</strong> {$docNumber}
        </div>

        <!-- Subject -->
        <div class="subject-line">{$subject}</div>

        <!-- Content -->
        <div class="content">
            {$content}
        </div>

        <!-- Verification Link -->
        <div class="verification-section">
            <p>For verification, you may refer to the following link:</p>
            <div class="verification-link">
                <svg class="globe-icon" viewBox="0 0 24 24" fill="#0891b2">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/>
                </svg>
                <a href="{$verificationUrl}/public/verify-document.php?ref={$docNumber}">{$verificationUrl}</a>
            </div>
        </div>

        <!-- Disclaimer -->
        <div class="disclaimer">
            <p>This letter has been issued upon request and does not imply any legal obligation on the part of our company regarding its content.</p>
        </div>

        <!-- Contact Section -->
        <div class="contact-section">
            <p>Should you require any further information, please feel free to contact us at:</p>
            <div class="contact-item">
                <span class="icon">&#9742;</span>
                <span>+977-78-590826</span>
            </div>
            <div class="contact-item">
                <span class="icon">&#9743;</span>
                <span>+977-9865005120</span>
            </div>
            <div class="contact-item">
                <span class="icon">&#9993;</span>
                <span>admin@example.com</span>
            </div>
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-left">
                <div class="label">Sincerely,</div>
                <div style="height: 50px;"></div>
                <div class="name">{$signatureName}</div>
                <div class="position">{$issuedByPost}</div>
                <div class="company">Company</div>
            </div>
            <div class="stamp-area">
                <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="50" cy="50" r="45" fill="none" stroke="#1a3a5c" stroke-width="2"/>
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#1a3a5c" stroke-width="1"/>
                    <text x="50" y="35" text-anchor="middle" fill="#1a3a5c" font-size="10" font-weight="bold">Company</text>
                    <text x="50" y="50" text-anchor="middle" fill="#1a3a5c" font-size="10" font-weight="bold">Apparel</text>
                    <text x="50" y="65" text-anchor="middle" fill="#0891b2" font-size="8">Since 2023</text>
                </svg>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <div class="footer-item">
            <div class="text">+977-9863618347<br>+977-9865005120</div>
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
            </div>
        </div>
        <div class="footer-item">
            <div class="text">www.example.com<br>admin@example.com</div>
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
            </div>
        </div>
        <div class="footer-item">
            <div class="text">Gaidakot-5, Nawalparasi(east)<br>Gandaki Province, Nepal</div>
            <div class="icon">
                <svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg>
            </div>
        </div>
        <div class="pan-footer">Pan No: 124441767</div>
    </div>
</body>
</html>
HTML;
}

$newDocNumber = generateDocNumber();
$currentBsDate = getCurrentBSDate();
$currentAdDate = date('Y-m-d');

include __DIR__ . '/../includes/admin_header.php';
?>

<!-- TinyMCE CDN -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>

<div class="max-w-5xl mx-auto">
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <ul class="list-disc list-inside">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success && $documentId): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fas fa-check-circle mr-2"></i><?php echo htmlspecialchars($success); ?>
            <div class="mt-2">
                <a href="preview-document.php?id=<?php echo $documentId; ?>" target="_blank"
                   class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 mr-2">
                    <i class="fas fa-eye mr-1"></i> Preview Document
                </a>
                <a href="create-document.php" class="inline-block px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    <i class="fas fa-plus mr-1"></i> Create Another
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-semibold text-gray-800 mb-6">
            <i class="fas fa-file-word text-blue-600 mr-2"></i>Create New Document
        </h3>

        <form method="POST" action="" class="space-y-6">
            <?php echo csrfField(); ?>
            <input type="hidden" name="action" value="create_document">

            <!-- Document Number -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Document Number <span class="text-red-500">*</span>
                </label>
                <input type="text" name="document_number"
                       value="<?php echo htmlspecialchars($newDocNumber); ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       required>
            </div>

            <!-- Date Field with Clickable Panel -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Document Date <span class="text-red-500">*</span>
                </label>
                <div id="create_document_datepicker" class="relative"></div>
            </div>

            <link rel="stylesheet" href="../assets/css/nepali-datepicker.css">
            <script src="../assets/js/nepali-datepicker.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const initialBsDate = '<?php echo htmlspecialchars($currentBsDate ?: ''); ?>';
                const apiUrl = 'api-dates.php';
                initNepaliDatePicker('create_document_datepicker', 'document_date_bs', 'document_date_ad', initialBsDate, apiUrl);
            });
            </script>

            <!-- Issued By -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Issued By (Name) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="issued_by"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., Mr. Ram Sharma" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Designation/Post
                    </label>
                    <input type="text" name="issued_by_post"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="e.g., General Manager">
                </div>
            </div>

            <!-- Issued To -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Issued To / Recipient
                </label>
                <input type="text" name="issued_to"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                       placeholder="e.g., Whom It May Concern / Mr. Hari Bahadur">
            </div>

            <!-- Subject -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Subject <span class="text-red-500">*</span>
                </label>

                <!-- Subject Type Selection -->
                <div class="flex items-center space-x-6 mb-3">
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="subject_type" value="preset" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" checked onchange="toggleSubjectFields()">
                        <span class="ml-2 text-sm text-gray-700 font-medium">Select from List</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer">
                        <input type="radio" name="subject_type" value="custom" class="w-4 h-4 text-blue-600 border-gray-300 focus:ring-blue-500" onchange="toggleSubjectFields()">
                        <span class="ml-2 text-sm text-gray-700 font-medium">Custom Subject</span>
                    </label>
                </div>

                <!-- Preset Subjects Dropdown -->
                <div id="preset_subject_container">
                    <select id="preset_subject" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="To Whom It May Concern">To Whom It May Concern</option>
                        <option value="Employment Verification Letter">Employment Verification Letter</option>
                        <option value="Salary Certificate">Salary Certificate</option>
                        <option value="Experience Certificate">Experience Certificate</option>
                        <option value="Character Certificate">Character Certificate</option>
                        <option value="Recommendation Letter">Recommendation Letter</option>
                        <option value="No Objection Certificate (NOC)">No Objection Certificate (NOC)</option>
                        <option value="Relieving Letter">Relieving Letter</option>
                        <option value="Internship Certificate">Internship Certificate</option>
                        <option value="Training Certificate">Training Certificate</option>
                        <option value="Appointment Letter">Appointment Letter</option>
                        <option value="Offer Letter">Offer Letter</option>
                        <option value="Warning Letter">Warning Letter</option>
                        <option value="Appreciation Letter">Appreciation Letter</option>
                        <option value="Authorization Letter">Authorization Letter</option>
                    </select>
                </div>

                <!-- Custom Subject Input -->
                <div id="custom_subject_container" class="hidden">
                    <input type="text" id="custom_subject"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                           placeholder="Enter your custom subject">
                </div>

                <!-- Hidden field for actual subject value -->
                <input type="hidden" name="subject" id="subject_value" required>
            </div>

            <script>
            function toggleSubjectFields() {
                const subjectType = document.querySelector('input[name="subject_type"]:checked').value;
                const presetContainer = document.getElementById('preset_subject_container');
                const customContainer = document.getElementById('custom_subject_container');
                const subjectValue = document.getElementById('subject_value');

                if (subjectType === 'preset') {
                    presetContainer.classList.remove('hidden');
                    customContainer.classList.add('hidden');
                    subjectValue.value = document.getElementById('preset_subject').value;
                } else {
                    presetContainer.classList.add('hidden');
                    customContainer.classList.remove('hidden');
                    subjectValue.value = document.getElementById('custom_subject').value;
                }
            }

            document.getElementById('preset_subject').addEventListener('change', function() {
                document.getElementById('subject_value').value = this.value;
            });

            document.getElementById('custom_subject').addEventListener('input', function() {
                document.getElementById('subject_value').value = this.value;
            });

            // Initialize
            document.addEventListener('DOMContentLoaded', function() {
                toggleSubjectFields();
            });
            </script>

            <!-- Content Editor -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Document Content <span class="text-red-500">*</span>
                </label>
                <textarea id="content-editor" name="content" rows="15"
                          class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>

            <!-- Signature -->
            <div class="bg-gray-50 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-gray-700 mb-3">
                    <i class="fas fa-signature mr-2"></i>Signature Details
                </h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Signatory Name
                        </label>
                        <input type="text" name="signature_name"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Name that appears below signature">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Remarks (Internal)
                        </label>
                        <input type="text" name="remarks"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                               placeholder="Internal notes (not shown on document)">
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                <h4 class="text-sm font-semibold text-blue-800 mb-2">
                    <i class="fas fa-info-circle mr-2"></i>Document Features
                </h4>
                <ul class="text-sm text-blue-700 space-y-1">
                    <li><i class="fas fa-check mr-2"></i>Professional PDF with company letterhead</li>
                    <li><i class="fas fa-check mr-2"></i>Auto-generated timestamp</li>
                    <li><i class="fas fa-check mr-2"></i>Verification footer with portal link</li>
                    <li><i class="fas fa-check mr-2"></i>Print-ready A4 layout</li>
                </ul>
            </div>

            <!-- Submit -->
            <div class="flex justify-end space-x-4">
                <a href="manage-documents.php"
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 focus:ring-4 focus:ring-blue-300">
                    <i class="fas fa-file-alt mr-2"></i>Create Document
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Initialize TinyMCE
tinymce.init({
    selector: '#content-editor',
    height: 400,
    menubar: true,
    plugins: [
        'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview',
        'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen',
        'insertdatetime', 'media', 'table', 'help', 'wordcount'
    ],
    toolbar: 'undo redo | blocks | ' +
        'bold italic underline strikethrough | alignleft aligncenter ' +
        'alignright alignjustify | bullist numlist outdent indent | ' +
        'table | removeformat | help',
    content_style: 'body { font-family: Times New Roman, Times, serif; font-size: 12pt; line-height: 1.6; }',
    block_formats: 'Paragraph=p; Heading 1=h1; Heading 2=h2; Heading 3=h3',
    setup: function(editor) {
        editor.on('change', function() {
            editor.save();
        });
    }
});

// Date picker is initialized in the form above using nepali-datepicker.js
</script>

<?php include __DIR__ . '/../includes/admin_footer.php'; ?>
