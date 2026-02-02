<?php
/**
 * Simple FPDF Test - Test basic PDF generation without CompanyPDF
 */

define('VERIFICATION_PORTAL', true);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../libs/fpdf.php';

// Create simple PDF
$pdf = new FPDF();
$pdf->AddPage();

// Simple content
$pdf->SetFont('Helvetica', 'B', 20);
$pdf->Cell(0, 20, 'COMPANY', 0, 1, 'C');

$pdf->SetFont('Helvetica', '', 12);
$pdf->Cell(0, 10, 'Test Document', 0, 1, 'C');

$pdf->Ln(10);

$pdf->SetFont('Helvetica', '', 11);
$pdf->MultiCell(0, 7, 'This is a simple test to verify that FPDF is generating PDF content correctly. If you can read this text, then basic PDF generation is working.');

$pdf->Ln(10);

$pdf->SetFont('Helvetica', 'B', 11);
$pdf->Cell(0, 7, 'Date: ' . date('Y-m-d H:i:s'), 0, 1);

// Output to browser
$pdf->Output('I', 'simple_test.pdf');
