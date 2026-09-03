<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Media_purger — logique de purge des médias, PARTAGÉE entre :
 *   - le cron  (Cron::purge_media, en CLI, une fois par jour) ;
 *   - le bouton manuel du back-office (admin/System::run_purge).
 *
 * « Purger » = supprimer photos + vidéos (objets R2 inclus) puis passer
 * l'événement en statut 'archive'. L'événement, ses albums et ses réglages
 * sont conservés (trace + réactivation commerciale possible).
 *
 * Trois règles, dans l'ordre :
 *   0. Orphelins R2 : suppressions ayant échoué (réseau) → re-tentées.
 *   1. Uploads « pending » jamais confirmés > 48 h → nettoyés.
 *   2. Rétention de forfait dépassée (retention_days + grâce) → purge.
 *   3. Abonnement terminé depuis > 30 j → purge.
 *
 * Jamais purgés : événements d'un super_admin, événements sans
 * propriétaire, propriétaires avec abonnement actif sans rétention.
 */
class Media_purger {

    /** Marge après la date de rétention du forfait avant purge (jours). */
    const PURGE_GRACE_RETENTION_DAYS = 3;

    /** Marge après l'expiration d'un abonnement avant purge (jours). */
    const PURGE_GRACE_EXPIRED_DAYS = 30;

    /** Ancienneté d'un upload « pending » jamais confirmé avant nettoyage (heures). */
    const STALE_PENDING_HOURS = 48;

    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
    }

    /**
     * Exécute la purge.
     *
     * @param bool          $dry  TRUE = simulation (ne supprime rien).
     * @param callable|null $emit Rappel appelé pour chaque ligne de sortie
     *                            (ex. écriture STDOUT en CLI, collecte en HTTP).
     * @return array  Résumé : orphans_cleaned, orphans_total, stale_cleaned, events_purged.
     */
    public function run($dry = FALSE, $emit = NULL)
    {
        $CI  =& $this->CI;
        $now = time();

        $CI->load->model(array('Photo_model', 'Event_model', 'Video_model', 'Subscription_model', 'Plan_model'));
        $CI->load->library('r2');

        $log = function ($msg) use ($emit) {
            if (is_callable($emit)) call_user_func($emit, $msg);
        };

        $log('=== purge_media ('.($dry ? 'SIMULATION' : 'application').') ===');

        // --- 0) Orphelins R2 : re-tenter les suppressions ratées (réseau). ---
        $orphans = $CI->db->order_by('id', 'ASC')->limit(200)->get('r2_orphans')->result_array();
        $no = 0;
        if ( ! empty($orphans) && $CI->r2->is_configured())
        {
            foreach ($orphans as $o)
            {
                if ($dry) { $no++; continue; }
                $ok = TRUE;
                if ( ! empty($o['upload_id']))
                {
                    $ok = $CI->r2->abortMultipart($o['r2_key'], $o['upload_id']);
                }
                if ($ok && $CI->r2->delete($o['r2_key']))
                {
                    $CI->db->where('id', (int) $o['id'])->delete('r2_orphans');
                    $no++;
                }
                else
                {
                    $CI->db->where('id', (int) $o['id'])
                        ->update('r2_orphans', array('attempts' => (int) $o['attempts'] + 1));
                }
            }
        }
        $log('0) Orphelins R2 nettoyés : '.$no.' / '.count($orphans));

        // --- 1) Uploads directs jamais confirmés (pending > 48 h). ---
        $n = 0;
        foreach ($CI->Photo_model->stale_pending(self::STALE_PENDING_HOURS) as $p)
        {
            $dry ? $n++ : ($CI->Photo_model->delete($p['id']) && $n++);
        }
        foreach ($CI->Video_model->stale_pending(self::STALE_PENDING_HOURS) as $v)
        {
            $dry ? $n++ : ($CI->Video_model->delete($v['id']) && $n++);
        }
        $log("1) Uploads avortés nettoyés : $n");

        // --- 2 & 3) Événements en fin de vie. ---
        $events = $CI->db->select('e.*, u.role AS owner_role')
            ->from('events e')
            ->join('users u', 'u.id = e.user_id') // sans propriétaire = jamais purgé
            ->where('u.role !=', 'super_admin')
            ->get()->result_array();

        $subs = array();  // cache par propriétaire : abonnement actif (ou NULL)
        $plans = array(); // cache des plans
        $purged = 0;

        foreach ($events as $e)
        {
            $uid = (int) $e['user_id'];

            if ( ! array_key_exists($uid, $subs))
            {
                $subs[$uid] = $CI->Subscription_model->active_for_user($uid) ?: NULL;
            }
            $sub = $subs[$uid];

            $reason = NULL;

            if ($sub)
            {
                // Abonnement actif : seule la rétention du plan s'applique.
                $pid = (int) $sub['plan_id'];
                if ( ! isset($plans[$pid])) $plans[$pid] = $CI->Plan_model->get($pid);
                $plan = $plans[$pid];

                if ($plan && $plan['retention_days'] !== NULL)
                {
                    $base = ! empty($e['date_evt']) ? strtotime($e['date_evt']) : strtotime($e['created_at']);
                    $deadline = $base + ((int) $plan['retention_days'] + self::PURGE_GRACE_RETENTION_DAYS) * 86400;
                    if ($now > $deadline)
                    {
                        $reason = 'rétention '.$plan['retention_days'].' j du forfait '.$plan['nom'].' dépassée';
                    }
                }
            }
            else
            {
                // Plus d'abonnement actif : purge 30 j après la fin du dernier.
                $hist = $CI->Subscription_model->for_user($uid);
                $ref = NULL;
                if ( ! empty($hist))
                {
                    $last = $hist[0];
                    $ref = $last['expires_at'] ?: ($last['updated_at'] ?: $last['created_at']);
                }
                else
                {
                    $ref = $e['created_at']; // jamais eu d'abonnement
                }
                if ($ref && $now > strtotime($ref) + self::PURGE_GRACE_EXPIRED_DAYS * 86400)
                {
                    $reason = 'abonnement terminé depuis plus de '.self::PURGE_GRACE_EXPIRED_DAYS.' j';
                }
            }

            if ($reason === NULL)
            {
                continue;
            }

            $photo_ids = $CI->Photo_model->ids_for_event($e['id']);
            $videos    = $CI->Video_model->all_for_event($e['id']);

            if (empty($photo_ids) && empty($videos) && $e['statut'] === 'archive')
            {
                continue; // déjà purgé et archivé
            }

            $log('Purge événement #'.$e['id'].' « '.$e['nom'].' » ('.$reason.') : '
                .count($photo_ids).' photo(s), '.count($videos).' vidéo(s).');

            if ($dry)
            {
                $purged++;
                continue;
            }

            foreach ($photo_ids as $pid2) { $CI->Photo_model->delete($pid2); }
            foreach ($videos as $v)       { $CI->Video_model->delete($v['id']); }

            // Jobs d'upload restants (mode disque) : lignes orphelines.
            $CI->db->where('event_id', (int) $e['id'])->delete('upload_jobs');

            // La galerie publique répond désormais 404.
            $CI->db->where('id', (int) $e['id'])->update('events', array('statut' => 'archive'));
            $purged++;
        }

        $log("2-3) Événements purgés : $purged");
        $log('=== purge_media terminé ===');

        return array(
            'orphans_cleaned' => $no,
            'orphans_total'   => count($orphans),
            'stale_cleaned'   => $n,
            'events_purged'   => $purged,
        );
    }
}
