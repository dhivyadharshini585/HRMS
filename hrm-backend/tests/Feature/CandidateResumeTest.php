<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobOpening;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CandidateResumeTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $hrAdmin;
    protected User $hrExecutive;
    protected User $managerUser;
    protected User $employeeUser;
    protected User $payrollUser;

    protected Department $dept;
    protected Designation $desig;
    protected JobOpening $jobOpening;
    protected Candidate $candidate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        Storage::fake('local');

        $this->superAdmin = User::where('email', 'superadmin@hrms.local')->first();
        $this->hrAdmin = User::where('email', 'hradmin@hrms.local')->first();
        $this->hrExecutive = User::where('email', 'hrexecutive@hrms.local')->first();
        $this->managerUser = User::where('email', 'manager@hrms.local')->first();
        $this->employeeUser = User::where('email', 'employee@hrms.local')->first();
        $this->payrollUser = User::where('email', 'payroll@hrms.local')->first();

        $this->dept = Department::create([
            'name' => 'Engineering',
            'description' => 'Product engineering',
        ]);

        $this->desig = Designation::create([
            'title' => 'Software Engineer',
            'description' => 'Builds systems',
        ]);

        $this->jobOpening = JobOpening::create([
            'title' => 'Fullstack Developer',
            'job_code' => 'JOB-FS-001',
            'department_id' => $this->dept->id,
            'designation_id' => $this->desig->id,
            'employment_type' => 'Full Time',
            'location' => 'Austin, TX',
            'openings_count' => 1,
            'description' => 'Fullstack developer opening',
            'status' => 'Open',
        ]);

        $this->candidate = Candidate::create([
            'candidate_code' => 'CAN-00001',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+1 555-123-4567',
            'job_opening_id' => $this->jobOpening->id,
            'status' => 'New',
        ]);
    }

    /**
     * 1. Authorized HR can upload a valid PDF resume.
     */
    public function test_1_authorized_hr_can_upload_pdf_resume(): void
    {
        $file = UploadedFile::fake()->create('john_doe_resume.pdf', 500, 'application/pdf');

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume uploaded successfully')
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'resume'],
                'extraction' => ['status', 'fields'],
            ]);

        $candidate = $this->candidate->fresh();
        $this->assertNotNull($candidate->resume_path);
        $this->assertEquals('john_doe_resume.pdf', $candidate->resume_original_name);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }

    /**
     * 2. Authorized HR can upload a valid DOCX resume.
     */
    public function test_2_authorized_hr_can_upload_docx_resume(): void
    {
        $file = UploadedFile::fake()->create('alice_resume.docx', 350, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume uploaded successfully');

        $candidate = $this->candidate->fresh();
        $this->assertNotNull($candidate->resume_path);
        $this->assertEquals('alice_resume.docx', $candidate->resume_original_name);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }

    /**
     * 3. Authorized HR can upload a valid DOC resume.
     */
    public function test_3_authorized_hr_can_upload_doc_resume(): void
    {
        $file = UploadedFile::fake()->create('legacy_resume.doc', 400, 'application/msword');

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume uploaded successfully');

        $candidate = $this->candidate->fresh();
        $this->assertNotNull($candidate->resume_path);
        $this->assertEquals('legacy_resume.doc', $candidate->resume_original_name);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }

    /**
     * 4. Unsupported file extensions (e.g., .txt, .exe, .png) are rejected.
     */
    public function test_4_unsupported_file_extension_is_rejected(): void
    {
        $txtFile = UploadedFile::fake()->create('document.txt', 100, 'text/plain');
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $txtFile,
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resume']);

        $pngFile = UploadedFile::fake()->create('image.png', 100, 'image/png');
        $responsePng = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $pngFile,
        ]);
        $responsePng->assertStatus(422)
            ->assertJsonValidationErrors(['resume']);
    }

    /**
     * 5. File exceeding 10 MB is rejected with validation error.
     */
    public function test_5_file_exceeding_10mb_is_rejected(): void
    {
        // 10241 KB > 10 MB (10240 KB)
        $largeFile = UploadedFile::fake()->create('huge_resume.pdf', 10241, 'application/pdf');

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $largeFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resume']);
    }

    /**
     * 6. Missing file payload is rejected with validation error.
     */
    public function test_6_missing_file_payload_is_rejected(): void
    {
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['resume']);
    }

    /**
     * 7. Resume is stored strictly in private local disk and not in public storage.
     */
    public function test_7_resume_is_stored_in_private_local_disk(): void
    {
        $file = UploadedFile::fake()->create('secure_resume.pdf', 300, 'application/pdf');

        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $candidate = $this->candidate->fresh();
        Storage::disk('local')->assertExists($candidate->resume_path);
        $this->assertStringStartsWith('resumes/', $candidate->resume_path);
        $this->assertStringNotContainsString('public', $candidate->resume_path);
    }

    /**
     * 8. Resume metadata is properly saved in candidates database table.
     */
    public function test_8_resume_metadata_is_saved_in_database(): void
    {
        $file = UploadedFile::fake()->create('metadata_test.pdf', 250, 'application/pdf');

        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $this->assertDatabaseHas('candidates', [
            'id' => $this->candidate->id,
            'resume_original_name' => 'metadata_test.pdf',
        ]);

        $candidate = $this->candidate->fresh();
        $this->assertNotNull($candidate->resume_size);
        $this->assertNotNull($candidate->resume_mime_type);
        $this->assertNotNull($candidate->resume_uploaded_at);
    }

    /**
     * 9. Candidate model/resource exposes safe resume attributes without raw file path.
     */
    public function test_9_candidate_resource_exposes_safe_resume_attributes(): void
    {
        $file = UploadedFile::fake()->create('safe_attributes.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $resumeData = $response->json('data.resume');
        $this->assertTrue($resumeData['exists']);
        $this->assertEquals('safe_attributes.pdf', $resumeData['original_name']);
        $this->assertTrue($resumeData['download_available']);
        $this->assertArrayNotHasKey('resume_path', $resumeData);

        $showResponse = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$this->candidate->id}");
        $showResumeData = $showResponse->json('data.resume');
        $this->assertTrue($showResumeData['exists']);
        $this->assertEquals('safe_attributes.pdf', $showResumeData['original_name']);
    }

    /**
     * 10. Resume replacement successfully updates file and metadata.
     */
    public function test_10_resume_replacement_updates_file_and_metadata(): void
    {
        $file1 = UploadedFile::fake()->create('first_resume.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file1,
        ])->assertStatus(200);

        $file2 = UploadedFile::fake()->create('updated_resume.pdf', 300, 'application/pdf');
        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file2,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume replaced successfully');

        $candidate = $this->candidate->fresh();
        $this->assertEquals('updated_resume.pdf', $candidate->resume_original_name);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }

    /**
     * 11. Resume replacement removes previous physical file from storage disk.
     */
    public function test_11_resume_replacement_deletes_previous_file_from_disk(): void
    {
        $file1 = UploadedFile::fake()->create('old_resume.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file1,
        ])->assertStatus(200);

        $oldPath = $this->candidate->fresh()->resume_path;
        Storage::disk('local')->assertExists($oldPath);

        $file2 = UploadedFile::fake()->create('new_resume.pdf', 300, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file2,
        ])->assertStatus(200);

        $newPath = $this->candidate->fresh()->resume_path;
        $this->assertNotEquals($oldPath, $newPath);
        Storage::disk('local')->assertMissing($oldPath);
        Storage::disk('local')->assertExists($newPath);
    }

    /**
     * 12. Authorized HR can download the uploaded resume.
     */
    public function test_12_authorized_hr_can_download_resume(): void
    {
        $file = UploadedFile::fake()->create('download_test.pdf', 250, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $response = $this->actingAs($this->hrAdmin)->get("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(200)
            ->assertHeader('content-disposition');
    }

    /**
     * 13. Downloading for candidate with no resume returns 404.
     */
    public function test_13_download_non_existent_resume_returns_404(): void
    {
        $response = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Resume file not found on server');
    }

    /**
     * 14. Downloading when physical file is missing from disk returns 404.
     */
    public function test_14_download_missing_physical_file_returns_404(): void
    {
        $this->candidate->update([
            'resume_path' => 'resumes/ghost_file.pdf',
            'resume_original_name' => 'ghost_file.pdf',
        ]);

        $response = $this->actingAs($this->hrAdmin)->getJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'Resume file not found on server');
    }

    /**
     * 15. Authorized HR Admin can delete candidate resume.
     */
    public function test_15_authorized_hr_admin_can_delete_resume(): void
    {
        $file = UploadedFile::fake()->create('delete_me.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume deleted successfully');
    }

    /**
     * 16. Resume deletion physically deletes the file from storage disk.
     */
    public function test_16_delete_resume_removes_physical_file_from_disk(): void
    {
        $file = UploadedFile::fake()->create('delete_disk.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $path = $this->candidate->fresh()->resume_path;
        Storage::disk('local')->assertExists($path);

        $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(200);

        Storage::disk('local')->assertMissing($path);
    }

    /**
     * 17. Resume deletion clears all resume fields in candidates table.
     */
    public function test_17_delete_resume_clears_candidate_resume_fields(): void
    {
        $file = UploadedFile::fake()->create('delete_db.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(200);

        $candidate = $this->candidate->fresh();
        $this->assertNull($candidate->resume_path);
        $this->assertNull($candidate->resume_original_name);
        $this->assertNull($candidate->resume_mime_type);
        $this->assertNull($candidate->resume_size);
        $this->assertNull($candidate->resume_uploaded_at);
        $this->assertFalse($candidate->resume['exists']);
    }

    /**
     * 18. Attempting to delete resume when none exists returns 404.
     */
    public function test_18_delete_candidate_without_resume_returns_404(): void
    {
        $response = $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(404)
            ->assertJsonPath('message', 'No resume attached to this candidate');
    }

    /**
     * 19. HR Executive cannot delete candidate resume (strictly 403).
     */
    public function test_19_hr_executive_cannot_delete_resume_403(): void
    {
        $file = UploadedFile::fake()->create('exec_cant_delete.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $response = $this->actingAs($this->hrExecutive)->deleteJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(403);
    }

    /**
     * 20. HR Executive can upload / replace candidate resume.
     */
    public function test_20_hr_executive_can_upload_resume(): void
    {
        $file = UploadedFile::fake()->create('exec_upload.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->hrExecutive)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume uploaded successfully');

        $this->assertNotNull($this->candidate->fresh()->resume_path);
    }

    /**
     * 21. HR Executive can download candidate resume.
     */
    public function test_21_hr_executive_can_download_resume(): void
    {
        $file = UploadedFile::fake()->create('exec_download.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $response = $this->actingAs($this->hrExecutive)->get("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(200);
    }

    /**
     * 22. Manager cannot upload candidate resume (403).
     */
    public function test_22_manager_cannot_upload_resume_403(): void
    {
        $file = UploadedFile::fake()->create('mgr_blocked.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->managerUser)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(403);
    }

    /**
     * 23. Manager cannot download candidate resume (403).
     */
    public function test_23_manager_cannot_download_resume_403(): void
    {
        $file = UploadedFile::fake()->create('mgr_dl_blocked.pdf', 200, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(200);

        $response = $this->actingAs($this->managerUser)->getJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(403);
    }

    /**
     * 24. Manager cannot delete candidate resume (403).
     */
    public function test_24_manager_cannot_delete_resume_403(): void
    {
        $response = $this->actingAs($this->managerUser)->deleteJson("/api/candidates/{$this->candidate->id}/resume");

        $response->assertStatus(403);
    }

    /**
     * 25. Employee cannot access any resume endpoints (403).
     */
    public function test_25_employee_cannot_access_resume_endpoints_403(): void
    {
        $file = UploadedFile::fake()->create('emp_blocked.pdf', 200, 'application/pdf');

        $this->actingAs($this->employeeUser)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(403);

        $this->actingAs($this->employeeUser)->getJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(403);
        $this->actingAs($this->employeeUser)->deleteJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(403);
    }

    /**
     * 26. Finance/Payroll cannot access candidate resume endpoints (403).
     */
    public function test_26_finance_payroll_cannot_access_resume_endpoints_403(): void
    {
        $file = UploadedFile::fake()->create('payroll_blocked.pdf', 200, 'application/pdf');

        $this->actingAs($this->payrollUser)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ])->assertStatus(403);

        $this->actingAs($this->payrollUser)->getJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(403);
        $this->actingAs($this->payrollUser)->deleteJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(403);
    }

    /**
     * 27. Resume extraction returns heuristic suggestions for parseable content.
     */
    public function test_27_resume_extraction_returns_heuristic_suggestions(): void
    {
        $content = "Jane Doe\nEmail: jane.candidate@example.com\nPhone: +1 555-987-6543\n5 years of experience in Backend Developer\nBachelor of Science in Computer Science\nAustin, TX";
        $file = UploadedFile::fake()->createWithContent('jane_resume.pdf', $content);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('extraction.status', 'completed')
            ->assertJsonPath('extraction.fields.email', 'jane.candidate@example.com')
            ->assertJsonPath('extraction.fields.phone', '+1 555-987-6543')
            ->assertJsonPath('extraction.fields.total_experience_years', 5);
    }

    /**
     * 28. Corrupted or unparseable resume content falls back to status 'unavailable'.
     */
    public function test_28_corrupted_or_unparseable_resume_falls_back_to_unavailable_status(): void
    {
        // Non-printable or empty content
        $binaryJunk = chr(0) . chr(1) . chr(2) . chr(3);
        $file = UploadedFile::fake()->createWithContent('corrupted.pdf', $binaryJunk);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('extraction.status', 'unavailable');
    }

    /**
     * 29. Extraction failure does not fail or rollback the core resume upload.
     */
    public function test_29_failed_extraction_does_not_fail_resume_upload(): void
    {
        $binaryJunk = chr(0) . chr(1) . chr(2);
        $file = UploadedFile::fake()->createWithContent('unparseable.pdf', $binaryJunk);

        $response = $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file,
        ]);

        // Upload succeeds with HTTP 200 even though extraction was unavailable
        $response->assertStatus(200)
            ->assertJsonPath('message', 'Resume uploaded successfully')
            ->assertJsonPath('extraction.status', 'unavailable');

        $candidate = $this->candidate->fresh();
        $this->assertNotNull($candidate->resume_path);
        Storage::disk('local')->assertExists($candidate->resume_path);
    }

    /**
     * 30. AuditLog records resume lifecycle events (uploaded, updated, downloaded, deleted).
     */
    public function test_30_audit_log_records_resume_lifecycle_events(): void
    {
        $file1 = UploadedFile::fake()->create('audit_test.pdf', 200, 'application/pdf');

        // 1. Upload
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file1,
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Candidate::class,
            'entity_id' => $this->candidate->id,
            'action' => 'uploaded',
        ]);

        // 2. Download
        $this->actingAs($this->hrAdmin)->get("/api/candidates/{$this->candidate->id}/resume")->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Candidate::class,
            'entity_id' => $this->candidate->id,
            'action' => 'downloaded',
        ]);

        // 3. Replace
        $file2 = UploadedFile::fake()->create('audit_test_replace.pdf', 250, 'application/pdf');
        $this->actingAs($this->hrAdmin)->postJson("/api/candidates/{$this->candidate->id}/resume", [
            'resume' => $file2,
        ])->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Candidate::class,
            'entity_id' => $this->candidate->id,
            'action' => 'updated',
        ]);

        // 4. Delete
        $this->actingAs($this->hrAdmin)->deleteJson("/api/candidates/{$this->candidate->id}/resume")->assertStatus(200);

        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Candidate::class,
            'entity_id' => $this->candidate->id,
            'action' => 'deleted',
        ]);
    }
}
