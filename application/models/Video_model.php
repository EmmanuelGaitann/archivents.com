<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Video_model — vidéos d'un événement (forfaits avec option vidéo).
 *
 * Toujours stockées sur Cloudflare R2 (MP4 web-ready H.264/AAC, servi tel
 * quel en range-requests par le CDN — aucun transcodage serveur).
 * `status` = 'pending' pendant l'upload direct (simple ou multipart),
 * 'ready' une fois confirmé. Le public ne voit que les vidéos 'ready'.
 */
class Video_model extends CI_Model {

    protected $table = 'videos';

    public function get($id)
    {
        return $this->db->where('id', (int) $id)->get($this->table)->row_array();
    }

    /** Crée une vidéo « pending » (upload direct R2 en cours). Retourne l'id. */
    public function create_pending($event_id, $album_id, $r2_key, array $extra = array())
    {
        $this->db->insert($this->table, array(
            'event_id'   => (int) $event_id,
            'album_id'   => $album_id !== NULL ? (int) $album_id : NULL,
            'r2_key'     => $r2_key,
            'titre'      => isset($extra['titre']) ? mb_substr($extra['titre'], 0, 190) : NULL,
            'upload_id'  => $extra['upload_id'] ?? NULL,
            'size_bytes' => isset($extra['size_bytes']) ? (int) $extra['size_bytes'] : NULL,
            'duration_s' => isset($extra['duration_s']) ? (int) $extra['duration_s'] : NULL,
            'largeur'    => isset($extra['largeur']) ? (int) $extra['largeur'] : NULL,
            'hauteur'    => isset($extra['hauteur']) ? (int) $extra['hauteur'] : NULL,
            'ordre'      => (int) ($extra['ordre'] ?? 0),
            'status'     => 'pending',
        ));
        return (int) $this->db->insert_id();
    }

    /** Enregistre l'UploadId multipart sur une vidéo en cours. */
    public function set_upload_id($id, $upload_id)
    {
        $this->db->where('id', (int) $id)->update($this->table, array('upload_id' => $upload_id));
    }

    /** Corrige la taille stockée avec la taille réelle mesurée sur R2. */
    public function set_size($id, $bytes)
    {
        $this->db->where('id', (int) $id)
            ->update($this->table, array('size_bytes' => max(0, (int) $bytes)));
    }

    /** Marque une vidéo comme prête (upload confirmé). */
    public function mark_ready($id)
    {
        $this->db->where('id', (int) $id)
            ->update($this->table, array('status' => 'ready', 'upload_id' => NULL));
    }

    public function next_ordre($event_id)
    {
        $row = $this->db->select_max('ordre', 'm')
            ->where('event_id', (int) $event_id)
            ->get($this->table)->row_array();
        return ((int) ($row['m'] ?? 0)) + 1;
    }

    /** Vidéos PRÊTES d'un événement (option : filtre album) — galerie publique. */
    public function for_event($event_id, $album_id = NULL)
    {
        $this->db->where('event_id', (int) $event_id)->where('status', 'ready');
        if ($album_id !== NULL)
        {
            $this->db->where('album_id', (int) $album_id);
        }
        return $this->db->order_by('ordre', 'ASC')->order_by('id', 'ASC')
            ->get($this->table)->result_array();
    }

    /** Toutes les vidéos d'un événement (admin — pending inclus). */
    public function all_for_event($event_id)
    {
        return $this->db->where('event_id', (int) $event_id)
            ->order_by('id', 'DESC')
            ->get($this->table)->result_array();
    }

    public function count_for_event($event_id)
    {
        return (int) $this->db->where('event_id', (int) $event_id)
            ->where('status', 'ready')->count_all_results($this->table);
    }

    /** Somme des octets stockés pour un ensemble d'événements (quota Go). */
    public function sum_bytes_for_events(array $event_ids)
    {
        if (empty($event_ids)) return 0;
        $row = $this->db->select('COALESCE(SUM(size_bytes),0) AS s')
            ->where_in('event_id', array_map('intval', $event_ids))
            ->get($this->table)->row_array();
        return (int) ($row['s'] ?? 0);
    }

    /** Octets stockés par propriétaire d'événement — back-office abonnements. */
    public function bytes_by_user()
    {
        $rows = $this->db->select('e.user_id AS uid, COALESCE(SUM(v.size_bytes),0) AS s')
            ->from($this->table.' v')
            ->join('events e', 'e.id = v.event_id')
            ->where('e.user_id IS NOT NULL', NULL, FALSE)
            ->group_by('e.user_id')
            ->get()->result_array();
        $out = array();
        foreach ($rows as $r) $out[(int) $r['uid']] = (int) $r['s'];
        return $out;
    }

    /** Vidéos « pending » abandonnées (upload jamais terminé). */
    public function stale_pending($hours = 48, $limit = 200)
    {
        return $this->db->where('status', 'pending')
            ->where('created_at <', date('Y-m-d H:i:s', time() - $hours * 3600))
            ->limit((int) $limit)
            ->get($this->table)->result_array();
    }

    /**
     * Supprime une vidéo : objet R2 (+ abandon multipart en cours) puis la ligne.
     */
    public function delete($id)
    {
        $video = $this->get((int) $id);
        if ( ! $video)
        {
            return FALSE;
        }

        // Un échec (réseau) n'est JAMAIS silencieux : la clé (et l'éventuel
        // multipart à avorter) part en file r2_orphans, re-tentée par le cron.
        $CI =& get_instance();
        $CI->load->library('r2');
        if ($CI->r2->is_configured())
        {
            $abort_ok = TRUE;
            if ( ! empty($video['upload_id']))
            {
                $abort_ok = $CI->r2->abortMultipart($video['r2_key'], $video['upload_id']);
            }
            $del_ok = $CI->r2->delete($video['r2_key']);
            if ( ! $abort_ok || ! $del_ok)
            {
                $this->db->insert('r2_orphans', array(
                    'r2_key'    => $video['r2_key'],
                    'upload_id' => $abort_ok ? NULL : $video['upload_id'],
                    'note'      => 'vidéo #'.(int) $id,
                ));
            }
        }

        $this->db->where('id', (int) $id)->delete($this->table);
        return $this->db->affected_rows() > 0;
    }
}
