<?php

namespace Tests\Unit;

use App\Services\FileSecurityService;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class FileSecurityServiceTest extends TestCase
{
    public function test_pdf_magic_number_is_accepted(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bassir_pdf_');
        file_put_contents($path, "%PDF-1.7\n");

        $file = new UploadedFile($path, 'candidate.pdf', 'application/pdf', null, true);
        (new FileSecurityService())->assertAllowedCv($file);

        $this->assertTrue(true);
        @unlink($path);
    }

    public function test_executable_content_with_pdf_extension_is_rejected(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bassir_bad_');
        file_put_contents($path, "MZ executable");

        $this->expectException(RuntimeException::class);
        try {
            $file = new UploadedFile($path, 'candidate.pdf', 'application/pdf', null, true);
            (new FileSecurityService())->assertAllowedCv($file);
        } finally {
            @unlink($path);
        }
    }
}
