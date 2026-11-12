<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\DocumentAccessLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Regresi untuk dua bug keamanan dokumen:
 *   1. User yang di-assign ke folder induk harus bisa mengakses subfoldernya.
 *   2. Setiap preview/download harus tercatat di activity_log.
 */
class DocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk dokumen ditentukan config('filesystems.documents_disk'), yang
        // di .env berisi "local". Fake disk yang benar-benar dipakai, bukan
        // nama yang diasumsikan.
        Storage::fake(\App\Services\FileService::getDisk());
    }

    /**
     * Autentikasi lewat JWT cookie seperti browser sungguhan.
     *
     * actingAs() hanya mengisi session guard, sedangkan routing aplikasi ini
     * dijaga JwtAuthenticate yang membaca cookie jwt_token / Bearer token.
     * Tanpa token, setiap request akan di-redirect ke /login (302).
     */
    private function actingAsJwt(User $user): self
    {
        $token = auth('api')->login($user);

        // withCookie() meng-encode nilai via EncryptCookies; JwtAuthenticate
        // membaca raw string, jadi pakai varian unencrypted.
        return $this->withUnencryptedCookie('jwt_token', $token)->actingAs($user);
    }

    private function makeUser(string $email = 'dosen@agrotek.test'): User
    {
        return User::create([
            'name'                  => 'Dosen Uji',
            'email'                 => $email,
            'password'              => bcrypt('password-uji'),
            'role'                  => 'user',
            'is_active'             => true,
            'force_change_password' => false,
        ]);
    }

    private function makeFolder(string $name, ?Folder $parent = null): Folder
    {
        return Folder::create([
            'name'      => $name,
            'parent_id' => $parent?->id,
            'is_root'   => false,
        ]);
    }







    // ─── 1. Bug akses subfolder ──────────────────────────────────────────────

    public function test_user_can_access_subfolder_of_assigned_folder(): void
    {
        $user     = $this->makeUser();
        $assigned = $this->makeFolder('Berkas Saya');
        $sub      = $this->makeFolder('Semester 1', $assigned);
        $deep     = $this->makeFolder('Sertifikat', $sub);

        $user->accessibleFolders()->attach($assigned->id);

        $this->assertTrue($user->canAccessFolder($assigned), 'folder yang di-assign harus boleh');
        $this->assertTrue($user->canAccessFolder($sub), 'subfolder harus boleh (bug lama menolak)');
        $this->assertTrue($user->canAccessFolder($deep), 'cucu folder harus boleh');
    }

    public function test_user_cannot_access_folder_outside_assignment(): void
    {
        $user      = $this->makeUser();
        $assigned  = $this->makeFolder('Berkas Saya');
        $lainOrang = $this->makeFolder('Berkas Orang Lain');

        $user->accessibleFolders()->attach($assigned->id);

        $this->assertFalse($user->canAccessFolder($lainOrang), 'folder lain tidak boleh diakses');
    }

    public function test_user_without_assignment_cannot_access_anything(): void
    {
        $user   = $this->makeUser();
        $folder = $this->makeFolder('Umum');

        $this->assertFalse($user->canAccessFolder($folder));
    }

    public function test_admin_can_access_every_folder(): void
    {
        $admin  = User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin-uji@agrotek.test',
            'password' => bcrypt('password-uji'),
            'role'     => 'admin',
        ]);
        $folder = $this->makeFolder('Apa Saja');

        $this->assertTrue($admin->canAccessFolder($folder));
    }

    public function test_assignment_on_child_does_not_grant_parent(): void
    {
        $user   = $this->makeUser();
        $parent = $this->makeFolder('Induk');
        $child  = $this->makeFolder('Anak', $parent);

        // Hanya anak yang di-assign — induk tetap tertutup.
        $user->accessibleFolders()->attach($child->id);

        $this->assertTrue($user->canAccessFolder($child));
        $this->assertFalse($user->canAccessFolder($parent), 'akses tidak boleh mengalir ke atas');
    }

    // ─── 2. Audit trail preview & download ───────────────────────────────────

    private function makeFileIn(Folder $folder, User $uploader): File
    {
        // Fake disk dipasang sekali per test lewat setUp(); bukan di sini,
        // karena pemanggilan berulang me-reset disk dan menghapus file.
        $disk = \App\Services\FileService::getDisk();
        $path = 'documents/' . $folder->id . '/uji.pdf';
        Storage::disk($disk)->put($path, "%PDF-1.4\n% uji\n");

        return File::create([
            'folder_id'     => $folder->id,
            'name'          => 'Surat Undangan',
            'original_name' => 'surat-undangan.pdf',
            'path'          => $path,
            'mime_type'     => 'application/pdf',
            'size'          => 20,
            'uploaded_by'   => $uploader->id,
        ]);
    }

    public function test_preview_writes_audit_log(): void
    {
        $admin  = User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin-audit@agrotek.test',
            'password' => bcrypt('password-uji'),
            'role'     => 'admin',
        ]);
        $folder = $this->makeFolder('Arsip');
        $file   = $this->makeFileIn($folder, $admin);

        $this->actingAsJwt($admin)
            ->get(route('admin.files.preview', $file->id))
            ->assertOk();

        $log = DB::table('activity_log')
            ->where('log_name', DocumentAccessLogger::LOG_NAME)
            ->where('event', DocumentAccessLogger::EVENT_PREVIEW)
            ->where('subject_id', $file->id)
            ->first();

        $this->assertNotNull($log, 'preview harus tercatat di activity_log');
        $this->assertSame($admin->id, $log->causer_id);

        $props = json_decode($log->properties, true);
        $this->assertSame('Surat Undangan', $props['file_name']);
        $this->assertSame('Arsip', $props['folder_name']);
        $this->assertArrayHasKey('ip', $props);
    }

    public function test_download_writes_audit_log(): void
    {
        $admin  = User::create([
            'name'     => 'Admin Uji',
            'email'    => 'admin-audit2@agrotek.test',
            'password' => bcrypt('password-uji'),
            'role'     => 'admin',
        ]);
        $folder = $this->makeFolder('Arsip Dua');
        $file   = $this->makeFileIn($folder, $admin);

        $this->actingAsJwt($admin)
            ->get(route('admin.files.download', $file->id))
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name'   => DocumentAccessLogger::LOG_NAME,
            'event'      => DocumentAccessLogger::EVENT_DOWNLOAD,
            'subject_id' => $file->id,
            'causer_id'  => $admin->id,
        ]);
    }

    public function test_forbidden_access_does_not_write_log(): void
    {
        $pemilik = $this->makeUser('pemilik@agrotek.test');
        $lain    = User::create([
            'name'     => 'Lain',
            'email'    => 'lain@agrotek.test',
            'password' => bcrypt('password-uji'),
            'role'     => 'admin',
        ]);

        $folder = $this->makeFolder('Milik Pemilik');
        $file   = $this->makeFileIn($folder, $pemilik);

        // User tanpa akses → 403 dan tidak boleh ada log akses
        $user = $this->makeUser('tanpaakses@agrotek.test');
        $this->actingAsJwt($user)
            ->get(route('user.files.download', $file->id))
            ->assertForbidden();

        $count = DB::table('activity_log')
            ->where('log_name', DocumentAccessLogger::LOG_NAME)
            ->where('subject_id', $file->id)
            ->count();

        $this->assertSame(0, $count, 'akses yang ditolak tidak boleh tercatat sebagai akses berhasil');
    }

    public function test_user_can_preview_file_in_subfolder(): void
    {
        $user     = $this->makeUser();
        $assigned = $this->makeFolder('Berkas Saya');
        $sub      = $this->makeFolder('Sub', $assigned);
        $user->accessibleFolders()->attach($assigned->id);

        $file = $this->makeFileIn($sub, $user);

        // Ini yang gagal sebelum perbaikan: file di subfolder → 403.
        $this->actingAsJwt($user)
            ->get(route('user.files.preview', $file->id))
            ->assertOk();

        $this->assertDatabaseHas('activity_log', [
            'log_name'   => DocumentAccessLogger::LOG_NAME,
            'event'      => DocumentAccessLogger::EVENT_PREVIEW,
            'subject_id' => $file->id,
        ]);
    }
}
