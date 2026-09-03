<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Image_processor
 * --------------------------------------------------------------------
 * Génération des dérivés d'image pour Archiven.
 *
 *  - Driver primaire : PHP Imagick (HEIC pris en charge si libheif présent).
 *  - Fallback        : GD (JPEG/PNG uniquement, pas de HEIC).
 *
 * N'utilise PAS la librairie Image_lib de CodeIgniter.
 *
 * Méthode principale : generate($source, $opts) qui produit, photo par photo :
 *   - WebP thumb  (plus grand côté = thumb_px)  — grille de la galerie
 *   - WebP medium (plus grand côté = medium_px) — lightbox
 *   - WebP full   (pleine résolution)           — repli téléchargement après purge
 *   - JPEG original (copie/conversion)          — téléchargement haute qualité
 * et retourne ['width' => int, 'height' => int].
 *
 * La grille publique n'affiche QUE le thumb (+ medium en srcset) : le full
 * n'est jamais chargé dans la galerie, ce qui la garde légère.
 *
 * En cas d'échec, lève une Exception (le worker la capture et marque
 * le job en "error" sans bloquer le lot).
 */
class Image_processor {

    /** @var string 'imagick' | 'gd' */
    protected $driver;

    public function __construct()
    {
        $this->driver = extension_loaded('imagick') ? 'imagick' : 'gd';
    }

    public function driver()
    {
        return $this->driver;
    }

    /**
     * Le format HEIC est-il décodable ?
     */
    public function heic_supported()
    {
        if ($this->driver === 'imagick')
        {
            try {
                $formats = (new Imagick())->queryFormats('HEIC');
                return ! empty($formats);
            } catch (Exception $e) {
                return FALSE;
            }
        }
        return FALSE; // GD ne lit pas le HEIC
    }

    /**
     * Génère tous les dérivés.
     *
     * @param string $source Chemin du fichier source.
     * @param array  $opts {
     *     thumb, medium, full, original : chemins de destination (absolus),
     *     thumb_px, medium_px           : tailles (plus grand côté),
     *     webp_q, full_q, jpeg_q        : qualités.
     * }
     * @return array ['width'=>int,'height'=>int]
     * @throws Exception
     */
    public function generate($source, array $opts)
    {
        if ( ! is_file($source))
        {
            throw new Exception('Fichier source introuvable : '.$source);
        }

        // Valeurs par défaut.
        $opts += array(
            'thumb_px'  => 300,
            'medium_px' => 1600,
            'webp_q'    => 82,
            'full_q'    => 90,
            'jpeg_q'    => 90,
        );

        // S'assure que les dossiers de destination existent.
        foreach (array('thumb', 'medium', 'full', 'original') as $k)
        {
            if ( ! empty($opts[$k]))
            {
                $dir = dirname($opts[$k]);
                if ( ! is_dir($dir) && ! @mkdir($dir, 0775, TRUE) && ! is_dir($dir))
                {
                    throw new Exception('Impossible de créer le dossier : '.$dir);
                }
            }
        }

        return $this->driver === 'imagick'
            ? $this->generate_imagick($source, $opts)
            : $this->generate_gd($source, $opts);
    }

    /* =================================================================
     |  Implémentation IMAGICK
     | ================================================================= */
    protected function generate_imagick($source, array $opts)
    {
        $img = new Imagick();
        try {
            $img->readImage($source);
            // Aplatit les images multi-frames éventuelles (HEIC live, etc.).
            $img = $img->coalesceImages();
            $img->setIteratorIndex(0);

            // Respecte l'orientation EXIF puis la réinitialise.
            $this->imagick_autoorient($img);

            $width  = $img->getImageWidth();
            $height = $img->getImageHeight();

            // 1) JPEG original (conversion/copie temporaire).
            if ( ! empty($opts['original']))
            {
                $orig = clone $img;
                $orig->setImageFormat('jpeg');
                $orig->setImageCompressionQuality((int) $opts['jpeg_q']);
                $orig->setImageBackgroundColor('#ffffff');
                $orig = $orig->flattenImages(); // gère la transparence PNG
                $orig->writeImage($opts['original']);
                $orig->clear();
            }

            // 2) WebP full (pleine résolution, qualité élevée).
            if ( ! empty($opts['full']))
            {
                $full = clone $img;
                $full->setImageFormat('webp');
                $full->setImageCompressionQuality((int) $opts['full_q']);
                $full->writeImage($opts['full']);
                $full->clear();
            }

            // 3) WebP medium (lightbox).
            if ( ! empty($opts['medium']))
            {
                $med = clone $img;
                $med->thumbnailImage((int) $opts['medium_px'], (int) $opts['medium_px'], TRUE);
                $med->setImageFormat('webp');
                $med->setImageCompressionQuality((int) $opts['webp_q']);
                $med->writeImage($opts['medium']);
                $med->clear();
            }

            // 4) WebP thumb (grille).
            if ( ! empty($opts['thumb']))
            {
                $th = clone $img;
                $th->thumbnailImage((int) $opts['thumb_px'], (int) $opts['thumb_px'], TRUE);
                $th->setImageFormat('webp');
                $th->setImageCompressionQuality((int) $opts['webp_q']);
                $th->writeImage($opts['thumb']);
                $th->clear();
            }

            $img->clear();

            return array('width' => $width, 'height' => $height);
        }
        catch (Exception $e) {
            @$img->clear();
            throw new Exception('Imagick : '.$e->getMessage());
        }
    }

