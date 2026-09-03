<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Cron — tâches planifiées, appelables UNIQUEMENT en CLI.
 *
 * O2Switch (crontab) :
 *   * * * * *   /usr/local/bin/php /home/USER/public_html/index.php cron process_uploads >> /home/USER/cron_uploads.log 2>&1
 *   30 3 * * *  /usr/local/bin/php /home/USER/public_html/index.php cron purge_originals >> /home/USER/cron_purge.log 2>&1
 *   15 4 * * *  /usr/local/bin/php /home/USER/public_html/index.php cron purge_media    >> /home/USER/cron_purge.log 2>&1
 *   0 8 * * *   /usr/local/bin/php /home/USER/public_html/index.php cron subscription_alerts >> /home/USER/cron_alerts.log 2>&1
 *
 * Test sans rien supprimer : php index.php cron purge_media dry
 *                            php index.php cron subscription_alerts dry
 */
class Cron extends CI_Controller {

    /** Marge après la date de rétention du forfait avant purge (jours). */
    const PURGE_GRACE_RETENTION_DAYS = 3;

    /** Marge après l'expiration d'un abonnement avant purge (jours). */
    const PURGE_GRACE_EXPIRED_DAYS = 30;

    /** Ancienneté d'un upload « pending » jamais confirmé avant nettoyage (heures). */
    const STALE_PENDING_HOURS = 48;

    public function __construct()
    {
        parent::__construct();

        // Verrou strict : refuse tout accès HTTP.
        if ( ! is_cli())
        {
            show_error('Cette route est réservée à la ligne de commande (CLI).', 403, 'Accès refusé');
        }

        $this->load->model('Photo_model');
    }

    /**
     * Test d'envoi SMTP (le « @ » étant interdit dans l'URI CLI, l'adresse
     * passe par une variable d'environnement) :
     *   TEST_EMAIL=adresse@exemple.com php index.php cron test_email
     */
    public function test_email($to = '')
    {
        if ($to === '') $to = (string) getenv('TEST_EMAIL');
        $this->load->library('Mailer');
        if ( ! $this->mailer->is_configured())
        {
            $this->out('SMTP NON configuré (SMTP_PASS manquant dans .env).');
            return;
        }
        if ( ! filter_var($to, FILTER_VALIDATE_EMAIL))
        {
            $this->out('Usage : php index.php cron test_email adresse@exemple.com');
            return;
        }
        $ok = $this->mailer->send($to, 'Test SMTP Archivents',
            '<p>Ceci est un e-mail de test envoyé le '.date('d/m/Y à H:i').'.</p>'
            .'<p>Si vous le lisez, l\'envoi SMTP d\'Archivents fonctionne. ✔</p>');
        $this->out($ok ? 'Envoyé à '.$to.' — vérifiez la boîte (et les indésirables).'
                       : 'ÉCHEC d\'envoi — voir application/logs.');
    }

    /**
     * Traite les jobs d'upload "pending".
     * À lancer chaque minute. Robuste : une image en échec ne bloque pas le lot.
     *
     * @param int $batch Nombre maximum de jobs traités par exécution.
     */
    public function process_uploads($batch = 30)
    {
        $this->load->library('Upload_worker');
        $this->upload_worker->process_batch(max(1, (int) $batch), array($this, 'out'));
    }

    /**
     * Purge les originaux dont la date de rétention est dépassée.
     * Les 3 WebP sont conservés indéfiniment. À lancer une fois par jour.
     */
    public function purge_originals()
    {
        $t0 = time();
        $rows = $this->Photo_model->due_for_purge(1000);

        if (empty($rows))
        {
            $this->out('Aucun original à purger.');
            $this->record('purge_originals', $t0);
            return;
        }

        $this->out('Purge de '.count($rows).' original(aux)...');

        $done = 0;
        foreach ($rows as $r)
        {
            $path = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $r['path_original']);
            if (is_file($path))
            {
                @unlink($path);
            }
            $this->Photo_model->mark_purged($r['id']);
            $done++;
        }

