<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller
 * --------------------------------------------------------------------
 * Contrôleurs de base de l'application Archiven (CodeIgniter 3).
 *
 *  - MY_Controller     : base commune (HTTP uniquement).
 *  - Public_Controller : site public (invités, pas de compte).
 *  - Admin_Controller  : back-office — impose login + contrôle de rôle
 *                        via un système de permissions extensible.
 *
 * Les rôles sont stockés en base (table `roles`) et la matrice de
 * permissions est définie dans config/app_config.php
 * ($config['role_permissions']).
 */
class MY_Controller extends CI_Controller {

    public function __construct()
    {
        parent::__construct();

        // Bloque l'accès HTTP aux contrôleurs prévus pour la CLI.
        // (Les contrôleurs CLI vérifieront is_cli() de leur côté.)
    }
}

/* ===================================================================== */

/**
 * Public_Controller — base du site public (galerie invités).
 * Pas d'authentification : l'accès se fait via le slug de l'événement
 * (le mot de passe optionnel par événement est géré au niveau galerie).
 */
class Public_Controller extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        // Helpers/Modèles communs au public seront chargés ici aux phases suivantes.
    }
}

/* ===================================================================== */

/**
 * Admin_Controller — base du back-office.
 * Impose une session authentifiée et fournit le contrôle de permissions.
 */
class Admin_Controller extends MY_Controller {

    /** @var array|null Données de l'utilisateur connecté */
    protected $current_user = NULL;

    public function __construct()
    {
        parent::__construct();

        // Exige une session valide pour tout le back-office.
        if ( ! $this->session->userdata('logged_in'))
        {
            // Mémorise la cible pour redirection après login.
            $this->session->set_userdata('redirect_after_login', current_url());
            redirect('admin/auth/login');
        }

        $this->current_user = array(
            'id'        => $this->session->userdata('user_id'),
            'nom'       => $this->session->userdata('user_nom'),
            'email'     => $this->session->userdata('user_email'),
            'role'      => $this->session->userdata('user_role'),
            // Collaborateur : id du titulaire du compte (sinon NULL).
            'parent_id' => $this->session->userdata('user_parent'),
        );
    }

    /**
     * Id du TITULAIRE (tenant) : le photographe lui-même, ou — pour un
     * collaborateur — le photographe qui l'a invité. Toute la donnée
     * (événements, quotas, abonnement) est rattachée au titulaire.
     */
    protected function tenant_id()
    {
        $p = (int) ($this->current_user['parent_id'] ?? 0);
        return $p > 0 ? $p : (int) $this->current_user['id'];
    }

    /* -----------------------------------------------------------------
     |  Système de permissions (extensible)
     | ----------------------------------------------------------------- */

    /**
     * L'utilisateur courant possède-t-il la permission donnée ?
     * '*' dans la liste du rôle = toutes les permissions (super_admin).
     */
    protected function has_permission($permission)
    {
        $role = isset($this->current_user['role']) ? $this->current_user['role'] : NULL;
        if ($role === NULL)
        {
            return FALSE;
        }

        $matrix = $this->config->item('role_permissions');
        if ( ! isset($matrix[$role]))
        {
            return FALSE;
        }

        $perms = $matrix[$role];
        return in_array('*', $perms, TRUE) || in_array($permission, $perms, TRUE);
    }

    /**
     * Exige une permission ; sinon coupe l'accès (403).
     * À appeler en début de méthode sensible.
     */
    protected function require_permission($permission)
    {
        if ( ! $this->has_permission($permission))
        {
            show_error(
                'Accès refusé : permission « '.html_escape($permission).' » requise.',
                403,
                'Accès refusé'
            );
        }
    }

    /**
     * Raccourci : exige le rôle super_admin.
     */
    protected function require_super_admin()
    {
        if (($this->current_user['role'] ?? NULL) !== 'super_admin')
        {
            show_error('Réservé au super administrateur.', 403, 'Accès refusé');
        }
    }

    /* -----------------------------------------------------------------
     |  Cloisonnement par événement (multi-tenant)
     |  super_admin : accès à tout.
     |  admin       : uniquement les événements qui lui sont attribués.
     | ----------------------------------------------------------------- */

    /** L'utilisateur courant est-il super_admin ? */
    protected function is_super()
    {
        return ($this->current_user['role'] ?? NULL) === 'super_admin';
    }

