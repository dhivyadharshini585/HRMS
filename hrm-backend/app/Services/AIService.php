<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected string $apiKey;
    protected string $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-pro:generateContent';

    public function __construct()
    {
        $this->apiKey = env('GEMINI_API_KEY', '');
    }

    /**
     * Call the Gemini API directly using Laravel's Http client.
     */
    protected function callGemini(string $prompt): string
    {
        if (empty($this->apiKey)) {
            Log::warning("Gemini API key is not set.");
            return "";
        }

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->post($this->apiUrl . '?key=' . $this->apiKey, [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1, // Keep it deterministic
            ]
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        }

        Log::error("Gemini API Error: " . $response->body());
        return "";
    }

    /**
     * Returns match score + extracted skills/experience
     */
    public function screenResume(string $resumeText, string $jobDescription): array
    {
        $prompt = "You are an expert HR recruiter. Please analyze the following resume against the provided job description.\n\n"
            . "Job Description:\n" . $jobDescription . "\n\n"
            . "Resume Text:\n" . $resumeText . "\n\n"
            . "Extract the candidate's skills and a brief summary of their experience. "
            . "Also, provide a match score from 0 to 100 based on how well the resume matches the job description.\n\n"
            . "You MUST reply ONLY with a valid JSON object in the following format, with no markdown formatting or backticks around it:\n"
            . "{\n"
            . '  "match_score": 85,' . "\n"
            . '  "extracted_skills": "Python, React, SQL",' . "\n"
            . '  "extracted_experience": "3 years of full-stack development..."' . "\n"
            . "}";

        $response = $this->callGemini($prompt);
        
        // Clean up potential markdown formatting if the LLM ignores the instruction
        $response = trim($response);
        $response = preg_replace('/^```json\s*/', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);

        $data = json_decode($response, true);

        return [
            'match_score' => $data['match_score'] ?? 0,
            'extracted_skills' => $data['extracted_skills'] ?? '',
            'extracted_experience' => $data['extracted_experience'] ?? '',
        ];
    }

    /**
     * Determines whether a question is a general HR informational question
     */
    public function isGeneralHRQuestion(string $question): bool
    {
        $q = strtolower(trim($question));
        if (preg_match('/^(how|what|why|explain|can you explain|tell me about|guide|help)\b/i', $q)) {
            if (!preg_match('/\b(show|list|get|fetch|count|display|all|select)\b/i', $q)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Answers general HR informational questions in natural language
     */
    public function answerGeneralHRQuestion(string $question): string
    {
        $prompt = "You are an expert HR Assistant in an HRMS system. "
            . "Answer the following general HR question clearly, professionally, and concisely in natural language:\n\"" . $question . "\"";

        $response = $this->callGemini($prompt);
        if (!empty($response)) {
            return trim($response);
        }

        $q = strtolower($question);
        if (strpos($q, 'attendance') !== false) {
            return "Attendance records log daily employee check-ins, check-outs, and attendance statuses (Present, Absent, Leave). You can view and manage attendance from the Attendance tab in the main sidebar.";
        } elseif (strpos($q, 'leave') !== false) {
            return "Leave management allows employees to apply for time off and managers/HR to approve or reject leave requests. Check your Leave Balances page to view your entitlement.";
        } elseif (strpos($q, 'payroll') !== false) {
            return "Payroll processing handles monthly salaries, deductions, tax rules, and generates official payslips for employees.";
        }

        return "I am your AI HR Assistant. You can ask me general HR guidance questions or query organizational data.";
    }

    /**
     * Returns a validated, read-only SQL query
     */
    public function generateHRQuery(string $userQuestion, string $schema): string
    {
        $prompt = "You are an AI assistant helping an HR manager query a MySQL database.\n"
            . "Here is the database schema:\n"
            . $schema . "\n\n"
            . "The user asks: \"" . $userQuestion . "\"\n\n"
            . "Generate a valid MySQL SELECT query to answer the user's question.\n"
            . "Rules:\n"
            . "1. Output ONLY the raw SQL query, with no markdown formatting, no backticks, and no explanations.\n"
            . "2. ONLY use SELECT. NEVER use INSERT, UPDATE, DELETE, DROP, ALTER, TRUNCATE, etc.\n"
            . "3. Do not include a trailing semicolon.\n"
            . "4. Ensure the query is valid and uses the provided schema.";

        $response = $this->callGemini($prompt);
        
        // Clean up formatting
        $response = trim($response);
        $response = preg_replace('/^```sql\s*/', '', $response);
        $response = preg_replace('/^```\s*/', '', $response);
        $response = preg_replace('/\s*```$/', '', $response);
        $response = rtrim($response, ';');

        return $response;
    }

    /**
     * Turns query results back into a natural-language answer
     */
    public function answerFromResults(string $question, array $rows): string
    {
        $prompt = "The user asked an HR-related question: \"" . $question . "\"\n"
            . "A SQL query was executed against the database and returned the following JSON data:\n"
            . json_encode($rows) . "\n\n"
            . "Provide a clear, concise, and professional natural language answer to the user's question based ONLY on the provided data. Do not mention that you queried a database or used SQL.";

        $response = $this->callGemini($prompt);
        return trim($response);
    }
}
