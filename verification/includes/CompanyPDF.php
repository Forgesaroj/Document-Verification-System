<?php
/**
 * Company PDF Generator
 * Creates PDF documents matching the exact company letterhead design
 */

if (!defined('VERIFICATION_PORTAL')) {
    die('Direct access not permitted');
}

// Only load FPDF if not already loaded
if (!class_exists('FPDF')) {
    require_once __DIR__ . '/../libs/fpdf.php';
}

class CompanyPDF extends FPDF
{
    // Company Information
    protected $companyName = 'COMPANY';
    protected $tagline = 'MERGING 33 YEARS OF EXPERTISE WITH MODERN PRECISION.';
    protected $panNumber = '124441767';
    protected $regNumber = '3383/079/080';
    protected $phone1 = '+977-9863618347';
    protected $phone2 = '+977-9865005120';
    protected $website = 'www.example.com';
    protected $email = 'admin@example.com';
    protected $address1 = 'Gaidakot-6, Nawalparasi(east)';
    protected $address2 = 'Gandaki Province, Nepal';

    // Colors (RGB) - matched to letterhead exactly
    protected $primaryColor = [0, 139, 176];    // Teal/Cyan #008BB0
    protected $darkColor = [26, 54, 71];        // Dark blue #1A3647
    protected $textColor = [51, 51, 51];        // Dark gray
    protected $lightGray = [100, 100, 100];     // Light gray for secondary text

    // Document info
    protected $documentTitle = '';
    protected $documentDate = '';
    protected $documentRef = '';

    // Logo path
    protected $logoPath = '';

