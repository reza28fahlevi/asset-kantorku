<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('download', $attachment);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404, 'File tidak ditemukan.');

        AuditLogger::log('attachment_downloaded', $attachment);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name);
    }
}
