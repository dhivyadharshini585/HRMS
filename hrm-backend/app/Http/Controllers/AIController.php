<?php

namespace App\Http\Controllers;

use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use PhpOffice\PhpWord\IOFactory;

class AIController extends Controller
{
    protected AIService $aiService;

    public function __construct(AIService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function screenResume(Request $request)
    {
        $request->validate([
            'candidate_id' => 'required|integer|exists:candidates,id',
        ]);

        $candidate = DB::table('candidates')->where('id', $request->candidate_id)->first();
        
        if (!$candidate || !$candidate->resume_path) {
            return response()->json(['error' => 'Candidate or resume not found.'], 404);
        }

        $jobOpening = DB::table('job_openings')->where('id', $candidate->job_opening_id)->first();
        if (!$jobOpening) {
            return response()->json(['error' => 'Job opening not found.'], 404);
        }

        $jobDescription = $jobOpening->title . "\n" . $jobOpening->description . "\n" . $jobOpening->requirements;

        $filePath = storage_path('app/' . $candidate->resume_path);
        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Resume file missing on server.'], 404);
        }

        $resumeText = '';
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        try {
            if ($ext === 'pdf') {
                $parser = new Parser();
                $pdf = $parser->parseFile($filePath);
                $resumeText = $pdf->getText();
            } elseif ($ext === 'docx') {
                $phpWord = IOFactory::load($filePath);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $resumeText .= $element->getText() . "\n";
                        }
                    }
                }
            } else {
                return response()->json(['error' => 'Unsupported resume format. Only PDF and DOCX are supported.'], 400);
            }
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to parse resume: ' . $e->getMessage()], 500);
        }

        if (empty(trim($resumeText))) {
            return response()->json(['error' => 'Resume appears to be empty or unreadable.'], 400);
        }

        try {
            $result = $this->aiService->screenResume($resumeText, $jobDescription);
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI Service failed to screen the resume: ' . $e->getMessage()], 502);
        }

        DB::table('candidates')->where('id', $candidate->id)->update([
            'ai_match_score' => $result['match_score'],
            'ai_extracted_skills' => $result['extracted_skills'],
            'ai_extracted_experience' => $result['extracted_experience'],
        ]);

        return response()->json([
            'message' => 'Resume screened successfully.',
            'data' => $result
        ]);
    }

    public function hrAssistant(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:1000',
        ]);

        $schema = "
        Table employees: id, first_name, last_name, email, department_id, designation_id, status (Active, Inactive), joining_date
        Table departments: id, name
        Table designations: id, name
        Table attendances: id, employee_id, date, status (Present, Absent, Leave)
        Table leave_balances: id, employee_id, leave_type_id, balance
        Table leave_types: id, name
        Table job_openings: id, title, department_id, status (Open, Closed), openings_count
        Table candidates: id, first_name, last_name, status (New, Hired, Rejected), job_opening_id, ai_match_score
        ";

        try {
            $sqlQuery = $this->aiService->generateHRQuery($request->question, $schema);
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI Service failed to generate query: ' . $e->getMessage()], 502);
        }

        // Security check - strip SQL comments first
        $cleanQuery = preg_replace('/\/\*.*?\*\/|--[^\r\n]*|#[^\r\n]*/s', '', $sqlQuery);

        if (!preg_match('/^\s*SELECT\b/i', $cleanQuery)) {
            return response()->json(['error' => 'Invalid query generated. Only SELECT queries are allowed.', 'query' => $sqlQuery], 403);
        }

        if (strpos($cleanQuery, ';') !== false) {
             return response()->json(['error' => 'Multiple statements detected.', 'query' => $sqlQuery], 403);
        }

        if (preg_match('/\b(INSERT|UPDATE|DELETE|DROP|ALTER|TRUNCATE|CREATE|GRANT|REVOKE|EXEC|EXECUTE|SLEEP|BENCHMARK|OUTFILE|DUMPFILE)\b/i', $cleanQuery)) {
            return response()->json(['error' => 'Destructive SQL keywords detected.', 'query' => $sqlQuery], 403);
        }

        if (preg_match('/\b(users|personal_access_tokens|password_resets|failed_jobs|migrations|sessions|oauth_\w+)\b/i', $cleanQuery)) {
            return response()->json(['error' => 'Access to sensitive system tables is strictly prohibited.', 'query' => $sqlQuery], 403);
        }

        // Dynamically append LIMIT 100 if not present to prevent massive result sets
        if (!preg_match('/\bLIMIT\s+\d+\b/i', $sqlQuery)) {
            $sqlQuery .= " LIMIT 100";
        }

        try {
            // Use the strict read-only database connection if configured, else default connection
            $connectionName = config('database.connections.ai-readonly') ? 'ai-readonly' : config('database.default');
            $results = DB::connection($connectionName)->select($sqlQuery);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to execute query.',
                'message' => $e->getMessage(),
                'query' => $sqlQuery
            ], 500);
        }

        if (empty($results)) {
            return response()->json([
                'answer' => "I couldn't find any data to answer that question.",
                'query' => $sqlQuery
            ]);
        }

        try {
            $answer = $this->aiService->answerFromResults($request->question, $results);
        } catch (\Exception $e) {
            return response()->json(['error' => 'AI Service failed to formulate answer: ' . $e->getMessage()], 502);
        }

        return response()->json([
            'answer' => $answer,
            'query' => $sqlQuery
        ]);
    }
}
