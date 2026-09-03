<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * media_helper — résout l'URL d'affichage d'une photo selon son origine.
 *
 *   - R2    : $p['r2_key'] présent  -> URL de transformation Cloudflare
 *             (WebP/AVIF à l'edge, format=auto ; AUCUN traitement serveur).
 *   - LOCAL : dérivés sur disque (path_thumb/medium/full).
 *
 * Utilisé par la galerie publique (grille, lightbox, couvertures d'album).
 */

if ( ! function_exists('av_r2'))
{
    /** Instance partagée de la librairie R2. */
    function av_r2()
    {
        $CI =& get_instance();
        if ( ! isset($CI->r2))
        {
            $CI->load->library('r2');
        }
        return $CI->r2;
    }
}

if ( ! function_exists('av_thumb_url'))
{
    function av_thumb_url($p)
    {
        if ( ! empty($p['r2_key'])) return av_r2()->imageUrl($p['r2_key'], 'thumb');
        return base_url($p['path_thumb_webp'] ?? '');
    }
}

if ( ! function_exists('av_medium_url'))
{
    function av_medium_url($p)
    {
        if ( ! empty($p['r2_key'])) return av_r2()->imageUrl($p['r2_key'], 'medium');
        return base_url($p['path_medium_webp'] ?? '');
    }
}

if ( ! function_exists('av_full_url'))
{
    function av_full_url($p)
    {
        if ( ! empty($p['r2_key'])) return av_r2()->imageUrl($p['r2_key'], 'full');
        return base_url($p['path_full_webp'] ?? '');
    }
}

if ( ! function_exists('av_video_url'))
{
    /** URL de lecture d'une vidéo : MP4 servi tel quel par le CDN (range-requests). */
    function av_video_url($v)
    {
        return av_r2()->publicUrl($v['r2_key']);
    }
}
