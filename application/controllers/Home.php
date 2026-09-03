<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Home — site vitrine public d'Archivents (SaaS).
 *
 *   /            -> landing (index)
 *   /pricing     -> grille tarifaire détaillée
 *   /register    -> inscription photographe (GET formulaire, POST création)
 *   /bienvenue   -> confirmation après inscription (registered)
 *
 * Les galeries d'événements restent accessibles via /e/{code}.
 */
class Home extends Public_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Plan_model');
    }

    /** Landing marketing — ou vitrine studio si {studio}.archivents.com. */
    public function index()
    {
        // Sous-domaine {studio}.archivents.com -> page vitrine du photographe.
        $studio = $this->studio_from_host();
        if (is_array($studio))
        {
            return $this->studio_page($studio);
        }
        if ($studio === FALSE)
        {
            // Sous-domaine inconnu : retour au site principal.
            redirect('https://archivents.com');
        }

        // Lien « Voir la démo » : galerie exemple publique (mariage Matuta).
        $demo_url = 'http://matuta.archivents.com/';

        $data = $this->plan_view_data() + array('active' => 'home', 'demo_url' => $demo_url);
        $this->render('site/landing', $data);
    }

    /* -----------------------------------------------------------------
     |  Sous-domaines studio ({studio}.archivents.com)
     | ----------------------------------------------------------------- */

    /**
     * Résout l'hôte de la requête en studio :
     *   NULL  = domaine principal / www / local -> comportement normal ;
     *   array = utilisateur photographe correspondant au sous-domaine ;
     *   FALSE = sous-domaine sans studio correspondant.
     */
    protected function studio_from_host()
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $host = preg_replace('/:\d+$/', '', $host);        // retire le port

        $suffix = '.archivents.com';
        if ($host === '' || substr($host, -strlen($suffix)) !== $suffix)
        {
            return NULL;                                   // apex, local, autre domaine
        }

        $sub = substr($host, 0, -strlen($suffix));
        if ($sub === '' || strpos($sub, '.') !== FALSE)
        {
            return NULL;                                   // apex ou multi-niveaux
        }

        // Sous-domaines techniques : jamais des studios.
        $reserved = array('www', 'cdn', 'mail', 'webmail', 'cpanel', 'ftp',
                          'api', 'autodiscover', 'autoconfig', 'ns1', 'ns2');
        if (in_array($sub, $reserved, TRUE))
        {
            return NULL;
        }

        $user = $this->db->where('studio_slug', $sub)
            ->where('role', 'photographe')
            ->where('actif', 1)
            ->get('users')->row_array();

        return $user ?: FALSE;
    }

    /**
     * Vitrine minimale d'un studio. VOLONTAIREMENT sans liste de galeries :
     * les liens /e/{code} sont non devinables par conception (confidentialité
     * des mariages) — la page ne doit pas les divulguer.
     */
    protected function studio_page(array $owner)
    {
        $this->load->model(array('Subscription_model', 'Plan_model'));

        $show_branding = TRUE;
        $sub = $this->Subscription_model->active_for_user((int) $owner['id']);
        if ($sub)
        {
            $plan = $this->Plan_model->get($sub['plan_id']);
            if ($plan && (int) $plan['remove_branding'] === 1)
            {
                $show_branding = FALSE;
            }
        }

        $this->load->view('site/studio', array(
            'studio'        => $owner,
            'show_branding' => $show_branding,
        ));
    }

    /** Page tarifs détaillée. */
    public function pricing()
    {
        $data = $this->plan_view_data() + array('active' => 'pricing');
        $this->render('site/pricing', $data);
    }

    /**
     * Inscription photographe (self-service).
     * GET  : affiche le formulaire (plan présélectionné via ?plan=slug).
     * POST : valide, crée le compte + abonnement/paiement « en attente »,
     *        connecte l'utilisateur puis redirige vers la confirmation.
     */
    public function register()
    {
        // Déjà connecté ? -> son espace.
        if ($this->session->userdata('logged_in'))
        {
            redirect('admin/dashboard');
        }

        // Résout le forfait choisi (CTA d'une carte tarif) ; par défaut = Gratuit.
        // Aucun menu déroulant : le plan est présenté en résumé (+ lien « changer »).
        $chosen = $this->input->post('plan_slug', TRUE) ?: $this->input->get('plan');
        $plan   = $chosen ? $this->Plan_model->get_by_slug($chosen) : NULL;
        if ( ! $plan || empty($plan['actif']))
        {
            $plan = $this->Plan_model->get_by_slug('gratuit');
        }
        $is_free = ($plan['billing_period'] === 'free' || (int) $plan['prix'] === 0);

        $data = array(
            'active' => 'register',
            'plan'   => $plan,
            'errors' => array(),
            'old'    => array('nom' => '', 'email' => '', 'studio_slug' => ''),
        );

        if ($this->input->method() === 'post')
        {
            $nom     = trim((string) $this->input->post('nom', TRUE));
            $email   = strtolower(trim((string) $this->input->post('email', TRUE)));
            $pass    = (string) $this->input->post('password', FALSE);
            $pass2   = (string) $this->input->post('password2', FALSE);
            $slug_in = (string) $this->input->post('studio_slug', TRUE);
            $slug    = $this->slugify($slug_in !== '' ? $slug_in : $nom);

            $data['old'] = array('nom' => $nom, 'email' => $email, 'studio_slug' => $slug);

            $this->load->model('User_model');
            $errors = array();

            if ($nom === '' || mb_strlen($nom) < 2)      $errors['nom'] = 'Indiquez le nom de votre studio.';
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL))
                                                          $errors['email'] = 'Adresse e-mail invalide.';
            elseif ($this->User_model->email_exists($email))
                                                          $errors['email'] = 'Un compte existe déjà avec cet e-mail.';
            if (strlen($pass) < 8)                        $errors['password'] = 'Au moins 8 caractères.';
            if ($pass !== $pass2)                         $errors['password2'] = 'Les mots de passe ne correspondent pas.';

            if ($slug === '' || strlen($slug) < 3)        $errors['studio_slug'] = 'Choisissez un identifiant (3 caractères minimum, lettres/chiffres).';
            elseif ($this->User_model->studio_slug_exists($slug))
                                                          $errors['studio_slug'] = 'Cet identifiant est déjà pris.';

            if ( ! $this->input->post('cgu'))             $errors['cgu'] = 'Vous devez accepter les Conditions et la Politique de confidentialité.';

            if (empty($errors))
            {
                $this->load->model(array('Subscription_model', 'Payment_model'));

                $user_id = $this->User_model->create(array(
                    'nom'             => $nom,
                    'email'           => $email,
                    'password'        => $pass,           // hashé par le modèle
                    'role'            => 'photographe',
                    'actif'           => 1,
                    'studio_slug'     => $slug,
                    'email_verified'  => 0,
                    'current_plan_id' => $plan['id'],
                ));

                // Gratuit : abonnement ACTIF tout de suite (pas de paiement).
                // Payant : abonnement « en attente » (paiement / activation manuelle).
                $sub_id = $this->Subscription_model->create(array(
                    'user_id'      => $user_id,
                    'plan_id'      => $plan['id'],
                    'statut'       => $is_free ? 'actif' : 'en_attente',
                    'started_at'   => $is_free ? date('Y-m-d H:i:s') : NULL,
                    'events_quota' => $plan['max_events'],   // NULL = illimité
                    'note'         => $is_free ? 'Plan gratuit — activation automatique' : 'Inscription self-service',
                ));

                // Trace de paiement uniquement pour un forfait payant.
                if ( ! $is_free)
                {
                    $this->Payment_model->create(array(
                        'user_id'         => $user_id,
                        'subscription_id' => $sub_id,
                        'plan_id'         => $plan['id'],
                        'montant'         => $plan['prix'],
                        'devise'          => $plan['devise'],
                        'methode'         => 'manuel',
                        'statut'          => 'en_attente',
                        'note'            => 'À encaisser / activation manuelle',
                    ));
                }

                $this->User_model->update($user_id, array('current_subscription_id' => $sub_id));

                // Connexion immédiate (même mécanique que admin/Auth).
                $this->session->sess_regenerate(TRUE);
                $this->session->set_userdata(array(
                    'logged_in'  => TRUE,
                    'user_id'    => $user_id,
                    'user_nom'   => $nom,
                    'user_email' => $email,
                    'user_role'  => 'photographe',
                ));

                // E-mail de bienvenue + lien de confirmation d'adresse
                // (no-op si SMTP non configuré).
                $this->load->library('Mailer');
                $this->mailer->welcome($email, $nom, $is_free, $plan['nom'], $slug,
                    $this->mailer->email_verify_url($user_id, $email));

                // Alerte opérateur : pour un forfait payant, c'est le signal
                // « encaisser puis activer dans admin → Abonnements ».
                $this->mailer->operator_new_signup($nom, $email, $plan['nom'],
                    $plan['prix'], $plan['devise'], $is_free, $slug);

                $this->session->set_flashdata('registered', array(
                    'nom'         => $nom,
                    'studio_slug' => $slug,
                    'plan_nom'    => $plan['nom'],
                    'plan_prix'   => $plan['prix'],
                    'plan_devise' => $plan['devise'],
                    'is_free'     => $is_free,
                ));
                redirect('bienvenue');
            }

            $data['errors'] = $errors;
        }

        $this->render('site/register', $data);
    }

    /**
     * Contrôle de santé (/ping) : si cette page répond, les réécritures
     * .htaccess, PHP et les routes fonctionnent ; « db=ok » confirme la base.
     * Aucune information sensible n'est exposée.
     */
    public function ping()
    {
        $db_ok = FALSE;
        try
        {
            $db_ok = (bool) $this->db->query('SELECT 1')->row_array();
        }
        catch (Throwable $e)
        {
            $db_ok = FALSE;
        }
        $this->output
            ->set_content_type('text/plain')
            ->set_output('pong | env='.ENVIRONMENT.' | rewrite=ok | db='.($db_ok ? 'ok' : 'FAIL'));
    }

    /**
     * Confirmation d'adresse e-mail (lien reçu par e-mail).
     * Jeton HMAC déterministe : aucune table, invalidé si l'adresse change.
     */
    public function verify_email($user_id, $token)
    {
        $this->load->model('User_model');
        $this->load->library('Mailer');

        $user = $this->User_model->get((int) $user_id);
        $ok = $user
            && hash_equals($this->mailer->email_verify_token($user['id'], $user['email']), (string) $token);

        if ($ok && empty($user['email_verified']))
        {
            $this->User_model->update($user['id'], array('email_verified' => 1));
        }

        $this->render('site/verify_email', array(
            'active'  => '',
            'ok'      => (bool) $ok,
            'already' => $ok && ! empty($user['email_verified']),
        ));
    }

    /** Mentions légales. */
    public function mentions()
    {
        $this->render('site/legal/mentions', array('active' => ''));
    }

    /** Politique de confidentialité. */
    public function confidentialite()
    {
        $this->render('site/legal/confidentialite', array('active' => ''));
    }

    /** Conditions Générales d'Utilisation et de Vente (le règlement). */
    public function conditions()
    {
        $this->render('site/legal/conditions', array('active' => ''));
    }

    /** Confirmation après inscription (lit le flashdata posé par register()). */
    public function registered()
    {
        $info = $this->session->flashdata('registered');
        if ( ! $info)
        {
            redirect(site_url());
        }
        $this->render('site/register_done', array('active' => 'register', 'info' => $info));
    }

    /* =================================================================
     |  Helpers
     | ================================================================= */

    /** Transforme une chaîne en identifiant d'URL (a-z 0-9 tirets). */
    protected function slugify($s)
    {
        $s = trim((string) $s);
        if (function_exists('iconv'))
        {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($t !== FALSE) { $s = $t; }
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = preg_replace('/-{2,}/', '-', $s);
        return trim($s, '-');
    }

    /**
     * Prépare les données de plans pour les vues :
     *  - $tiers : paliers d'abonnement (essentiel/studio/signature) avec
     *             leurs variantes mensuelle/annuelle indexées ;
     *  - $pass  : le Pass Événement (paiement unique).
     */
    protected function plan_view_data()
    {
        $grouped = $this->Plan_model->grouped_by_tier(TRUE);

        $tiers = array();
        foreach (array('gratuit', 'essentiel', 'studio', 'signature') as $tier)
        {
            if (empty($grouped[$tier]))
            {
                continue;
            }
            $byperiod = array();
            foreach ($grouped[$tier] as $p)
            {
                $byperiod[$p['billing_period']] = $p;
            }
            $tiers[$tier] = $byperiod; // ['monthly'=>plan, 'yearly'=>plan]
        }

        $pass = isset($grouped['pass'][0]) ? $grouped['pass'][0] : NULL;

        return array('tiers' => $tiers, 'pass' => $pass);
    }

    /** Assemble une page vitrine (layout dédié, indépendant de la galerie). */
    protected function render($view, $data)
    {
        $this->load->view('site/layout/header', $data);
        $this->load->view($view, $data);
        $this->load->view('site/layout/footer', $data);
    }
}
