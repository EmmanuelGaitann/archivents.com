<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/Uploads — zone d'upload in-site (glisser-déposer).
 *
 * Principe clé : la requête HTTP NE TRAITE RIEN.
 *   1. on valide vite le fichier,
 *   2. on dépose la source dans uploads/_incoming/,
 *   3. on crée une ligne upload_jobs "pending",
 *   4. on répond immédiatement.
 * Le worker CLI (Cron::process_uploads) fait le travail lourd.
 */
class Uploads extends Admin_Controller {

    /** Message renvoyé tant que l'adresse e-mail n'est pas confirmée. */
    const MSG_UNVERIFIED = 'Confirmez d\'abord votre adresse e-mail (bouton « Renvoyer » sur le tableau de bord) avant d\'importer des fichiers.';

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('photo.upload');
        $this->load->model(array('Event_model', 'Album_model', 'Upload_job_model'));
    }

    /**
     * Page d'upload : sélection événement + album + dropzone.
     */
    public function index()
    {
        $events = $this->accessible_events();

        // Événement courant : ?event=ID, sinon le premier.
        $event_id = (int) $this->input->get('event');
        if ( ! $event_id && ! empty($events))
        {
            $event_id = (int) $events[0]['id'];
        }

        if ($event_id) $this->guard_event($event_id);
        $event  = $event_id ? $this->Event_model->get($event_id) : NULL;
        $albums = $event ? $this->Album_model->for_event($event_id) : array();
        $counts = $event ? $this->Upload_job_model->counts($event_id) : NULL;

        $this->load->view('admin/layout/header', array(
            'title' => 'Upload de photos',
            'user'  => $this->current_user,
        ));
        $this->load->library('r2');
        $r2_active = ($this->config->item('storage_driver') === 'r2') && $this->r2->is_configured();

        // Quota de stockage (bannière) + option vidéo du forfait.
        $storage_quota_mo = $this->effective_storage_mo();
        $storage_used     = $this->is_super() ? NULL : $this->storage_used_bytes();
        $video_cfg        = $this->config->item('video');

        $this->load->view('admin/uploads/index', array(
            'events'          => $events,
            'event'           => $event,
            'albums'          => $albums,
            'counts'          => $counts,
            'r2_active'       => $r2_active,
            'video_allowed'   => $r2_active && $this->plan_allows_video(),
            'video_cfg'       => $video_cfg,
            'storage_used'    => $storage_used,
            'storage_quota_mo'=> $storage_quota_mo,
        ));
        $this->load->view('admin/layout/footer');
    }

    /**
     * Réception AJAX d'un fichier. Répond en JSON, sans traiter l'image.
     */
    public function store()
    {
        $this->output->set_content_type('application/json');

        $event_id = (int) $this->input->post('event_id');
        $album_id = $this->input->post('album_id');
        $album_id = ($album_id === '' || $album_id === NULL) ? NULL : (int) $album_id;

        $event = $this->Event_model->get($event_id);
        if ( ! $event)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement invalide.'), 422);
        }

        // Cloisonnement : un admin ne peut envoyer que dans ses événements.
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($event_id, $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement non autorisé.'), 403);
        }

        // Anti-spam : adresse e-mail confirmée obligatoire avant tout upload.
        if ( ! $this->email_confirmed())
        {
            return $this->json(array('ok' => FALSE, 'error' => self::MSG_UNVERIFIED), 403);
        }

        if ( ! $this->Album_model->belongs_to($album_id, $event_id))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Album invalide pour cet événement.'), 422);
        }

        // Plafond de photos du forfait (ex. Test = 30 / événement).
        $max_photos = $this->plan_max_photos();
        if ($max_photos !== NULL)
        {
            $this->load->model('Photo_model');
            $c    = $this->Upload_job_model->counts($event_id);
            $used = (int) $this->Photo_model->count_for_event($event_id)
                  + (int) $c['pending'] + (int) $c['processing'];
            if ($used >= $max_photos)
            {
                return $this->json(array('ok' => FALSE, 'error' =>
                    'Limite de '.$max_photos.' photos atteinte pour cet événement (votre forfait). '
                    .'Passez à un forfait supérieur pour en ajouter davantage.'), 422);
            }
        }

        if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Aucun fichier reçu ('.$this->upload_error_label($_FILES['file']['error'] ?? -1).').'), 422);
        }

        // Quota de stockage du forfait (photos + vidéos, tous événements).
        if ( ! $this->can_store_bytes((int) $_FILES['file']['size'], $qerr))
        {
            return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
        }

        $tmp = $_FILES['file']['tmp_name'];

        // Validation MIME réelle (pas de confiance dans l'extension cliente).
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = finfo_file($finfo, $tmp);
        finfo_close($finfo);

        $allowed = $this->config->item('upload_allowed_mime');
        if ( ! in_array($mime, $allowed, TRUE))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Type non autorisé : '.$mime), 422);
        }

        // Dépose la source dans _incoming avec un nom hashé.
        $incoming = $this->config->item('upload_incoming');
        if ( ! is_dir($incoming) && ! @mkdir($incoming, 0775, TRUE) && ! is_dir($incoming))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Dossier _incoming non accessible.'), 500);
        }

        $ext  = $this->ext_for_mime($mime);
        $name = bin2hex(random_bytes(16)).'.'.$ext;
        $dest = $incoming.$name;

        if ( ! move_uploaded_file($tmp, $dest))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Échec de l\'enregistrement du fichier.'), 500);
        }

        // Chemin relatif (portable) stocké dans le job.
        $rel = 'uploads/_incoming/'.$name;

        $job_id = $this->Upload_job_model->create($event_id, $album_id, $rel);

        return $this->json(array('ok' => TRUE, 'job_id' => $job_id));
    }

    /**
     * (R2) Émet une URL présignée pour l'upload DIRECT navigateur -> R2.
     * Le serveur ne reçoit jamais le fichier ; il crée juste la ligne « pending ».
     */
    public function sign()
    {
        $this->output->set_content_type('application/json');
        $this->load->library('r2');

        if ($this->config->item('storage_driver') !== 'r2' || ! $this->r2->is_configured())
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Stockage R2 inactif.'), 400);
        }

        $event_id = (int) $this->input->post('event_id');
        $album_id = $this->input->post('album_id');
        $album_id = ($album_id === '' || $album_id === NULL) ? NULL : (int) $album_id;
        $filename = (string) $this->input->post('filename', TRUE);
        $ct       = (string) $this->input->post('content_type', TRUE) ?: 'image/jpeg';

        $event = $this->Event_model->get($event_id);
        if ( ! $event)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement invalide.'), 422);
        }
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($event_id, $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement non autorisé.'), 403);
        }
        if ( ! $this->email_confirmed())
        {
            return $this->json(array('ok' => FALSE, 'error' => self::MSG_UNVERIFIED), 403);
        }
        if ( ! $this->Album_model->belongs_to($album_id, $event_id))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Album invalide.'), 422);
        }

        $allowed = array('image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif');
        if ( ! in_array($ct, $allowed, TRUE))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Type non autorisé : '.$ct), 422);
        }

        // Quota du forfait (abonnement actif + plafond photos + stockage).
        $size = max(0, (int) $this->input->post('size'));
        if ( ! $this->is_super())
        {
            if ( ! $this->active_subscription())
            {
                return $this->json(array('ok' => FALSE, 'error' => 'Abonnement non actif.'), 403);
            }
            $this->load->model('Photo_model');
            $max = $this->plan_max_photos();
            if ($max !== NULL && $this->Photo_model->count_all_for_event($event_id) >= $max)
            {
                return $this->json(array('ok' => FALSE, 'error' =>
                    'Limite de '.$max.' photos atteinte pour cet événement (votre forfait).'), 422);
            }
            if ( ! $this->can_store_bytes($size, $qerr))
            {
                return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
            }
        }

        $this->load->model('Photo_model');
        $key = $this->r2->buildKey($event['slug'], $event_id, $filename);
        $photo_id = $this->Photo_model->create_pending(
            $event_id, $album_id, $key, $this->Photo_model->next_ordre($event_id),
            $size > 0 ? $size : NULL
        );

        return $this->json(array(
            'ok'         => TRUE,
            'upload_url' => $this->r2->presignUpload($key, $ct),
            'key'        => $key,
            'photo_id'   => $photo_id,
        ));
    }

    /**
     * (R2) Confirme l'upload : vérifie l'objet sur R2 puis passe la photo
     * en « ready » (avec les dimensions fournies par le navigateur).
     */
    public function confirm()
    {
        $this->output->set_content_type('application/json');
        $this->load->library('r2');
        $this->load->model('Photo_model');

        $photo_id = (int) $this->input->post('photo_id');
        $key      = (string) $this->input->post('key', TRUE);
        $w        = (int) $this->input->post('w');
        $h        = (int) $this->input->post('h');

        $photo = $this->Photo_model->get($photo_id);
        if ( ! $photo || $photo['r2_key'] !== $key)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Photo introuvable.'), 404);
        }
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($photo['event_id'], $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Non autorisé.'), 403);
        }

        $real = $this->r2->size($key);
        if ($real === FALSE)
        {
            // Panne réseau (après retries) : on ne conclut PAS « absent »,
            // le client peut simplement re-tenter la confirmation.
            return $this->json(array('ok' => FALSE, 'retry' => TRUE, 'error' =>
                'Stockage momentanément injoignable, réessayez.'), 503);
        }
        if ($real === NULL)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Objet absent sur R2.'), 422);
        }

        // Taille réelle mesurée sur R2 : corrige la taille déclarée à la
        // signature, et rejette si la sous-déclaration fait déborder le quota.
        $declared = (int) ($photo['size_bytes'] ?? 0);
        if ($real > 0 && $real !== $declared)
        {
            $this->Photo_model->set_size($photo_id, $real);
            if ($real > $declared && ! $this->is_super() && ! $this->can_store_bytes(0, $qerr))
            {
                $this->Photo_model->delete($photo_id); // supprime aussi l'objet R2
                return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
            }
        }

        $this->Photo_model->mark_ready($photo_id, $w, $h);
        return $this->json(array('ok' => TRUE, 'thumb' => $this->r2->imageUrl($key, 'thumb')));
    }

    /* =================================================================
     |  VIDÉO — upload direct R2 (simple <= seuil, sinon multipart).
     |  Réservé aux forfaits avec option vidéo (plans.video = 1).
     |  MP4 web-ready exigé (H.264/AAC) : servi tel quel par le CDN.
     | ================================================================= */

    /**
     * (R2) Initie l'upload d'une vidéo.
     * Répond mode 'single' (URL présignée unique) ou 'multipart'
     * (upload_id + taille de part ; les parts sont signées à la demande).
     */
    public function sign_video()
    {
        $this->output->set_content_type('application/json');
        $this->load->library('r2');

        if ($this->config->item('storage_driver') !== 'r2' || ! $this->r2->is_configured())
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Stockage R2 inactif.'), 400);
        }

        $event_id = (int) $this->input->post('event_id');
        $album_id = $this->input->post('album_id');
        $album_id = ($album_id === '' || $album_id === NULL) ? NULL : (int) $album_id;
        $filename = (string) $this->input->post('filename', TRUE);
        $ct       = (string) $this->input->post('content_type', TRUE) ?: 'video/mp4';
        $size     = max(0, (int) $this->input->post('size'));

        $event = $this->Event_model->get($event_id);
        if ( ! $event)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement invalide.'), 422);
        }
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($event_id, $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Événement non autorisé.'), 403);
        }
        if ( ! $this->email_confirmed())
        {
            return $this->json(array('ok' => FALSE, 'error' => self::MSG_UNVERIFIED), 403);
        }
        if ( ! $this->Album_model->belongs_to($album_id, $event_id))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Album invalide.'), 422);
        }

        $cfg = $this->config->item('video');
        if ( ! in_array($ct, $cfg['allowed_mime'], TRUE))
        {
            return $this->json(array('ok' => FALSE, 'error' =>
                'Format non pris en charge : envoyez un MP4 (H.264/AAC).'), 422);
        }
        if ($size < 1)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Taille du fichier manquante.'), 422);
        }
        if ($size > (int) $cfg['max_bytes'])
        {
            return $this->json(array('ok' => FALSE, 'error' =>
                'Vidéo trop volumineuse (maximum '.$this->format_go($cfg['max_bytes']).').'), 422);
        }

        // Gating forfait : option vidéo + abonnement actif + quota de stockage.
        if ( ! $this->is_super())
        {
            if ( ! $this->active_subscription())
            {
                return $this->json(array('ok' => FALSE, 'error' => 'Abonnement non actif.'), 403);
            }
            if ( ! $this->plan_allows_video())
            {
                return $this->json(array('ok' => FALSE, 'error' =>
                    'L\'ajout de vidéos n\'est pas inclus dans votre forfait. '
                    .'Passez au forfait Studio pour en profiter.'), 403);
            }
            if ( ! $this->can_store_bytes($size, $qerr))
            {
                return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
            }
        }

        $this->load->model('Video_model');
        $key   = $this->r2->buildKey($event['slug'], $event_id, $filename ?: 'video.mp4');
        $extra = array(
            'titre'      => pathinfo($filename, PATHINFO_FILENAME) ?: NULL,
            'size_bytes' => $size,
            'duration_s' => (int) $this->input->post('duration') ?: NULL,
            'largeur'    => (int) $this->input->post('w') ?: NULL,
            'hauteur'    => (int) $this->input->post('h') ?: NULL,
            'ordre'      => $this->Video_model->next_ordre($event_id),
        );

        // Petit fichier : PUT présigné unique (comme les photos).
        if ($size <= (int) $cfg['multipart_threshold'])
        {
            $video_id = $this->Video_model->create_pending($event_id, $album_id, $key, $extra);
            return $this->json(array(
                'ok'         => TRUE,
                'mode'       => 'single',
                'video_id'   => $video_id,
                'key'        => $key,
                'upload_url' => $this->r2->presignUpload($key, $ct, 3600),
            ));
        }

        // Gros fichier : multipart reprenable (parts signées à la demande).
        $upload_id = $this->r2->createMultipart($key, $ct);
        if ( ! $upload_id)
        {
            return $this->json(array('ok' => FALSE, 'error' =>
                'Impossible d\'initier l\'upload multipart (réessayez).'), 502);
        }
        $extra['upload_id'] = $upload_id;
        $video_id = $this->Video_model->create_pending($event_id, $album_id, $key, $extra);

        return $this->json(array(
            'ok'        => TRUE,
            'mode'      => 'multipart',
            'video_id'  => $video_id,
            'key'       => $key,
            'part_size' => (int) $cfg['part_size'],
        ));
    }

    /** (R2) URL présignée pour une part multipart (PUT direct navigateur). */
    public function video_part()
    {
        $this->output->set_content_type('application/json');
        $video = $this->owned_pending_video();
        if ( ! is_array($video)) return $video;

        $part = (int) $this->input->post('part_number');
        if ($part < 1 || $part > 10000 || empty($video['upload_id']))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Part invalide.'), 422);
        }

        return $this->json(array(
            'ok'  => TRUE,
            'url' => $this->r2->presignPart($video['r2_key'], $video['upload_id'], $part, 3600),
        ));
    }

    /**
     * (R2) Finalise un upload multipart : assemble les parts puis marque
     * la vidéo « ready ». $_POST['parts'] = JSON [{PartNumber, ETag}, …].
     */
    public function video_complete()
    {
        $this->output->set_content_type('application/json');
        $video = $this->owned_pending_video();
        if ( ! is_array($video)) return $video;

        $parts = json_decode((string) $this->input->post('parts'), TRUE);
        if (empty($parts) || ! is_array($parts) || empty($video['upload_id']))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Liste des parts manquante.'), 422);
        }

        $clean = array();
        foreach ($parts as $p)
        {
            if ( ! isset($p['PartNumber'], $p['ETag'])) continue;
            $clean[] = array('PartNumber' => (int) $p['PartNumber'], 'ETag' => (string) $p['ETag']);
        }

        if (empty($clean) || ! $this->r2->completeMultipart($video['r2_key'], $video['upload_id'], $clean))
        {
            return $this->json(array('ok' => FALSE, 'error' =>
                'Assemblage de la vidéo échoué (réessayez).'), 502);
        }

        if ( ! $this->verify_video_size($video, $qerr))
        {
            return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
        }

        $this->Video_model->mark_ready($video['id']);
        return $this->json(array('ok' => TRUE, 'url' => $this->r2->publicUrl($video['r2_key'])));
    }

    /** (R2) Confirme un upload vidéo « single » : vérifie l'objet puis ready. */
    public function video_confirm()
    {
        $this->output->set_content_type('application/json');
        $video = $this->owned_pending_video();
        if ( ! is_array($video)) return $video;

        $ex = $this->r2->exists($video['r2_key']);
        if ($ex === NULL)
        {
            return $this->json(array('ok' => FALSE, 'retry' => TRUE, 'error' =>
                'Stockage momentanément injoignable, réessayez.'), 503);
        }
        if ($ex === FALSE)
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Objet absent sur R2.'), 422);
        }

        if ( ! $this->verify_video_size($video, $qerr))
        {
            return $this->json(array('ok' => FALSE, 'error' => $qerr), 422);
        }

        $this->Video_model->mark_ready($video['id']);
        return $this->json(array('ok' => TRUE, 'url' => $this->r2->publicUrl($video['r2_key'])));
    }

    /**
     * Vérifie la taille réelle d'une vidéo sur R2 : corrige size_bytes, et
     * refuse (objet + ligne supprimés) si la taille réelle dépasse le
     * plafond vidéo ou fait déborder le quota de stockage.
     */
    protected function verify_video_size(array $video, &$error = NULL)
    {
        $error = NULL;
        $real  = $this->r2->size($video['r2_key']);
        if ($real === FALSE || $real === NULL || $real <= 0)
        {
            return TRUE; // objet déjà vérifié par exists/complete ; taille indisponible (réseau/404) = on garde la déclarée
        }

        $declared = (int) ($video['size_bytes'] ?? 0);
        if ($real === $declared)
        {
            return TRUE;
        }

        $this->Video_model->set_size($video['id'], $real);

        $cfg = $this->config->item('video');
        if ($real > (int) $cfg['max_bytes'])
        {
            $this->Video_model->delete($video['id']); // supprime aussi l'objet R2
            $error = 'Vidéo trop volumineuse (maximum '.$this->format_go($cfg['max_bytes']).').';
            return FALSE;
        }
        if ($real > $declared && ! $this->is_super() && ! $this->can_store_bytes(0, $qerr))
        {
            $this->Video_model->delete($video['id']);
            $error = $qerr;
            return FALSE;
        }
        return TRUE;
    }

    /** (R2) Abandonne un upload vidéo (nettoie multipart + ligne). */
    public function video_abort()
    {
        $this->output->set_content_type('application/json');
        $video = $this->owned_pending_video();
        if ( ! is_array($video)) return $video;

        $this->Video_model->delete($video['id']); // gère abortMultipart + delete R2
        return $this->json(array('ok' => TRUE));
    }

    /**
     * Charge la vidéo « pending » du POST (video_id) et vérifie l'accès.
     * Retourne la ligne, ou la réponse JSON d'erreur déjà émise.
     */
    protected function owned_pending_video()
    {
        $this->load->library('r2');
        $this->load->model('Video_model');

        $video = $this->Video_model->get((int) $this->input->post('video_id'));
        if ( ! $video || $video['status'] !== 'pending')
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Vidéo introuvable.'), 404);
        }
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($video['event_id'], $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'Non autorisé.'), 403);
        }
        return $video;
    }

    /**
     * Traitement manuel de la file (bouton "Traiter maintenant").
     * Pratique en local (pas de cron) ; en production le cron suffit.
     * Traite un lot borné pour éviter tout timeout HTTP.
     */
    public function process()
    {
        $this->output->set_content_type('application/json');
        $this->load->library('Upload_worker');
        // Cloisonnement : un admin ne traite/révèle que ses propres événements.
        $event_ids = $this->is_super() ? NULL : array_map('intval', array_column($this->accessible_events(), 'id'));
        $res = $this->upload_worker->process_batch(20, NULL, $event_ids);
        return $this->json(array('ok' => TRUE) + $res);
    }

    /**
     * État de la file pour un événement (polling temps réel côté admin).
     */
    public function status($event_id = 0)
    {
        $this->output->set_content_type('application/json');
        $event_id = (int) $event_id;
        if ( ! $this->is_super() && ! $this->Event_model->owned_by($event_id, $this->tenant_id()))
        {
            return $this->json(array('ok' => FALSE, 'error' => 'forbidden'), 403);
        }
        $counts = $this->Upload_job_model->counts($event_id);
        $total_photos = 0;
        $this->load->model('Photo_model');
        $total_photos = $this->Photo_model->count_for_event($event_id);
        return $this->json(array('ok' => TRUE, 'counts' => $counts, 'photos' => $total_photos));
    }

    /* ----------------------------------------------------------------- */

    protected function json($payload, $status = 200)
    {
        $this->output
            ->set_status_header($status)
            ->set_output(json_encode($payload));
    }

    /** Plafond photos/événement du forfait courant (NULL = illimité / super). */
    protected function plan_max_photos()
    {
        if ($this->is_super()) return NULL;
        $plan = $this->current_plan();
        return ($plan && $plan['max_photos'] !== NULL) ? (int) $plan['max_photos'] : NULL;
    }

    protected function ext_for_mime($mime)
    {
        switch ($mime)
        {
            case 'image/png':  return 'png';
            case 'image/heic':
            case 'image/heif': return 'heic';
            default:           return 'jpg';
        }
    }

    protected function upload_error_label($code)
    {
        $map = array(
            UPLOAD_ERR_INI_SIZE   => 'fichier trop volumineux (php.ini)',
            UPLOAD_ERR_FORM_SIZE  => 'fichier trop volumineux (formulaire)',
            UPLOAD_ERR_PARTIAL    => 'upload incomplet',
            UPLOAD_ERR_NO_FILE    => 'aucun fichier',
            UPLOAD_ERR_NO_TMP_DIR => 'pas de dossier temporaire',
            UPLOAD_ERR_CANT_WRITE => 'écriture disque impossible',
        );
        return $map[$code] ?? ('code '.$code);
    }
}