    public function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        if (is_string($size)) {
            $size = strtoupper($size);
        }
        parent::__construct($orientation, $unit, $size);
        $this->SetAutoPageBreak(true, 65); // Leave space for footer
        $this->SetMargins(25, 50, 20);     // Left, Top, Right margins
        $this->logoPath = __DIR__ . '/../uploads/logo.png';
    }

    /**
     * Set document information
     */
    public function setDocumentInfo($title, $date = '', $ref = '')
    {
        $this->documentTitle = $title;
        $this->documentDate = $date;
        $this->documentRef = $ref;
    }

    /**
     * Header - Called automatically on each page
     */
    public function Header()
    {
        // Draw corner designs first (behind content)
        $this->drawTopRightCorner();
        $this->drawTopLeftBar();

        // Company Logo area (left side)
        $this->SetXY(18, 15);

        // Draw hexagonal logo
        $this->drawHexagonLogo(18, 15, 22);

        // Registration number (small, above company name)
        $this->SetXY(42, 15);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 4, 'Reg:' . $this->regNumber, 0, 1);

        // Company Name - COMPANY in dark, PORTAL in teal
        $this->SetXY(42, 19);
        $this->SetFont('Helvetica', 'B', 20);
        $this->SetTextColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $companyWidth = $this->GetStringWidth('COMPANY ');
        $this->Cell($companyWidth, 8, 'COMPANY ', 0, 0);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetFont('Helvetica', '', 20);
        $this->Cell(0, 8, 'APPAREL', 0, 1);

        // Tagline - with letter spacing effect
        $this->SetXY(42, 28);
        $this->SetFont('Helvetica', '', 6);
        $this->SetTextColor($this->lightGray[0], $this->lightGray[1], $this->lightGray[2]);
        $this->Cell(0, 4, $this->tagline, 0, 1);

        // PAN Number
        $this->SetXY(42, 33);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 4, 'Pan : ' . $this->panNumber, 0, 1);

        // Reset position for content
        $this->SetXY(25, 50);
    }

    /**
     * Footer - Called automatically on each page
     */
    public function Footer()
    {
        // Draw corner designs
        $this->drawBottomLeftCorner();
        $this->drawBottomRightBar();

        // Footer content - right aligned
        $this->SetY(-58);

        $rightEdge = 188;
        $iconX = $rightEdge + 3;

        // Phone numbers
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(50, 50, 50);

        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->phone1, 0, 0, 'R');
        $this->drawCircleIcon($iconX, $this->GetY() + 2.5, 'phone');
        $this->Ln(5);

        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->phone2, 0, 1, 'R');
        $this->Ln(2);

        // Website and email
        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->website, 0, 0, 'R');
        $this->drawCircleIcon($iconX, $this->GetY() + 2.5, 'globe');
        $this->Ln(5);

        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->email, 0, 1, 'R');
        $this->Ln(2);

        // Address
        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->address1, 0, 0, 'R');
        $this->drawCircleIcon($iconX, $this->GetY() + 2.5, 'location');
        $this->Ln(5);

        $this->SetX(20);
        $this->Cell($rightEdge - 20, 5, $this->address2, 0, 1, 'R');
    }

    /**
     * Draw hexagonal logo with T inside
     */
    protected function drawHexagonLogo($x, $y, $size)
    {
        $cx = $x + $size / 2;
        $cy = $y + $size / 2;
        $r = $size / 2;

        // Outer hexagon - dark blue filled
        $this->SetFillColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(0.8);

        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(60 * $i - 90);
            $points[] = $cx + $r * cos($angle);
            $points[] = $cy + $r * sin($angle);
        }
        $this->Polygon($points, 'DF');

        // Inner hexagon - teal outline only
        $this->SetDrawColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->SetLineWidth(0.5);
        $r2 = $r * 0.7;
        $points2 = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(60 * $i - 90);
            $points2[] = $cx + $r2 * cos($angle);
            $points2[] = $cy + $r2 * sin($angle);
        }
        $this->Polygon($points2, 'D');

        // Draw "T" letter in white
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetXY($cx - 3, $cy - 4);
        $this->Cell(6, 8, 'T', 0, 0, 'C');

        // Reset text color
        $this->SetTextColor(0, 0, 0);
    }

    /**
     * Draw a polygon
     */
    protected function Polygon($points, $style = 'D')
    {
        $op = 'S';
        if ($style == 'F') $op = 'f';
        elseif ($style == 'DF' || $style == 'FD') $op = 'B';

        $h = $this->h;
        $k = $this->k;

        $s = '';
        for ($i = 0; $i < count($points); $i += 2) {
            $x = $points[$i] * $k;
            $y = ($h - $points[$i + 1]) * $k;
            if ($i == 0) {
                $s .= sprintf('%.2F %.2F m ', $x, $y);
            } else {
                $s .= sprintf('%.2F %.2F l ', $x, $y);
            }
        }
        $s .= 'h ' . $op;
        $this->_put($s);
    }

    /**
     * Draw top right corner triangles (ribbon effect)
     */
    protected function drawTopRightCorner()
    {
        $w = $this->GetPageWidth();

        // Large dark blue triangle
        $this->SetFillColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $points1 = [
            $w - 50, 0,    // Top start
            $w, 0,         // Top right corner
            $w, 70,        // Down the right side
        ];
        $this->Polygon($points1, 'F');

        // Teal/cyan triangle overlay (smaller)
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $points2 = [
            $w - 28, 0,    // Top start (further right)
            $w, 0,         // Top right corner
            $w, 40,        // Shorter down
        ];
        $this->Polygon($points2, 'F');

        // Add ribbon fold effect - small dark triangle
        $this->SetFillColor($this->darkColor[0] - 10, $this->darkColor[1] - 10, $this->darkColor[2] - 10);
        $points3 = [
            $w - 50, 0,
            $w - 45, 5,
            $w - 50, 5,
        ];
        $this->Polygon($points3, 'F');
    }

    /**
     * Draw top left vertical bar
     */
    protected function drawTopLeftBar()
    {
        $this->SetFillColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $this->Rect(0, 0, 5, 45, 'F');
    }

    /**
     * Draw bottom left corner triangles (ribbon effect)
     */
    protected function drawBottomLeftCorner()
    {
        $h = $this->GetPageHeight();

        // Large dark blue triangle
        $this->SetFillColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $points1 = [
            0, $h - 80,    // Up the left side
            0, $h,         // Bottom left corner
            60, $h,        // Along the bottom
        ];
        $this->Polygon($points1, 'F');

        // Teal/cyan triangle overlay (smaller)
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $points2 = [
            0, $h - 45,    // Higher up
            0, $h,         // Bottom left corner
            35, $h,        // Shorter along bottom
        ];
        $this->Polygon($points2, 'F');

        // Add ribbon fold effect - small dark triangle on right edge
        $this->SetFillColor($this->darkColor[0] - 10, $this->darkColor[1] - 10, $this->darkColor[2] - 10);
        $points3 = [
            60, $h,
            55, $h - 5,
            60, $h - 5,
        ];
        $this->Polygon($points3, 'F');
    }

    /**
     * Draw bottom right vertical bar
     */
    protected function drawBottomRightBar()
    {
        $w = $this->GetPageWidth();
        $h = $this->GetPageHeight();

        $this->SetFillColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $this->Rect($w - 5, $h - 60, 5, 60, 'F');
    }

    /**
     * Draw circular icon for footer
     */
    protected function drawCircleIcon($x, $y, $type)
    {
        // Draw teal circle
        $this->SetFillColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $r = 4;
        $this->Circle($x, $y, $r, 'F');

        // Icon is implied by position - actual icons would need image support
    }

    /**
     * Draw a circle
     */
    protected function Circle($x, $y, $r, $style = 'D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    /**
     * Draw an ellipse
     */
    protected function Ellipse($x, $y, $rx, $ry, $style = 'D')
    {
        $op = 'S';
        if ($style == 'F') $op = 'f';
        elseif ($style == 'DF' || $style == 'FD') $op = 'B';

        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;
        $k = $this->k;
        $h = $this->h;

        $this->_put(sprintf('%.2F %.2F m', ($x + $rx) * $k, ($h - $y) * $k));
        $this->_put(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x + $lx) * $k, ($h - ($y - $ry)) * $k,
            $x * $k, ($h - ($y - $ry)) * $k));
        $this->_put(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $k, ($h - ($y - $ry)) * $k,
            ($x - $rx) * $k, ($h - ($y - $ly)) * $k,
            ($x - $rx) * $k, ($h - $y) * $k));
        $this->_put(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x - $lx) * $k, ($h - ($y + $ry)) * $k,
            $x * $k, ($h - ($y + $ry)) * $k));
        $this->_put(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $lx) * $k, ($h - ($y + $ry)) * $k,
            ($x + $rx) * $k, ($h - ($y + $ly)) * $k,
            ($x + $rx) * $k, ($h - $y) * $k));
        $this->_put($op);
    }

    /**
     * Add document title section
     */
    public function addDocumentHeader($title, $subtitle = '', $date = '', $refNo = '')
    {
        $this->Ln(5);

        // Date and Reference on same line
        if ($date || $refNo) {
            $this->SetFont('Helvetica', '', 10);
            $this->SetTextColor(50, 50, 50);

            if ($date) {
                $this->Cell(80, 6, 'Date: ' . $date, 0, 0, 'L');
            }
            if ($refNo) {
                $this->Cell(80, 6, 'Ref: ' . $refNo, 0, 0, 'R');
            }
            $this->Ln(12);
        }

        // Title - centered and underlined
        $this->SetFont('Helvetica', 'B', 14);
        $this->SetTextColor($this->darkColor[0], $this->darkColor[1], $this->darkColor[2]);
        $this->Cell(0, 8, $title, 0, 1, 'C');

        // Underline
        $this->SetDrawColor(100, 100, 100);
        $this->SetLineWidth(0.3);
        $titleWidth = $this->GetStringWidth($title);
        $startX = ($this->GetPageWidth() - $titleWidth) / 2;
        $this->Line($startX, $this->GetY(), $startX + $titleWidth, $this->GetY());

        $this->Ln(10);
    }

    /**
     * Add a section with heading
     */
    public function addSection($heading, $content)
    {
        // Heading
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor($this->primaryColor[0], $this->primaryColor[1], $this->primaryColor[2]);
        $this->Cell(0, 7, $heading, 0, 1);

        // Content
        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(50, 50, 50);
        $this->MultiCell(0, 6, $content);
        $this->Ln(3);
    }

    /**
     * Add paragraph
     */
    public function addParagraph($text, $fontSize = 11, $align = 'J')
    {
        $this->SetFont('Helvetica', '', $fontSize);
        $this->SetTextColor(50, 50, 50);
        $this->MultiCell(0, 6, $text, 0, $align);
        $this->Ln(4);
    }

    /**
     * Add signature section
     */
    public function addSignatureSection($name, $designation, $showDate = true)
    {
        $this->Ln(15);

        // "Sincerely," text
        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 6, 'Sincerely,', 0, 1);

        $this->Ln(18); // Space for signature

        // Signature line
        $this->SetDrawColor(100, 100, 100);
        $this->SetLineWidth(0.3);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 55, $this->GetY());

        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 11);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(0, 6, $name, 0, 1);

        $this->SetFont('Helvetica', '', 10);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(0, 5, $designation, 0, 1);
        $this->Cell(0, 5, 'Company', 0, 1);
    }

    /**
     * Add two-column signature section
     */
    public function addDualSignature($leftName, $leftDesignation, $rightName, $rightDesignation)
    {
        $this->Ln(15);

        $y = $this->GetY();

        // Left signature
        $this->SetXY(25, $y);
        $this->SetDrawColor(100, 100, 100);
        $this->Line(25, $y, 75, $y);
        $this->Ln(2);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(60, 5, $leftName, 0, 1);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(60, 5, $leftDesignation, 0, 1);

        // Right signature
        $this->SetXY(130, $y);
        $this->Line(130, $y, 180, $y);
        $this->SetXY(130, $y + 2);
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(50, 50, 50);
        $this->Cell(60, 5, $rightName, 0, 1);
        $this->SetXY(130, $y + 7);
        $this->SetFont('Helvetica', '', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(60, 5, $rightDesignation, 0, 1);
    }

    /**
     * Write HTML-like content (basic support)
     */
    public function writeHTML($html)
    {
        // Strip HTML tags but preserve line breaks
        $text = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $html));
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = trim($text);

        $this->SetFont('Helvetica', '', 11);
        $this->SetTextColor(50, 50, 50);
        $this->MultiCell(0, 6, $text, 0, 'J');
    }
}

/**
 * Helper function to create a document PDF
 */
function createDocumentPDF($title, $content, $date = '', $refNo = '', $issuedBy = '', $designation = '')
{
    $pdf = new CompanyPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Add document header
    $pdf->addDocumentHeader($title, '', $date, $refNo);

    // Add content
    if (is_array($content)) {
        foreach ($content as $section) {
            if (isset($section['heading'])) {
                $pdf->addSection($section['heading'], $section['content'] ?? '');
            } else {
                $pdf->addParagraph($section['content'] ?? $section);
            }
        }
    } else {
        $pdf->writeHTML($content);
    }

    // Add signature if provided
    if ($issuedBy) {
        $pdf->addSignatureSection($issuedBy, $designation);
    }

    return $pdf;
}
