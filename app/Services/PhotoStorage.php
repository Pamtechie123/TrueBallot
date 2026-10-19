<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * User photos (voter card, candidate on the ballot).
 *
 *  - Maximum upload size: 2 MB.
 *  - Only real JPG, PNG or WebP pictures are accepted (the content is checked, not just the file name).
 *  - The picture is re-drawn as a clean JPEG, at most 600 pixels wide or tall. This removes anything
 *    hidden inside the uploaded file and keeps the pages fast.
 *  - Photos are saved in the database (photos table), not on disk, because Render's free plan
 *    deletes files whenever the app restarts. They are shown through the /photo page after an access check.
 */
final class PhotoStorage
{
    public const MAX_BYTES = 2 * 1024 * 1024;

    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIDE      = 600;          // pixels, after resizing
    private const MIN_SIDE      = 100;          // pixels, before resizing
    private const MAX_PIXELS    = 16000000;     // refuse huge pictures that could use all the server's memory

    /** The JPEG bytes of a user's photo, or null if they have none. */
    public static function data(int $userId): ?string
    {
        $data = Database::connection()
            ->prepare('SELECT image FROM photos WHERE user_id = ?');
        $data->execute([$userId]);
        $image = $data->fetchColumn();
        return $image === false ? null : (string) $image;
    }

    public static function exists(int $userId): bool
    {
        return self::version($userId) !== 0;
    }

    /** Changes whenever the photo changes, so the browser never shows an old picture. 0 = no photo. */
    public static function version(int $userId): int
    {
        $query = Database::connection()->prepare('SELECT UNIX_TIMESTAMP(updated_at) FROM photos WHERE user_id = ?');
        $query->execute([$userId]);
        return (int) $query->fetchColumn();
    }

    public static function delete(int $userId): void
    {
        Database::connection()->prepare('DELETE FROM photos WHERE user_id = ?')->execute([$userId]);
    }

    /** True when the form had a file chosen in this field. */
    public static function wasUploaded(mixed $upload): bool
    {
        return is_array($upload) && isset($upload['error']) && !is_array($upload['error'])
            && (int) $upload['error'] !== UPLOAD_ERR_NO_FILE;
    }

    /** Check and clean an upload, then save it as this user's photo (replaces any old one). */
    public static function save(int $userId, mixed $upload): void
    {
        self::store($userId, self::prepare($upload));
    }

    public static function store(int $userId, string $jpeg): void
    {
        $query = Database::connection()->prepare(
            'INSERT INTO photos (user_id, image, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE image = VALUES(image), updated_at = NOW()'
        );
        $query->bindValue(1, $userId, \PDO::PARAM_INT);
        $query->bindValue(2, $jpeg, \PDO::PARAM_LOB);
        $query->execute();
    }

    /**
     * Check an uploaded file (an entry of $_FILES) and return it as clean JPEG bytes.
     * Throws DomainException with a message that is safe to show to the user.
     */
    public static function prepare(mixed $upload): string
    {
        if (!is_array($upload) || !isset($upload['error'], $upload['tmp_name']) || is_array($upload['error'])) {
            throw new \DomainException('Please choose a photo first.');
        }

        switch ((int) $upload['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new \DomainException('Please choose a photo first.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new \DomainException(self::tooLarge());
            default:
                throw new \DomainException('The upload did not finish. Please try again.');
        }

        $temporary = (string) $upload['tmp_name'];
        if (!is_uploaded_file($temporary)) {
            throw new \DomainException('The upload did not finish. Please try again.');
        }

        $size = (int) filesize($temporary);
        if ($size > self::MAX_BYTES) {
            throw new \DomainException(self::tooLarge());
        }
        if ($size === 0) {
            throw new \DomainException('That file is empty. Please choose another photo.');
        }

        if (!extension_loaded('gd')) {
            \App\Core\Logger::error('Photo upload failed: the PHP "gd" extension is not enabled.');
            throw new \DomainException('Photo uploads are not available yet: the server needs the PHP "gd" extension. '
                . 'On XAMPP open php.ini, remove the ; in front of extension=gd, then restart Apache.');
        }

        // Look at what is really inside the file. Its name and the type the browser claims mean nothing.
        $info = @getimagesize($temporary);
        $type = $info === false ? '' : (string) $info['mime'];

        if (!in_array($type, self::ALLOWED_TYPES, true) || $info === false) {
            throw new \DomainException('Only JPG, PNG or WebP photos are allowed.');
        }

        [$width, $height] = $info;
        if ($width < self::MIN_SIDE || $height < self::MIN_SIDE) {
            throw new \DomainException('That photo is too small. It must be at least ' . self::MIN_SIDE . ' pixels wide and tall.');
        }
        if ($width * $height > self::MAX_PIXELS) {
            throw new \DomainException('That photo has too many pixels. Please choose a smaller picture (up to about 4000 x 4000).');
        }

        $source = match ($type) {
            'image/jpeg' => @imagecreatefromjpeg($temporary),
            'image/png'  => @imagecreatefrompng($temporary),
            default      => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($temporary) : false,
        };
        if ($source === false) {
            throw new \DomainException('We could not read that picture. Please try a different photo.');
        }

        if ($type === 'image/jpeg') {
            $source = self::straighten($source, $temporary);
        }

        $clean = self::redraw($source);

        ob_start();
        imagejpeg($clean, null, 85);
        return (string) ob_get_clean();
    }

    // ------------------------------------------------------------------ helpers

    private static function tooLarge(): string
    {
        return 'That photo is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB. Please choose a smaller one.';
    }

    /** Phones store "this picture is sideways" in a hidden note. Turn the picture the right way up. */
    private static function straighten(\GdImage $image, string $file): \GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data($file);
        $turn = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };
        if ($turn === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $turn, 0);
        return $rotated === false ? $image : $rotated;
    }

    /** Draw the picture on a fresh canvas, no larger than MAX_SIDE, on a white background. */
    private static function redraw(\GdImage $source): \GdImage
    {
        $width  = imagesx($source);
        $height = imagesy($source);
        $scale  = min(1, self::MAX_SIDE / max($width, $height));
        $newW   = max(1, (int) round($width * $scale));
        $newH   = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newW, $newH);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));   // transparent PNG areas become white
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

        return $canvas;
    }
}