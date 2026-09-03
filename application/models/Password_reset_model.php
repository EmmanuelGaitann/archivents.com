<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Password_reset_model — jetons de réinitialisation de mot de passe.
 * Seul le hash du jeton est stocké ; le jeton brut voyage par e-mail.
 */
class Password_reset_model extends CI_Model {

    protected $table = 'password_resets';

    /** Crée un jeton (remplace les précédents du même e-mail). */
    public function issue($email, $token_hash, $ttl_minutes = 60)
    {
        $this->delete_for_email($email);
        $this->db->insert($this->table, array(
            'email'      => $email,
            'token_hash' => $token_hash,
            'expires_at' => date('Y-m-d H:i:s', time() + $ttl_minutes * 60),
        ));
    }

    /** Retourne la ligne valide (non expirée) pour ce hash, ou NULL. */
    public function find_valid($token_hash)
    {
        return $this->db
            ->where('token_hash', $token_hash)
            ->where('expires_at >=', date('Y-m-d H:i:s'))
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get($this->table)
            ->row_array();
    }

    public function delete_for_email($email)
    {
        $this->db->where('email', $email)->delete($this->table);
    }

    /** Purge des jetons expirés (hygiène). */
    public function gc()
    {
        $this->db->where('expires_at <', date('Y-m-d H:i:s'))->delete($this->table);
    }
}