    /**
     * Liste des événements accessibles à l'utilisateur courant
     * (tous pour super_admin, sinon les siens).
     */
    protected function accessible_events()
    {
        $this->load->model('Event_model');
        return $this->is_super()
            ? $this->Event_model->all()
            : $this->Event_model->for_user($this->tenant_id());
    }

    /**
     * Coupe l'accès (403) si l'admin tente d'agir sur un événement
     * qui ne lui est pas attribué. (super_admin : toujours autorisé.)
     */
    protected function guard_event($event_id)
    {
        if ($this->is_super())
        {
            return;
        }
        $this->load->model('Event_model');
        if ( ! $event_id || ! $this->Event_model->owned_by($event_id, $this->tenant_id()))
        {
            show_error('Cet événement ne vous est pas attribué.', 403, 'Accès refusé');
        }
    }

    /* -----------------------------------------------------------------
     |  Abonnement & quota de forfait (SaaS — Phase 4)
     |  super_admin : illimité, aucun contrôle.
     | ----------------------------------------------------------------- */

    /** @var array|null|false Abonnement actif du photographe (FALSE = non chargé). */
    protected $_active_sub = FALSE;

    /** Abonnement ACTIF du photographe courant (ou NULL). */
    protected function active_subscription()
    {
        if ($this->is_super()) return NULL;
        if ($this->_active_sub === FALSE)
        {
            $this->load->model('Subscription_model');
            $this->_active_sub = $this->Subscription_model->active_for_user($this->tenant_id()) ?: NULL;
        }
        return $this->_active_sub;
    }

    /** Plan associé à l'abonnement actif (ou NULL si aucun abonnement actif). */
    protected function current_plan()
    {
        if ($this->is_super()) return NULL;
        $sub = $this->active_subscription();
        if ( ! $sub) return NULL;
        $this->load->model('Plan_model');
        return $this->Plan_model->get($sub['plan_id']);
    }

    /** Nombre d'événements possédés par le titulaire courant. */
    protected function events_owned_count()
    {
        return (int) $this->db
            ->where('user_id', $this->tenant_id())
            ->count_all_results('events');
    }

    /**
     * Quota d'événements EFFECTIF du photographe courant.
     * Priorité à la dérogation posée par le super admin sur l'abonnement
     * (subscriptions.events_quota, 0 = illimité), sinon le plan.
     * Retourne un entier, ou NULL = illimité.
     */
    protected function effective_max_events()
    {
        $sub = $this->active_subscription();
        if ($sub && $sub['events_quota'] !== NULL)
        {
            $v = (int) $sub['events_quota'];
            return $v === 0 ? NULL : $v; // 0 accordé par le super admin = illimité
        }
        $plan = $this->current_plan();
        return ($plan && $plan['max_events'] !== NULL) ? (int) $plan['max_events'] : NULL;
    }

    /** @var bool|NULL Cache par requête de email_confirmed(). */
    protected $_email_confirmed = NULL;

    /**
     * L'adresse e-mail du compte est-elle confirmée ? (anti-spam)
     * Toujours TRUE pour le super admin et les collaborateurs (comptes créés
     * par le titulaire, pas d'auto-inscription). Lu en base à chaque requête
     * (pas en session) : cliquer le lien de confirmation prend effet
     * immédiatement, sans reconnexion.
     */
    protected function email_confirmed()
    {
        if ($this->is_super()) return TRUE;
        if (($this->current_user['role'] ?? '') === 'collaborateur') return TRUE;

        if ($this->_email_confirmed === NULL)
        {
            $row = $this->db->select('email_verified')
                ->where('id', (int) $this->current_user['id'])
                ->get('users')->row_array();
            $this->_email_confirmed = ! empty($row['email_verified']);
        }
        return $this->_email_confirmed;
    }

    /**
     * Le photographe courant peut-il créer un nouvel événement ?
     * $reason (par référence) : 'unverified' (e-mail non confirmé),
     * 'inactive' (abo non actif) ou 'quota' (plafond).
     * super_admin : toujours TRUE.
     */
    protected function can_create_event(&$reason = NULL)
    {
        $reason = NULL;
        if ($this->is_super()) return TRUE;

        if ( ! $this->email_confirmed()) { $reason = 'unverified'; return FALSE; }

        if ( ! $this->active_subscription()) { $reason = 'inactive'; return FALSE; }

        $max = $this->effective_max_events();
        if ($max === NULL) return TRUE; // illimité

        if ($this->events_owned_count() >= $max) { $reason = 'quota'; return FALSE; }
        return TRUE;
    }

