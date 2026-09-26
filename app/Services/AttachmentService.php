<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AttachmentService
{
    /** Ekstensi yang diizinkan untuk lampiran. */
    public const ALLOWED_MIMES = 'pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx';

    /** Rule validasi file standar. */
    public static function rules(bool $required = false): array
    {
        $max = (int) Setting::get('attachment.max_size_kb', 5120);

        return [$required ? 'required' : 'nullable', 'file', 'mimes:'.self::ALLOWED_MIMES, "max:{$max}"];
    }

    /**
     * Simpan file ke disk privat (storage/app/private) dan catat metadata.
     *
     * @param  UploadedFile|array<UploadedFile>|null  $files
     */
    public function store(Model $owner, UploadedFile|array|null $files, string $category = 'OTHER'): void
    {
        foreach (array_filter((array) $files) as $file) {
            $dir = 'attachments/'.$owner->getMorphClass().'/'.$owner->getKey();
            $name = Str::uuid().'.'.$file->getClientOriginalExtension();
            $path = $file->storeAs($dir, $name, 'local');

            Attachment::create([
                'attachable_type' => $owner->getMorphClass(),
                'attachable_id' => $owner->getKey(),
                'category' => $category,
                'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'stored_path' => $path,
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSize(),
                'uploaded_by_user_id' => Auth::id(),
            ]);
        }
    }
}
