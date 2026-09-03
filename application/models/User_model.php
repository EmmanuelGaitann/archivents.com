<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * User_model — utilisateurs du back-office.
 */
class User_model extends CI_Model {

    protected $table = 'users';

    /**
     * Récupère un utilisateur actif par email.
     */
    public function get_by_email($email)
    {
        return $this->db
            ->where('email', $email)
            ->where('actif', 1)
            ->get($this->table)
            ->row_array();
    }

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    /**
     * Vérifie les identifiants. Retourne l'utilisateur (sans le hash)
     * en cas de succès, sinon FALSE.
     */
    public function verify_credentials($email, $password)
    {
        $user = $this->get_by_email($email);
        if ( ! $user)
        {
            return FALSE;
        }

        if ( ! password_verify($password, $user['password_hash']))
        {
            return FALSE;
        }

        // Re-hash transparent si l'algorithme par défaut a évolué.
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT))
        {
            $this->db->where('id', $user['id'])->update($this->table, array(
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ));
        }

        unset($user['password_hash']);
        return $user;
    }

    /* -----------------------------------------------------------------
     |  CRUD (gestion des utilisateurs — super_admin)
     | ----------------------------------------------------------------- */

    /** Liste de tous les utilisateurs. */
    public function all()
    {
        return $this->db->order_by('created_at', 'ASC')
            ->get($this->table)->result_array();
    }

    /** Email déjà utilisé ? (option : exclure un id lors d'une édition). */
    public function email_exists($email, $exclude_id = NULL)
    {
        $this->db->where('email', $email);
        if ($exclude_id)
        {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return (bool) $this->db->count_all_results($this->table);
    }

    /** Sous-domaine (studio_slug) déjà pris ? (option : exclure un id). */
    public function studio_slug_exists($slug, $exclude_id = NULL)
    {
        $this->db->where('studio_slug', $slug);
        if ($exclude_id)
        {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return (bool) $this->db->count_all_results($this->table);
    }

    /** Crée un utilisateur. Retourne l'id. */
    public function create(array $data)
    {
        if (isset($data['password']))
        {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            unset($data['password']);
        }
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /** Met à jour un utilisateur. Le mot de passe n'est changé que s'il est fourni. */
    public function update($id, array $data)
    {
        if ( ! empty($data['password']))
        {
            $data['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }
        unset($data['password']);
        $this->db->where('id', (int) $id)->update($this->table, $data);
    }

    public function delete($id)
    {
        $this->db->where('id', (int) $id)->delete($this->table);
    }

    /** Nombre de super_admin actifs (garde-fou anti-verrouillage). */
    public function count_active_super_admins($exclude_id = NULL)
    {
        $this->db->where('role', 'super_admin')->where('actif', 1);
        if ($exclude_id)
        {
            $this->db->where('id !=', (int) $exclude_id);
        }
        return (int) $this->db->count_all_results($this->table);
    }
}
