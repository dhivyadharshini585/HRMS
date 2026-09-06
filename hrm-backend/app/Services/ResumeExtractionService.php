<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResumeExtractionService
{
    /**
     * Extract suggested candidate fields from an uploaded resume file.
     * Guaranteed to never throw and never interrupt the upload process.
     */
    public static function extract($file, ?string $originalName = null): array
    {
        try {
            $filePath = $file instanceof UploadedFile ? $file->getRealPath() : $file;
            $extension = strtolower(pathinfo($originalName ?? ($file instanceof UploadedFile ? $file->getClientOriginalName() : $filePath), PATHINFO_EXTENSION));

            $text = self::extractText($filePath, $extension);

            if (empty($text) || strlen(trim($text)) < 5) {
                return [
                    'status' => 'unavailable',
                    'fields' => (object) [],
                ];
            }

            $fields = self::extractFields($text);

            if (empty($fields)) {
                return [
                    'status' => 'unavailable',
                    'fields' => (object) [],
                ];
            }

            return [
                'status' => 'completed',
                'fields' => $fields,
            ];
        } catch (Throwable $e) {
            Log::warning('Resume extraction failed gracefully: ' . $e->getMessage());
            return [
                'status' => 'unavailable',
                'fields' => (object) [],
            ];
        }
    }

    /**
     * Extract text based on file format.
     */
    protected static function extractText(string $filePath, string $extension): string
    {
        if (!file_exists($filePath)) {
            return '';
        }

        if ($extension === 'pdf') {
            return self::extractPdfText($filePath);
        }

        if ($extension === 'docx') {
            return self::extractDocxText($filePath);
        }

        if ($extension === 'doc') {
            return self::extractDocText($filePath);
        }

        return '';
    }

    /**
     * Extract text from PDF file.
     */
    protected static function extractPdfText(string $filePath): string
    {
        try {
            if (class_exists(\Smalot\PdfParser\Parser::class)) {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($filePath);
                $text = $pdf->getText();
                if (!empty(trim($text))) {
                    return $text;
                }
            }
        } catch (Throwable $e) {
            Log::debug('PDF parser library exception, falling back to stream parsing: ' . $e->getMessage());
        }

        // Fallback: extract plain text / stream objects from PDF binary
        $content = @file_get_contents($filePath);
        if (!$content) {
            return '';
        }

        return self::extractPrintableText($content);
    }

    /**
     * Extract text from DOCX file.
     */
    protected static function extractDocxText(string $filePath): string
    {
        try {
            // DOCX is a zip archive containing word/document.xml
            $content = @file_get_contents($filePath);
            if (!$content) {
                return '';
            }

            // If word/document.xml is present in uncompressed form or XML chunks
            if (preg_match('/<w:body>(.*?)<\/w:body>/is', $content, $matches)) {
                return strip_tags($matches[1]);
            }

            // Fallback: search for <w:t> tags
            if (preg_match_all('/<w:t[^>]*>(.*?)<\/w:t>/is', $content, $matches)) {
                return implode(' ', $matches[1]);
            }

            return self::extractPrintableText($content);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Extract text from legacy DOC file.
     */
    protected static function extractDocText(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if (!$content) {
            return '';
        }

        return self::extractPrintableText($content);
    }

    /**
     * Extract printable ASCII and UTF-8 strings from binary stream.
     */
    protected static function extractPrintableText(string $binary): string
    {
        // Extract sequence of printable characters
        preg_match_all('/[\x20-\x7E]{4,}/', $binary, $matches);
        $extracted = implode(' ', $matches[0] ?? []);

        // Limit string size to prevent memory bloat (max 50,000 characters)
        return substr($extracted, 0, 50000);
    }

    /**
     * Extract basic candidate profile fields using regex heuristics.
     */
    protected static function extractFields(string $text): array
    {
        $fields = [];

        // 1. Email extraction
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $fields['email'] = strtolower(trim($matches[0]));
        }

        // 2. Phone extraction (international or standard phone formats)
        if (preg_match('/(?:\+?\d{1,3}[-.\s]?)?(?:\(?\d{3}\)?[-.\s]?)?\d{3}[-.\s]?\d{4}/', $text, $matches)) {
            $phone = trim($matches[0]);
            if (strlen(preg_replace('/\D/', '', $phone)) >= 7) {
                $fields['phone'] = $phone;
            }
        }

        // 3. Experience years extraction (e.g. "5 years experience", "4.5 yrs of exp")
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:\+)?\s*(?:years?|yrs?)(?:\s+of)?\s+(?:experience|exp|background)/i', $text, $matches)) {
            $years = (float) $matches[1];
            if ($years >= 0 && $years <= 50) {
                $fields['total_experience_years'] = $years;
            }
        }

        // 4. Highest qualification extraction
        if (preg_match('/\b(Ph\.?D|Master(?:[\'’]s)?(?:\s+of\s+[A-Za-z]+)?|M\.?S\.?|M\.?Tech|Bachelor(?:[\'’]s)?(?:\s+of\s+[A-Za-z]+)?|B\.?S\.?|B\.?Tech|MBA|Computer Science)\b/i', $text, $matches)) {
            $fields['highest_qualification'] = trim($matches[0]);
        }

        // 5. Common designation extraction
        if (preg_match('/\b((?:Senior|Lead|Principal|Junior|Staff|Associate)?\s*(?:Software Engineer|Backend Developer|Frontend Developer|Fullstack Developer|DevOps Engineer|QA Engineer|Data Scientist|Product Manager|System Architect|Cloud Engineer))\b/i', $text, $matches)) {
            $fields['current_designation'] = trim($matches[0]);
        }

        // 6. Current location extraction (City, State / Country)
        if (preg_match('/\b([A-Z][a-zA-Z\s]{2,20},\s*(?:[A-Z]{2}|USA|United States|UK|Canada|India))\b/', $text, $matches)) {
            $fields['current_location'] = trim($matches[0]);
        }

        // Sanitize all values
        foreach ($fields as $k => $v) {
            if (is_string($v)) {
                $fields[$k] = htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
            }
        }

        return $fields;
    }
}
