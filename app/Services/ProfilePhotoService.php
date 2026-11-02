<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Square JPEG avatar (~256px) under public/uploads/avatars (shared-hosting friendly).
 */
class ProfilePhotoService
{
    public const SIZE = 256;

    public function store(UploadedFile $file, int $userId, ?string $oldPath = null): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw new \RuntimeException(__('Image processing (GD) is not available on this server.'));
        }

        $dir = public_path('uploads/avatars');
        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new \RuntimeException(__('Could not create avatar folder.'));
        }

        $raw = file_get_contents($file->getRealPath());
        $image = @imagecreatefromstring($raw ?: '');
        if (! $image) {
            throw new \RuntimeException(__('Could not read that image. Use JPG, PNG, GIF or WebP.'));
        }

        $square = $this->toSquare($image, self::SIZE);
        imagedestroy($image);

        $name = 'u'.$userId.'_'.Str::lower(Str::random(8)).'.jpg';
        $abs = $dir.DIRECTORY_SEPARATOR.$name;
        imagejpeg($square, $abs, 82);
        imagedestroy($square);

        $this->deleteOld($oldPath);

        return 'uploads/avatars/'.$name;
    }

    public function deleteOld(?string $path): void
    {
        if (! $path) {
            return;
        }
        $rel = ltrim(str_replace('\\', '/', $path), '/');
        if (! str_starts_with($rel, 'uploads/avatars/')) {
            return;
        }
        $abs = public_path($rel);
        $root = realpath(public_path('uploads/avatars'));
        $real = is_file($abs) ? realpath($abs) : false;
        if ($root && $real && str_starts_with($real, $root)) {
            @unlink($real);
        }
    }

    /** @param  \GdImage  $image */
    private function toSquare($image, int $size)
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $side = min($w, $h);
        $sx = (int) (($w - $side) / 2);
        $sy = (int) (($h - $side) / 2);
        $out = imagecreatetruecolor($size, $size);
        imagecopyresampled($out, $image, 0, 0, $sx, $sy, $size, $size, $side, $side);

        return $out;
    }
}
