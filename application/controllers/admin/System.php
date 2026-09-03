<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * admin/System — monitoring d'exploitation (réservé au super admin).
 *
 * Page en LECTURE SEULE qui répond à « est-ce que tout tourne ? » :
 *  - les tâches cron ont-elles tourné (cron_log) et quand ;
 *  - santé de la plateforme (stockage R2, SMTP, transformations, disque) ;
 *  - stockage consommé vs palier gratuit R2 (10 Go) + top clients ;
 *  - files en attente (uploads pending, orphelins R2, jobs disque) ;
 *  - abonnements à traiter / sur le point d'expirer ;
 *  - dernières erreurs PHP (application/logs).
 */
class System extends Admin_Controller {

    /** Palier gratuit Cloudflare R2 (octets). */
    const R2_FREE_BYTES = 10737418240; // 10 Go

    /** Au-delà de ce délai sans exécution, une tâche quotidienne est « en retard ». */
    const DAILY_TASK_STALE_HOURS = 26;

    public function __construct()
    {
        parent::__construct();
        $this->require_permission('system.monitor'); // seul le super admin ('*') la possède
        $this->load->model(array('Photo_model', 'Video_model'));
    }

    public function index()
    {
        $this->render('admin/system/index', array(
            'cron'    => $this->cron_status(),
            'logs'    => $this->db->order_by('finished_at', 'DESC')->limit(15)->get('cron_log')->result_array(),
            'health'  => $this->health(),
            'storage' => $this->storage(),
            'queues'  => $this->queues(),
            'subs'    => $this->subscriptions(),
            'errors'  => $this->php_errors(),
        ));
    }

    /* -----------------------------------------------------------------
     |  Collecteurs
     | ----------------------------------------------------------------- */

    /** Dernière exécution de chaque tâche + fraîcheur. */
    protected function cron_status()
    {
        $tasks = array(
            'purge_media'         => 'Purge médias (rétention, uploads avortés, orphelins R2)',
            'subscription_alerts' => 'Alertes d\'abonnement (rappels J-7/J-1, expirations)',
            'purge_originals'     => 'Purge des originaux (pipeline disque)',
            'process_uploads'     => 'Traitement des uploads (pipeline disque)',
        );

        $out = array();
        foreach ($tasks as $task => $label)
        {
            $last = $this->db->where('task', $task)
                ->order_by('finished_at', 'DESC')->limit(1)
                ->get('cron_log')->row_array();

            $state = 'never'; // jamais tournée
            if ($last)
            {
                $age_h = (time() - strtotime($last['finished_at'])) / 3600;
                $state = ($age_h <= self::DAILY_TASK_STALE_HOURS) ? 'ok' : 'stale';
                if ( ! (int) $last['ok']) $state = 'error';
            }

            $out[] = array(
                'task'  => $task,
                'label' => $label,
                'last'  => $last,
                'state' => $state,
            );
        }
        return $out;
    }

    /** Drapeaux de santé de la plateforme. */
    protected function health()
    {
        $this->load->library(array('r2', 'Mailer'));

        $free  = @disk_free_space(FCPATH);
        $total = @disk_total_space(FCPATH);

        return array(
            'env'            => ENVIRONMENT,
            'php'            => PHP_VERSION,
            'storage_driver' => getenv('ARCHIVENTS_STORAGE') ?: 'local',
            'r2_configured'  => $this->r2->is_configured(),
            'cf_transform'   => (getenv('CF_TRANSFORM') === '1'),
            'smtp'           => $this->mailer->is_configured(),
            'disk_free'      => $free !== FALSE ? (float) $free : NULL,
            'disk_total'     => $total !== FALSE ? (float) $total : NULL,
        );
    }

