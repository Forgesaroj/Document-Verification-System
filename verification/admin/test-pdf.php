<?php
/**
 * Test PDF Generation
 * This file tests if the PDF generation works correctly
 */

define('VERIFICATION_PORTAL', true);

// Enable full error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing PDF Generation</h1>";

// Step 1: Check FPDF library
echo "<h2>Step 1: Loading FPDF Library</h2>";
try {
    require_once __DIR__ . '/../libs/fpdf.php';
    echo "<p style='color:green;'>FPDF library loaded successfully.</p>";
} catch (Exception $e) {
    die("<p style='color:red;'>Error loading FPDF: " . $e->getMessage() . "</p>");
}

// Step 2: Check CompanyPDF class
echo "<h2>Step 2: Loading CompanyPDF Class</h2>";
try {
    require_once __DIR__ . '/../includes/CompanyPDF.php';
    echo "<p style='color:green;'>CompanyPDF class loaded successfully.</p>";
} catch (Exception $e) {
    die("<p style='color:red;'>Error loading CompanyPDF: " . $e->getMessage() . "</p>");
}

// Step 3: Create a simple PDF
echo "<h2>Step 3: Creating Simple PDF</h2>";
try {
    $pdf = new CompanyPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    echo "<p style='color:green;'>PDF object created and page added.</p>";

    // Add document content matching letterhead style
    $pdf->addDocumentHeader('Employment Verification Letter', '', date('jS F Y'), 'DOC-2026-0001');
    echo "<p style='color:green;'>Document header added.</p>";

    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(0, 7, 'To Whom It May Concern', 0, 1);
    $pdf->Ln(5);

    $content = "This is to certify that Mr. John Doe has been employed with Company since January 2020. During his tenure, he has demonstrated exceptional skills and dedication to his work.\n\nHe currently holds the position of Senior Developer and has been an invaluable member of our team. His responsibilities include managing projects, mentoring junior staff, and ensuring quality deliverables.\n\nWe are pleased to confirm his employment status and recommend him for any future endeavors.";

    $pdf->addParagraph($content);
    echo "<p style='color:green;'>Content added.</p>";

    // Verification notice
    $pdf->Ln(5);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(0, 5, 'For verification, please visit: https://verify.example.com', 0, 1);

    $pdf->Ln(3);
    $pdf->SetFont('Helvetica', 'I', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->MultiCell(0, 5, 'This letter has been issued upon request and does not imply any legal obligation on the part of our company.');

    $pdf->addSignatureSection('Ram Sharma', 'General Manager', false);
    echo "<p style='color:green;'>Signature section added.</p>";

} catch (Exception $e) {
    die("<p style='color:red;'>Error creating PDF: " . $e->getMessage() . "</p>");
}

// Step 4: Save PDF to file
echo "<h2>Step 4: Saving PDF to File</h2>";
try {
    $uploadDir = __DIR__ . '/../uploads/documents/';
    $fileName = 'test_pdf_' . time() . '.pdf';
    $fullPath = $uploadDir . $fileName;

    echo "<p>Attempting to save to: " . htmlspecialchars($fullPath) . "</p>";

    $pdf->Output($fullPath, 'F');

    if (file_exists($fullPath)) {
        $fileSize = filesize($fullPath);
        echo "<p style='color:green;'>PDF saved successfully! File size: " . $fileSize . " bytes</p>";
        echo "<p><a href='serve-test-pdf.php?file=" . htmlspecialchars($fileName) . "' target='_blank' style='color:blue;'>Click here to view the PDF</a></p>";
        echo "<p><a href='serve-test-pdf.php?file=" . htmlspecialchars($fileName) . "&download=1' style='color:green;'>Click here to download the PDF</a></p>";
    } else {
        echo "<p style='color:red;'>PDF file was not created.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>Error saving PDF: " . $e->getMessage() . "</p>";
}

echo "<h2>Test Complete</h2>";
echo "<p><a href='create-document.php'>Go to Create Document page</a></p>";
