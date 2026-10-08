<?php
declare(strict_types=1);

namespace App\Core;

use DomainException;
use finfo;
use RuntimeException;
use Throwable;

final class PaymentReceipt
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function save(?array $upload): ?string
    {
        if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        if (($upload['error'] ?? null) !== UPLOAD_ERR_OK) {
            throw new DomainException('The receipt could not be uploaded. Use an image no larger than 5 MB.');
        }
        $temporary = $upload['tmp_name'] ?? null;
        $name = $upload['name'] ?? null;
        if (!is_string($temporary) || !is_string($name) || str_contains($name, "\0") || !is_uploaded_file($temporary)) {
            throw new DomainException('Invalid receipt upload.');
        }
        $size = filesize($temporary);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new DomainException('The receipt must be an image no larger than 5 MB.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($temporary);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = $mime === 'image/jpeg' ? ['jpg', 'jpeg'] : [self::TYPES[$mime] ?? ''];
        if (!isset(self::TYPES[$mime]) || !in_array($extension, $allowed, true)) {
            throw new DomainException('Use a JPG, JPEG, PNG, or WEBP receipt with a matching image format.');
        }
        if (!extension_loaded('gd')) {
            throw new RuntimeException('Receipt image processing is unavailable.');
        }
        $bytes = file_get_contents($temporary);
        $dimensions = $bytes === false ? false : @getimagesizefromstring($bytes);
        if (!$dimensions || ($dimensions['mime'] ?? '') !== $mime
            || $dimensions[0] > 6000 || $dimensions[1] > 6000
            || $dimensions[0] * $dimensions[1] > 12000000
            || preg_match('/<\?(?:php|=)|<script\b/i', $bytes)) {
            throw new DomainException('The receipt is corrupted, unsafe, or too large in image dimensions.');
        }
        $warning = false;
        set_error_handler(static function () use (&$warning): bool { $warning = true; return true; });
        try {
            $image = imagecreatefromstring($bytes);
        } finally {
            restore_error_handler();
        }
        if (!$image || $warning) {
            if ($image) imagedestroy($image);
            throw new DomainException('The receipt image is corrupted. Please upload a valid image.');
        }
        $relative = 'payment_receipts/' . bin2hex(random_bytes(24)) . '.' . self::TYPES[$mime];
        $path = $this->directory() . '/' . basename($relative);
        $handle = null;
        $created = false;
        try {
            $previousMask = umask(0077);
            try { $handle = fopen($path, 'xb'); }
            finally { umask($previousMask); }
            if ($handle === false) throw new RuntimeException('Receipt storage is unavailable.');
            $created = true;
            if ($mime !== 'image/jpeg') {
                imagealphablending($image, false);
                imagesavealpha($image, true);
            }
            $written = match ($mime) {
                'image/jpeg' => imagejpeg($image, $handle, 90),
                'image/png' => imagepng($image, $handle, 6),
                'image/webp' => imagewebp($image, $handle, 90),
            };
            fclose($handle);
            $handle = null;
            clearstatcache(true, $path);
            if (!$written || !filesize($path) || filesize($path) > self::MAX_BYTES) {
                throw new DomainException('The processed receipt exceeds 5 MB or could not be saved.');
            }
            @chmod($path, 0600);
            return $relative;
        } catch (Throwable $exception) {
            if (is_resource($handle)) fclose($handle);
            if ($created && is_file($path)) unlink($path);
            throw $exception;
        } finally {
            imagedestroy($image);
        }
    }

    private function directory(): string
    {
        $directory = realpath(BASE_PATH . '/storage/payment_receipts');
        $storage = realpath(BASE_PATH . '/storage');
        $project = realpath(BASE_PATH);
        if (!$directory || !$storage || !$project || dirname($storage) !== $project
            || dirname($directory) !== $storage || is_link(BASE_PATH . '/storage')
            || is_link(BASE_PATH . '/storage/payment_receipts')) {
            throw new RuntimeException('Receipt storage is unavailable.');
        }
        return $directory;
    }

    public function path(string $relative): ?string
    {
        if (!preg_match('/\Apayment_receipts\/[a-f0-9]{48}\.(?:jpg|png|webp)\z/', $relative)) return null;
        $directory = $this->directory();
        $candidate = $directory . '/' . basename($relative);
        $path = realpath($candidate);
        return $path && dirname($path) === $directory && is_file($path) && !is_link($candidate) ? $path : null;
    }

    public function remove(?string $relative): void
    {
        if ($relative !== null && ($path = $this->path($relative))) {
            if (!unlink($path)) throw new RuntimeException('Receipt cleanup failed.');
        }
    }

    public function mime(string $path): ?string
    {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        return isset(self::TYPES[$mime]) ? $mime : null;
    }
}
