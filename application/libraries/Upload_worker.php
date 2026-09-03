<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Upload_worker — traitement de la file d'attente d'images.
 *
 * Logique partagée entre :
 *   - le cron CLI (Cron::process_uploads),
 *   - le bouton admin "Traiter maintenant" (Uploads::process).
 *
 * Génère les WebP (thumb grille + medium lightbox + full repli) + l'original
 * JPEG, enregistre la photo, supprime la source temporaire. Robuste : une
 * image en échec marque le job en "error" sans bloquer le lot.
 */
class Upload_worker {

    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model(array('Event_model', 'Upload_job_model', 'Photo_model'));
        $this->CI->load->library('Image_processor');
    }

    public function driver()
    {
        return $this->CI->image_processor->driver();
    }

    /**
     * Traite un lot de jobs "pending".
     *
     * @param  int      $limit
     * @param  callable $logger fonction(string) optionnelle (sortie CLI)
     * @return array ['ok'=>int, 'err'=>int, 'processed'=>int, 'results'=>array]
     *               results : une entrée par job traité, pour l'affichage live
     *               admin -> ['job_id', 'ok', 'photo_id'?, 'webp'?, 'error'?]
     */
    public function process_batch($limit = 30, $logger = NULL, $event_ids = NULL)
    {
        $log = is_callable($logger) ? $logger : function () {};

        $ids = $this->CI->Upload_job_model->next_pending_ids($limit, $event_ids);
        if (empty($ids))
        {
            $log('Aucun job en attente.');
            return array('ok' => 0, 'err' => 0, 'processed' => 0, 'results' => array());
        }

        $log('Traitement de '.count($ids).' job(s)... (driver image : '.$this->driver().')');

        $ok = 0; $err = 0; $results = array();
        foreach ($ids as $id)
        {
            // Réservation atomique (évite le double-traitement si chevauchement).
            $job = $this->CI->Upload_job_model->claim($id);
            if ( ! $job)
            {
                continue;
            }

            try {
                $photo = $this->process_one($job);
                $this->CI->Upload_job_model->mark_done($job['id']);
                $ok++;
                $results[] = array(
                    'job_id'   => (int) $job['id'],
                    'ok'       => TRUE,
                    'photo_id' => (int) $photo['id'],
                    'webp'     => base_url($photo['webp']),
                );
            }
            catch (Exception $e) {
                $this->CI->Upload_job_model->mark_error($job['id'], $e->getMessage());
                $log('  [ERREUR] job #'.$job['id'].' : '.$e->getMessage());
                $err++;
                $results[] = array(
                    'job_id' => (int) $job['id'],
                    'ok'     => FALSE,
                    'error'  => $e->getMessage(),
                );
            }
        }

        $log("Terminé : $ok succès, $err erreur(s).");
        return array('ok' => $ok, 'err' => $err, 'processed' => $ok + $err, 'results' => $results);
    }

    /**
     * Traite un job unique.
     * @return array ['id'=>int photo, 'webp'=>string chemin relatif full WebP]
     * @throws Exception
     */
    protected function process_one(array $job)
    {
        $event = $this->CI->Event_model->get($job['event_id']);
        if ( ! $event)
        {
            throw new Exception('Événement introuvable (#'.$job['event_id'].').');
        }
        $slug = $event['slug'];

        $source = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $job['source_path']);
        if ( ! is_file($source))
        {
            throw new Exception('Fichier source absent : '.$job['source_path']);
        }

        $base = bin2hex(random_bytes(16));

        $rel = array(
            'thumb'    => "uploads/$slug/thumb/$base.webp",
            'medium'   => "uploads/$slug/medium/$base.webp",
            'full'     => "uploads/$slug/full/$base.webp",
            'original' => "uploads/$slug/original/$base.jpg",
        );

        $abs = array();
        foreach ($rel as $k => $v)
        {
            $abs[$k] = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $v);
        }

        $meta = $this->CI->image_processor->generate($source, array(
            'thumb'     => $abs['thumb'],
            'medium'    => $abs['medium'],
            'full'      => $abs['full'],
            'original'  => $abs['original'],
            'thumb_px'  => (int) $this->CI->config->item('img_thumb_px'),
            'medium_px' => (int) $this->CI->config->item('img_medium_px'),
            'webp_q'    => (int) $this->CI->config->item('img_webp_quality'),
            'full_q'    => (int) $this->CI->config->item('img_full_quality'),
            'jpeg_q'    => (int) $this->CI->config->item('img_full_quality'),
        ));

        $photo_id = $this->CI->Photo_model->create(array(
            'event_id'         => $job['event_id'],
            'album_id'         => $job['album_id'] ?: NULL,
            'filename_base'    => $base,
            'path_thumb_webp'  => $rel['thumb'],
            'path_medium_webp' => $rel['medium'],
            'path_full_webp'   => $rel['full'],
            'path_original'    => $rel['original'],
            'original_purged'  => 0,
            'largeur'          => $meta['width'],
            'hauteur'          => $meta['height'],
            'ordre'            => $this->CI->Photo_model->next_ordre($job['event_id']),
        ));

        // Supprime la source temporaire de _incoming.
        @unlink($source);

        // 'webp' = la vignette, pour l'aperçu live léger côté admin.
        return array('id' => $photo_id, 'webp' => $rel['thumb']);
    }
}
