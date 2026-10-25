<?php
namespace App\Traits;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

trait HasImage
{
    /**
     * Store an uploaded image and return its relative path.
     */
    public function saveImage(
        UploadedFile $image,
        string $folder
    ): string {
        $folder = trim($folder, '/');

        if ($folder === '' || str_contains($folder, '..')) {
            throw new InvalidArgumentException(
                'Invalid image folder.'
            );
        }

        $path = $image->store($folder, 'public');

        if ($path === false) {
            throw new \RuntimeException(
                'Failed to store the uploaded image.'
            );
        }

        return $path;
    }

    /**
     * Delete an image using its relative storage path.
     */
    public function deleteImage(?string $path): bool
    {
        if (empty($path)) {
            return false;
        }

        // Prevent deleting files outside the expected directory.
        $path = ltrim($path, '/');

        if (
            str_contains($path, '..') ||
            str_starts_with($path, 'storage/')
        ) {
            throw new InvalidArgumentException(
                'Invalid image path.'
            );
        }

        return Storage::disk('public')->delete($path);
    }

    /**
     * Generate the public URL for an image path.
     */
    public function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}