    /* -----------------------------------------------------------------
     |  Quota de STOCKAGE (Go) — photos + vidéos, tous événements du
     |  photographe. La dérogation super admin (subscriptions.
     |  storage_quota_mo, 0 = illimité) prime sur le plan.
     | ----------------------------------------------------------------- */

    /** Quota de stockage effectif en Mo (NULL = illimité / super_admin). */
    protected function effective_storage_mo()
    {
        if ($this->is_super()) return NULL;
        $sub = $this->active_subscription();
        if ($sub && $sub['storage_quota_mo'] !== NULL)
        {
            $v = (int) $sub['storage_quota_mo'];
            return $v === 0 ? NULL : $v; // 0 accordé par le super admin = illimité
        }
        $plan = $this->current_plan();
        return ($plan && $plan['storage_mo'] !== NULL) ? (int) $plan['storage_mo'] : NULL;
    }

    /** Octets déjà stockés (photos + vidéos) sur les événements du titulaire. */
    protected function storage_used_bytes()
    {
        $this->load->model(array('Event_model', 'Photo_model', 'Video_model'));
        $events = $this->Event_model->for_user($this->tenant_id());
        $ids = array_map('intval', array_column($events, 'id'));
        return $this->Photo_model->sum_bytes_for_events($ids)
             + $this->Video_model->sum_bytes_for_events($ids);
    }

    /**
     * Le photographe peut-il stocker $add_bytes octets de plus ?
     * $error (par référence) : message d'explication prêt à afficher.
     * super_admin : toujours TRUE.
     */
    protected function can_store_bytes($add_bytes, &$error = NULL)
    {
        $error = NULL;
        if ($this->is_super()) return TRUE;

        if ( ! $this->active_subscription())
        {
            $error = 'Abonnement non actif.';
            return FALSE;
        }

        $quota_mo = $this->effective_storage_mo();
        if ($quota_mo === NULL) return TRUE; // illimité

        $quota = $quota_mo * 1048576;
        $used  = $this->storage_used_bytes();
        if ($used + max(0, (int) $add_bytes) > $quota)
        {
            $error = 'Quota de stockage de votre forfait atteint ('
                   . $this->format_go($used).' utilisés sur '.$this->format_go($quota).'). '
                   . 'Supprimez des médias ou passez à un forfait supérieur.';
            return FALSE;
        }
        return TRUE;
    }

    /** L'option vidéo est-elle incluse dans le forfait courant ? */
    protected function plan_allows_video()
    {
        if ($this->is_super()) return TRUE;
        $plan = $this->current_plan();
        return $plan && ! empty($plan['video']);
    }

    /** Le mot de passe de galerie (« lien chiffré ») est-il inclus dans le forfait ? */
    protected function plan_allows_password()
    {
        if ($this->is_super()) return TRUE;
        $plan = $this->current_plan();
        return $plan && ! empty($plan['gallery_password']);
    }

    /* -----------------------------------------------------------------
     |  Collaborateurs (comptes invités rattachés au titulaire)
     | ----------------------------------------------------------------- */

    /**
     * Plafond de collaborateurs du forfait : entier, ou NULL = illimité.
     * 0 = le forfait n'inclut pas la collaboration.
     */
    protected function effective_max_collaborators()
    {
        if ($this->is_super()) return NULL;
        $plan = $this->current_plan();
        if ( ! $plan) return 0;
        return ($plan['max_collaborators'] === NULL) ? NULL : (int) $plan['max_collaborators'];
    }

    /** Nombre de collaborateurs (actifs ou non) rattachés au titulaire. */
    protected function collaborators_count()
    {
        return (int) $this->db
            ->where('parent_user_id', $this->tenant_id())
            ->count_all_results('users');
    }

    /** Formatage lisible d'un volume en octets (Mo / Go). */
    protected function format_go($bytes)
    {
        $bytes = (float) $bytes;
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 2).' Go';
        return round($bytes / 1048576).' Mo';
    }
}