        $this->out("Terminé : $done original(aux) purgé(s).");
        $this->record('purge_originals', $t0);
    }

    /**
     * PURGE des médias (maîtrise des coûts de stockage R2/disque).
     * À lancer une fois par jour. Trois règles, dans l'ordre :
     *
     *  1. NETTOYAGE : photos/vidéos « pending » jamais confirmées depuis
     *     plus de 48 h -> objet R2 + ligne supprimés (uploads avortés).
     *
     *  2. RÉTENTION DE FORFAIT : le plan du propriétaire a retention_days
     *     (Test = 7 j, Ponctuel = 15 j) -> l'événement est purgé
     *     retention_days + 3 j de grâce après sa date (ou sa création).
     *
     *  3. ABONNEMENT TERMINÉ : le propriétaire n'a plus d'abonnement actif
     *     depuis plus de 30 j -> tous ses événements sont purgés.
     *
     * « Purger » = supprimer photos + vidéos (objets R2 et fichiers disque
     * inclus) puis passer l'événement en statut 'archive' (la galerie
     * publique répond alors 404). L'événement, ses albums et ses réglages
     * sont conservés (trace + réactivation commerciale possible).
     *
     * Jamais purgés : événements d'un super_admin, événements sans
     * propriétaire, propriétaires avec abonnement actif sans rétention.
     *
     * @param string $mode 'apply' (défaut) ou 'dry' = simulation sans suppression.
     */
    public function purge_media($mode = 'apply')
    {
        $t0  = time();
        $dry = ($mode === 'dry');

        // La logique est partagée avec le bouton manuel du back-office.
        $this->load->library('Media_purger');
        $this->media_purger->run($dry, array($this, 'out'));

        if ( ! $dry)
        {
            $this->record('purge_media', $t0);
        }
    }

    /**
     * ALERTES D'ABONNEMENT (e-mails automatiques). À lancer une fois par
     * jour, le matin (ex. 8 h) :
     *
     *  - RAPPEL J-7 : abonnement actif expirant sous 7 jours -> e-mail
     *    « pensez à renouveler » (une seule fois, drapeau notif_j7).
     *  - RAPPEL J-1 : expirant sous 24 h -> e-mail « dernier jour »
     *    (une seule fois, drapeau notif_j1).
     *  - EXPIRATION : échéance passée -> statut passé à 'expire' + e-mail
     *    « 30 jours pour renouveler avant suppression » (drapeau
     *    notif_expired). La purge effective reste du ressort de
     *    purge_media (30 j de grâce).
     *
     * Les drapeaux sont remis à zéro à chaque activation/prolongation
     * (Subscription_model::activate, admin/Subscriptions::extend) : un
     * abonnement renouvelé recevra à nouveau ses rappels au cycle suivant.
     * Sans SMTP configuré, les e-mails sont ignorés mais le passage en
     * statut 'expire' est tout de même appliqué.
     *
     * @param string $mode 'apply' (défaut) ou 'dry' = liste sans envoyer ni modifier.
     */
    public function subscription_alerts($mode = 'apply')
    {
        $t0  = time();
        $dry = ($mode === 'dry');
        $this->load->library('Mailer');
        $now = time();

        $this->out('=== subscription_alerts ('.($dry ? 'SIMULATION' : 'application').') ===');

        // Abonnements actifs à échéance, enrichis (photographe + plan).
        $subs = $this->db
            ->select('s.id, s.expires_at, s.notif_j7, s.notif_j1, s.notif_expired,
                      u.nom AS user_nom, u.email AS user_email, p.nom AS plan_nom')
            ->from('subscriptions s')
            ->join('users u', 'u.id = s.user_id')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('s.statut', 'actif')
            ->where('s.expires_at IS NOT NULL', NULL, FALSE)
            ->where('s.expires_at <=', date('Y-m-d H:i:s', $now + 7 * 86400))
            ->get()->result_array();

        $sent = array('j7' => 0, 'j1' => 0, 'expired' => 0);

        foreach ($subs as $s)
        {
            $exp       = strtotime($s['expires_at']);
            $days_left = (int) ceil(($exp - $now) / 86400);

            if ($exp <= $now)
            {
                // Échéance passée : statut + e-mail (une seule fois).
                $this->out('EXPIRÉ : '.$s['user_email'].' ('.$s['plan_nom'].', échéance '.$s['expires_at'].')');
                if ($dry) { $sent['expired']++; continue; }

                $upd = array('statut' => 'expire');
                if ( ! (int) $s['notif_expired'])
                {
                    // Drapeau posé seulement si l'envoi a réussi : en cas de
                    // panne SMTP transitoire, on retentera au prochain passage.
                    if ($this->mailer->expired($s['user_email'], $s['user_nom'], $s['plan_nom']))
                    {
                        $upd['notif_expired'] = 1;
                        $sent['expired']++;
                    }
                }
                $this->db->where('id', (int) $s['id'])->update('subscriptions', $upd);
            }
            elseif ($days_left <= 1 && ! (int) $s['notif_j1'])
            {
                $this->out('J-1 : '.$s['user_email'].' ('.$s['plan_nom'].', échéance '.$s['expires_at'].')');
                if ($dry) { $sent['j1']++; continue; }

                if ($this->mailer->expiring($s['user_email'], $s['user_nom'], $s['plan_nom'], $s['expires_at'], $days_left))
                {
                    $this->db->where('id', (int) $s['id'])
                        ->update('subscriptions', array('notif_j1' => 1, 'notif_j7' => 1));
                    $sent['j1']++;
                }
            }
            elseif ($days_left <= 7 && ! (int) $s['notif_j7'])
            {
                $this->out('J-7 : '.$s['user_email'].' ('.$s['plan_nom'].', échéance '.$s['expires_at'].')');
                if ($dry) { $sent['j7']++; continue; }

                if ($this->mailer->expiring($s['user_email'], $s['user_nom'], $s['plan_nom'], $s['expires_at'], $days_left))
                {
                    $this->db->where('id', (int) $s['id'])->update('subscriptions', array('notif_j7' => 1));
                    $sent['j7']++;
                }
            }
        }

        $this->out('Rappels J-7 : '.$sent['j7'].' · J-1 : '.$sent['j1'].' · expirations : '.$sent['expired']);
        $this->out('=== subscription_alerts terminé ===');
        if ( ! $dry)
        {
            $this->record('subscription_alerts', $t0);
        }
    }

    /* ----------------------------------------------------------------- */

    /** @var string[] Lignes émises pendant la tâche (pour cron_log). */
    protected $out_buffer = array();

    public function out($msg)
    {
        $this->out_buffer[] = $msg;
        fwrite(STDOUT, '['.date('Y-m-d H:i:s').'] '.$msg.PHP_EOL);
    }

    /**
     * Journalise l'exécution d'une tâche dans cron_log (page admin Système :
     * on voit QUAND chaque purge a tourné et ce qu'elle a fait).
     */
    protected function record($task, $started_at, $ok = TRUE)
    {
        $this->db->insert('cron_log', array(
            'task'       => $task,
            'ok'         => $ok ? 1 : 0,
            'output'     => implode("\n", array_slice($this->out_buffer, 0, 100)),
            'started_at' => date('Y-m-d H:i:s', $started_at),
        ));
        // Garde 90 jours d'historique.
        $this->db->where('finished_at <', date('Y-m-d H:i:s', time() - 90 * 86400))->delete('cron_log');
    }
}