    /** Stockage consommé (photos + vidéos) et top clients. */
    protected function storage()
    {
        $p = $this->db->select('COUNT(*) n, COALESCE(SUM(size_bytes),0) b')
            ->get('photos')->row_array();
        $v = $this->db->select('COUNT(*) n, COALESCE(SUM(size_bytes),0) b')
            ->get('videos')->row_array();

        // Top tenants par octets (photos + vidéos rattachées à leurs événements).
        $top = $this->db->query("
            SELECT u.id, u.nom, u.email,
                   COALESCE(SUM(t.b), 0) AS bytes, SUM(t.n) AS objets
            FROM users u
            JOIN events e ON e.user_id = u.id
            JOIN (
                SELECT event_id, COUNT(*) n, COALESCE(SUM(size_bytes),0) b FROM photos GROUP BY event_id
                UNION ALL
                SELECT event_id, COUNT(*) n, COALESCE(SUM(size_bytes),0) b FROM videos GROUP BY event_id
            ) t ON t.event_id = e.id
            GROUP BY u.id, u.nom, u.email
            ORDER BY bytes DESC
            LIMIT 5
        ")->result_array();

        $used = (float) $p['b'] + (float) $v['b'];

        return array(
            'photos_n' => (int) $p['n'],
            'videos_n' => (int) $v['n'],
            'bytes'    => $used,
            'free_cap' => self::R2_FREE_BYTES,
            'pct'      => min(100, round($used / self::R2_FREE_BYTES * 100, 1)),
            'top'      => $top,
        );
    }

    /** Files d'attente et anomalies à surveiller. */
    protected function queues()
    {
        return array(
            'photos_pending' => (int) $this->db->where('status', 'pending')->count_all_results('photos'),
            'videos_pending' => (int) $this->db->where('status', 'pending')->count_all_results('videos'),
            'orphans'        => (int) $this->db->count_all_results('r2_orphans'),
            'orphans_stuck'  => (int) $this->db->where('attempts >=', 3)->count_all_results('r2_orphans'),
            'jobs_pending'   => (int) $this->db->where_in('statut', array('pending', 'processing'))
                                    ->count_all_results('upload_jobs'),
        );
    }

    /** Abonnements : à activer, actifs, expirant sous 7 jours. */
    protected function subscriptions()
    {
        $expiring = $this->db->select('s.id, s.expires_at, u.nom, u.email, p.nom AS plan_nom')
            ->from('subscriptions s')
            ->join('users u', 'u.id = s.user_id')
            ->join('plans p', 'p.id = s.plan_id')
            ->where('s.statut', 'actif')
            ->where('s.expires_at IS NOT NULL', NULL, FALSE)
            ->where('s.expires_at <=', date('Y-m-d H:i:s', time() + 7 * 86400))
            ->order_by('s.expires_at', 'ASC')
            ->get()->result_array();

        return array(
            'pending'          => (int) $this->db->where('statut', 'en_attente')->count_all_results('subscriptions'),
            'active'           => (int) $this->db->where('statut', 'actif')->count_all_results('subscriptions'),
            'payments_pending' => (int) $this->db->where('statut', 'en_attente')->count_all_results('payments'),
            'expiring'         => $expiring,
        );
    }

    /**
     * Dernières erreurs PHP consignées par CodeIgniter (application/logs).
     * On lit le fichier du jour puis celui de la veille, max 20 lignes ERROR.
     */
    protected function php_errors($max = 20)
    {
        $lines = array();
        foreach (array(date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))) as $day)
        {
            $file = APPPATH.'logs/log-'.$day.'.php';
            if ( ! is_file($file)) continue;

            foreach (array_reverse(file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) as $l)
            {
                if (strpos($l, 'ERROR') === 0)
                {
                    $lines[] = $l;
                    if (count($lines) >= $max) break 2;
                }
            }
        }
        return $lines;
    }

    /**
     * Lance la purge des médias À LA MAIN depuis le back-office (super admin).
     * Filet de sécurité si le cron n'est pas (encore) programmé sur l'hébergement.
     *
     * POST admin/system/run_purge  (jeton CSRF via form_open) :
     *   - mode=dry   → simulation (n'efface rien, montre ce qui serait purgé) ;
     *   - mode=apply → purge réelle.
     * Utilise EXACTEMENT le même code que le cron (bibliothèque Media_purger)
     * et journalise l'exécution dans cron_log (traçabilité identique).
     */
    public function run_purge()
    {
        if ($this->input->method() !== 'post')
        {
            redirect('admin/system');
        }

        $dry = ($this->input->post('mode') === 'dry');
        $t0  = time();

        $lines = array();
        $this->load->library('Media_purger');
        $summary = $this->media_purger->run($dry, function ($m) use (&$lines) { $lines[] = $m; });

        // Trace dans cron_log, comme une exécution planifiée (préfixe [manuel]).
        $this->db->insert('cron_log', array(
            'task'       => 'purge_media',
            'ok'         => 1,
            'output'     => '[manuel] '.implode("\n", array_slice($lines, 0, 100)),
            'started_at' => date('Y-m-d H:i:s', $t0),
        ));

        $resume = ($dry ? 'Simulation' : 'Purge')
            .' terminée — orphelins R2 : '.$summary['orphans_cleaned'].'/'.$summary['orphans_total']
            .', uploads avortés : '.$summary['stale_cleaned']
            .', événements purgés : '.$summary['events_purged'].'.';

        $this->session->set_flashdata($dry ? 'ok' : 'ok', $resume);
        redirect('admin/system');
    }

    /* ----------------------------------------------------------------- */

    protected function render($view, $data)
    {
        $data['current_user'] = $this->current_user;
        $this->load->view('admin/layout/header', array('title' => 'Système', 'user' => $this->current_user));
        $this->load->view($view, $data);
        $this->load->view('admin/layout/footer');
    }
}