    protected function imagick_autoorient(Imagick $img)
    {
        $orientation = $img->getImageOrientation();
        switch ($orientation)
        {
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $img->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $img->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $img->rotateImage('#000', -90);
                break;
        }
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    /* =================================================================
     |  Implémentation GD (fallback ; pas de HEIC)
     | ================================================================= */
    protected function generate_gd($source, array $opts)
    {
        if ( ! function_exists('imagewebp'))
        {
            throw new Exception('GD sans support WebP : impossible de générer les dérivés.');
        }

        $info = @getimagesize($source);
        if ($info === FALSE)
        {
            throw new Exception('Format non lisible par GD (HEIC ? fournir du JPEG).');
        }

        $mime = $info['mime'];
        switch ($mime)
        {
            case 'image/jpeg':
                $src = @imagecreatefromjpeg($source);
                break;
            case 'image/png':
                $src = @imagecreatefrompng($source);
                break;
            default:
                throw new Exception('GD ne gère pas ce format : '.$mime.' (fournir du JPEG/PNG).');
        }

        if ( ! $src)
        {
            throw new Exception('GD : échec de lecture de l\'image.');
        }

        // Orientation EXIF (JPEG).
        $src = $this->gd_autoorient($src, $source, $mime);

        $width  = imagesx($src);
        $height = imagesy($src);

        try {
            // 1) JPEG original (aplati sur fond blanc si transparence).
            if ( ! empty($opts['original']))
            {
                $flat = $this->gd_flatten($src, $width, $height);
                imagejpeg($flat, $opts['original'], (int) $opts['jpeg_q']);
                imagedestroy($flat);
            }

            // 2) WebP full (pleine résolution).
            if ( ! empty($opts['full']))
            {
                imagewebp($src, $opts['full'], (int) $opts['full_q']);
            }

            // 3) WebP medium (lightbox).
            if ( ! empty($opts['medium']))
            {
                $this->gd_resize_webp($src, $width, $height, (int) $opts['medium_px'], $opts['medium'], (int) $opts['webp_q']);
            }

            // 4) WebP thumb (grille).
            if ( ! empty($opts['thumb']))
            {
                $this->gd_resize_webp($src, $width, $height, (int) $opts['thumb_px'], $opts['thumb'], (int) $opts['webp_q']);
            }
        }
        finally {
            imagedestroy($src);
        }

        return array('width' => $width, 'height' => $height);
    }

    /**
     * Redimensionne (plus grand côté = $max_px, jamais agrandi) et écrit en WebP.
     */
    protected function gd_resize_webp($src, $w, $h, $max_px, $dest, $quality)
    {
        $scale = min(1, $max_px / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        if ($scale >= 1)
        {
            // Pas d'agrandissement : on encode tel quel.
            imagewebp($src, $dest, $quality);
            return;
        }

        $dst = imagecreatetruecolor($nw, $nh);
        // Préserve la transparence pour l'étape (même si WebP final).
        imagealphablending($dst, FALSE);
        imagesavealpha($dst, TRUE);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagewebp($dst, $dest, $quality);
        imagedestroy($dst);
    }

    /**
     * Retourne une copie aplatie sur fond blanc (pour JPEG).
     */
    protected function gd_flatten($src, $w, $h)
    {
        $flat = imagecreatetruecolor($w, $h);
        $white = imagecolorallocate($flat, 255, 255, 255);
        imagefilledrectangle($flat, 0, 0, $w, $h, $white);
        imagecopy($flat, $src, 0, 0, 0, 0, $w, $h);
        return $flat;
    }

    /**
     * Applique l'orientation EXIF pour les JPEG sous GD.
     */
    protected function gd_autoorient($src, $source, $mime)
    {
        if ($mime !== 'image/jpeg' || ! function_exists('exif_read_data'))
        {
            return $src;
        }

        $exif = @exif_read_data($source);
        if (empty($exif['Orientation']))
        {
            return $src;
        }

        switch ((int) $exif['Orientation'])
        {
            case 3:
                $rot = imagerotate($src, 180, 0);
                break;
            case 6:
                $rot = imagerotate($src, -90, 0);
                break;
            case 8:
                $rot = imagerotate($src, 90, 0);
                break;
            default:
                return $src;
        }

        if ($rot)
        {
            imagedestroy($src);
            return $rot;
        }
        return $src;
    }
}
