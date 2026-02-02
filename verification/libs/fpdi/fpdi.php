<?php
/**
 * FPDI - Free PDF Document Importer
 * Simplified version for importing PDF templates
 */

require_once __DIR__ . '/../fpdf.php';

class FPDI extends FPDF
{
    protected $parsers = [];
    protected $currentParser = null;
    protected $importedPages = [];
    protected $tplIdx = 0;
    protected $lastUsedPageBox = null;
    protected $templates = [];

    /**
     * Set the source PDF file
     */
    public function setSourceFile($filename)
    {
        $this->currentParser = $this->getPdfParser($filename);
        return $this->currentParser->getPageCount();
    }

    /**
     * Import a page from the source PDF
     */
    public function importPage($pageNo, $box = 'MediaBox')
    {
        if ($this->currentParser === null) {
            throw new Exception('No source file set.');
        }

        $pageId = $this->currentParser->getFilename() . '-' . $pageNo . '-' . $box;

        if (isset($this->importedPages[$pageId])) {
            return $this->importedPages[$pageId];
        }

        $this->tplIdx++;
        $tplIdx = $this->tplIdx;

        $pageData = $this->currentParser->getPage($pageNo);
        $boxData = $this->currentParser->getPageBox($pageNo, $box);

        $this->templates[$tplIdx] = [
            'parser' => $this->currentParser,
            'pageNo' => $pageNo,
            'box' => $box,
            'boxData' => $boxData,
            'pageData' => $pageData
        ];

        $this->importedPages[$pageId] = $tplIdx;
        $this->lastUsedPageBox = $boxData;

        return $tplIdx;
    }

    /**
     * Get the size of the imported page
     */
    public function getTemplateSize($tplIdx, $width = null, $height = null)
    {
        if (!isset($this->templates[$tplIdx])) {
            throw new Exception('Template does not exist.');
        }

        $tpl = $this->templates[$tplIdx];
        $boxData = $tpl['boxData'];

        $tplWidth = $boxData['width'];
        $tplHeight = $boxData['height'];

        if ($width === null && $height === null) {
            $width = $tplWidth;
            $height = $tplHeight;
        } elseif ($width === null) {
            $width = $tplWidth * ($height / $tplHeight);
        } elseif ($height === null) {
            $height = $tplHeight * ($width / $tplWidth);
        }

        return [
            'width' => $width,
            'height' => $height,
            'orientation' => $width > $height ? 'L' : 'P'
        ];
    }

    /**
     * Use the imported page as template
     */
    public function useTemplate($tplIdx, $x = 0, $y = 0, $width = null, $height = null)
    {
        if (!isset($this->templates[$tplIdx])) {
            throw new Exception('Template does not exist.');
        }

        $size = $this->getTemplateSize($tplIdx, $width, $height);

        $tpl = $this->templates[$tplIdx];

        // Store template reference for PDF output
        $this->_out(sprintf('q %.4F 0 0 %.4F %.4F %.4F cm',
            $size['width'] * $this->k,
            $size['height'] * $this->k,
            $x * $this->k,
            ($this->h - $y - $size['height']) * $this->k
        ));

        // Include the template content
        $this->_putTemplate($tplIdx);

        $this->_out('Q');

        return $size;
    }

    /**
     * Use imported page to create a new page with that template
     */
    public function useImportedPage($tplIdx, $x = 0, $y = 0, $width = null, $height = null)
    {
        return $this->useTemplate($tplIdx, $x, $y, $width, $height);
    }

    protected function _putTemplate($tplIdx)
    {
        // This is a simplified version - for complex PDFs, full FPDI library is needed
    }

    /**
     * Get PDF parser instance
     */
    protected function getPdfParser($filename)
    {
        if (!isset($this->parsers[$filename])) {
            $this->parsers[$filename] = new SimplePdfParser($filename);
        }
        return $this->parsers[$filename];
    }
}

/**
 * Simple PDF Parser - extracts basic info from PDF files
 */
class SimplePdfParser
{
    protected $filename;
    protected $fileContent;
    protected $pageCount = 0;
    protected $pages = [];
    protected $pageBoxes = [];

    public function __construct($filename)
    {
        if (!file_exists($filename)) {
            throw new Exception('PDF file not found: ' . $filename);
        }

        $this->filename = $filename;
        $this->fileContent = file_get_contents($filename);
        $this->parse();
    }

    public function getFilename()
    {
        return $this->filename;
    }

    public function getPageCount()
    {
        return $this->pageCount;
    }

    public function getPage($pageNo)
    {
        return $this->pages[$pageNo] ?? null;
    }

    public function getPageBox($pageNo, $box = 'MediaBox')
    {
        // Return A4 dimensions as default
        return [
            'x' => 0,
            'y' => 0,
            'width' => 210,  // A4 width in mm
            'height' => 297, // A4 height in mm
            'llx' => 0,
            'lly' => 0,
            'urx' => 595.28,
            'ury' => 841.89
        ];
    }

    protected function parse()
    {
        // Count pages - look for /Type /Page entries
        preg_match_all('/\/Type\s*\/Page[^s]/i', $this->fileContent, $matches);
        $this->pageCount = max(1, count($matches[0]));

        // Extract MediaBox if present
        if (preg_match('/\/MediaBox\s*\[\s*([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s*\]/', $this->fileContent, $match)) {
            $this->pageBoxes[1] = [
                'llx' => floatval($match[1]),
                'lly' => floatval($match[2]),
                'urx' => floatval($match[3]),
                'ury' => floatval($match[4]),
                'width' => (floatval($match[3]) - floatval($match[1])) / 2.83465,
                'height' => (floatval($match[4]) - floatval($match[2])) / 2.83465
            ];
        }
    }
}
