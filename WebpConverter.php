<?php

class WebpConverter
{
    /** @var int Qualité de compression WebP (0-100) */
    private $quality = 80;

    /** @var array Extensions à convertir */
    private $allowedExt = ['jpg', 'jpeg', 'png'];

    /**
     * Parcourt récursivement le dossier passé en paramètre (le dossier img/
     * du shop) et convertit toutes les images. Retourne un tableau avec
     * les stats (converties, ignorées, erreurs).
     */
    public function convertAll($rootDir)
    {
        $stats = ['converted' => 0, 'skipped' => 0, 'errors' => 0];

        if (!extension_loaded('gd')) {
            $stats['errors']++;
            $stats['message'] = 'Extension GD non disponible sur ce serveur.';
            return $stats;
        }

        if (!is_dir($rootDir)) {
            $stats['errors']++;
            $stats['message'] = 'Le dossier à convertir est introuvable : ' . $rootDir;
            return $stats;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {

            if (!$file->isFile()) {
                continue;
            }

            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExt)) {
                continue;
            }

            $sourcePath = $file->getPathname();
            $webpPath = preg_replace('/\.' . $ext . '$/i', '.webp', $sourcePath);

            // Ne pas reconvertir si le .webp existe déjà et est plus récent
            if (file_exists($webpPath) && filemtime($webpPath) >= filemtime($sourcePath)) {
                $stats['skipped']++;
                continue;
            }

            if ($this->convertImage($sourcePath, $webpPath, $ext)) {
                $stats['converted']++;
            } else {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function convertImage($source, $destination, $ext)
    {
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $image = @imagecreatefromjpeg($source);
                break;
            case 'png':
                $image = @imagecreatefrompng($source);
                break;
            default:
                return false;
        }

        if (!$image) {
            return false;
        }

        // Préserver la transparence pour les PNG
        if ($ext === 'png') {
            imagepalettetotruecolor($image);
            imagealphablending($image, true);
            imagesavealpha($image, true);
        }

        $result = imagewebp($image, $destination, $this->quality);
        imagedestroy($image);

        return $result;
    }
}