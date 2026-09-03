<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Photo_model — photos d'un événement.
 *
 * Deux origines possibles par photo :
 *   - LOCAL : dérivés sur disque (path_thumb/medium/full/original).
 *   - R2    : original sur Cloudflare R2 (r2_key) + vignettes générées à l'edge.
 * `status` = 'pending' pendant l'upload direct R2, 'ready' une fois confirmé.
 * Les requêtes publiques ne renvoient que les photos 'ready'.
 */
class Photo_model extends CI_Model {

    protected $table = 'photos';

    public function get($id)
    {
        return $this->db->where('id', $id)->get($this->table)->row_array();
    }

    public function get_by_r2_key($key)
    {
        return $this->db->where('r2_key', $key)->get($this->table)->row_array();
    }

    /** Insère une photo traitée (mode local). Retourne l'id. */
    public function create(array $data)
    {
        $this->db->insert($this->table, $data);
        return $this->db->insert_id();
    }

    /** Crée une photo « pending » pour un upload direct R2. Retourne l'id. */
    public function create_pending($event_id, $album_id, $r2_key, $ordre = 0, $size_bytes = NULL)
    {
        $this->db->insert($this->table, array(
            'event_id'      => (int) $event_id,
            'album_id'      => $album_id !== NULL ? (int) $album_id : NULL,
            'r2_key'        => $r2_key,
            'size_bytes'    => $size_bytes !== NULL ? (int) $size_bytes : NULL,
            'filename_base' => bin2hex(random_bytes(8)),
            'ordre'         => (int) $ordre,
            'status'        => 'pending',
        ));
        return $this->db->insert_id();
    }

    /** Corrige la taille stockée avec la taille réelle mesurée sur R2. */
    public function set_size($id, $bytes)
    {
        $this->db->where('id', (int) $id)
            ->update($this->table, array('size_bytes' => max(0, (int) $bytes)));
    }

    /** Marque une photo R2 comme prête (upload confirmé) + dimensions client. */
    public function mark_ready($id, $w = NULL, $h = NULL)
    {
        $data = array('status' => 'ready');
        if ($w) $data['largeur'] = (int) $w;
        if ($h) $data['hauteur'] = (int) $h;
        $this->db->where('id', (int) $id)->update($this->table, $data);
    }

    public function next_ordre($event_id)
    {
        $row = $this->db->select_max('ordre', 'm')
            ->where('event_id', $event_id)
            ->get($this->table)->row_array();
        return ((int) ($row['m'] ?? 0)) + 1;
    }

    /**
     * Photos PRÊTES d'un événement (option : filtre album), paginées.
     */
    public function for_event($event_id, $album_id = NULL, $limit = 40, $offset = 0, $sort = 'ordre')
    {
        $this->db->where('event_id', $event_id)->where('status', 'ready');
        if ($album_id !== NULL)
        {
            $this->db->where('album_id', $album_id);
        }

        if ($sort === 'date')
        {
            $this->db->order_by('created_at', 'DESC')->order_by('id', 'DESC');
        }
        else
        {
            $this->db->order_by('ordre', 'ASC')->order_by('id', 'ASC');
        }

        return $this->db->limit($limit, $offset)->get($this->table)->result_array();
    }

    /**
     * Nombre de photos PRÊTES par album (cartes folders_first). Clé 0 = sans dossier.
     */
    public function counts_by_album($event_id)
    {
        $rows = $this->db->select('COALESCE(album_id,0) AS aid, COUNT(*) AS n')
            ->where('event_id', $event_id)->where('status', 'ready')
            ->group_by('aid')
            ->get($this->table)->result_array();

        $out = array();
        foreach ($rows as $r)
        {
            $out[(int) $r['aid']] = (int) $r['n'];
        }
        return $out;
    }

    /**
     * Photo de couverture (première prête) par album.
     * Retourne [album_id => array('r2_key'=>…, 'path_thumb_webp'=>…)]
     * pour laisser la vue résoudre l'URL (local ou R2).
     */
    public function covers_by_album($event_id)
    {
        $mins = $this->db->select('MIN(id) AS mid')
            ->where('event_id', $event_id)->where('status', 'ready')
            ->group_by('album_id')
            ->get($this->table)->result_array();

        $ids = array_filter(array_column($mins, 'mid'));
        if (empty($ids))
        {
            return array();
        }

        $rows = $this->db->select('album_id, r2_key, path_thumb_webp')
            ->where_in('id', $ids)
            ->get($this->table)->result_array();

        $out = array();
        foreach ($rows as $r)
        {
            $out[(int) ($r['album_id'] ?? 0)] = array(
                'r2_key'          => $r['r2_key'],
                'path_thumb_webp' => $r['path_thumb_webp'],
            );
        }
        return $out;
    }

    /**
     * Photos dont l'original disque doit être purgé (rétention dépassée).
     * (Le mode R2 gère la rétention séparément — hors de ce cron disque.)
     */
    public function due_for_purge($limit = 500)
    {
        return $this->db->select('p.id, p.path_original')
            ->from($this->table.' p')
            ->join('event_settings es', 'es.event_id = p.event_id')
            ->where('p.original_purged', 0)
            ->where('p.path_original IS NOT NULL', NULL, FALSE)
            ->where('es.originals_available_until IS NOT NULL', NULL, FALSE)
            ->where('es.originals_available_until <', date('Y-m-d H:i:s'))
            ->limit($limit)
            ->get()->result_array();
    }

    public function for_event_to_purge($event_id)
    {
        return $this->db->select('id, path_original')
            ->where('event_id', $event_id)
            ->where('original_purged', 0)
            ->where('path_original IS NOT NULL', NULL, FALSE)
            ->get($this->table)->result_array();
    }

    public function mark_purged($id)
    {
        $this->db->where('id', $id)->update($this->table, array('original_purged' => 1));
    }

    public function originals_available_count($event_id)
    {
        return (int) $this->db
            ->where('event_id', $event_id)
            ->where('original_purged', 0)
            ->where('path_original IS NOT NULL', NULL, FALSE)
            ->count_all_results($this->table);
    }

    /**
     * Supprime une photo : original R2 (si clé) + dérivés disque, puis la ligne.
     */
    public function delete($id)
    {
        $photo = $this->get((int) $id);
        if ( ! $photo)
        {
            return FALSE;
        }

        // Original sur R2. Un échec (réseau) n'est JAMAIS silencieux : la clé
        // part en file r2_orphans et le cron purge_media re-tentera.
        if ( ! empty($photo['r2_key']))
        {
            $CI =& get_instance();
            $CI->load->library('r2');
            if ($CI->r2->is_configured() && ! $CI->r2->delete($photo['r2_key']))
            {
                $this->db->insert('r2_orphans', array(
                    'r2_key' => $photo['r2_key'],
                    'note'   => 'photo #'.(int) $id,
                ));
            }
        }

        // Dérivés sur disque (mode local).
        foreach (array('path_thumb_webp', 'path_medium_webp', 'path_full_webp', 'path_original') as $k)
        {
            if ( ! empty($photo[$k]))
            {
                $abs = FCPATH.str_replace('/', DIRECTORY_SEPARATOR, $photo[$k]);
                if (is_file($abs)) @unlink($abs);
            }
        }

        $this->db->where('id', (int) $id)->delete($this->table);
        return $this->db->affected_rows() > 0;
    }

    /** Nombre de photos PRÊTES (affichage public / stats). */
    public function count_for_event($event_id, $album_id = NULL)
    {
        $this->db->where('event_id', $event_id)->where('status', 'ready');
        if ($album_id !== NULL)
        {
            $this->db->where('album_id', $album_id);
        }
        return (int) $this->db->count_all_results($this->table);
    }

    /** Nombre TOTAL de photos (pending + ready) — pour le quota de forfait. */
    public function count_all_for_event($event_id)
    {
        return (int) $this->db->where('event_id', $event_id)->count_all_results($this->table);
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
        $rows = $this->db->select('e.user_id AS uid, COALESCE(SUM(p.size_bytes),0) AS s')
            ->from($this->table.' p')
            ->join('events e', 'e.id = p.event_id')
            ->where('e.user_id IS NOT NULL', NULL, FALSE)
            ->group_by('e.user_id')
            ->get()->result_array();
        $out = array();
        foreach ($rows as $r) $out[(int) $r['uid']] = (int) $r['s'];
        return $out;
    }

    /** Toutes les photos d'un événement (pending inclus) — purge admin/cron. */
    public function ids_for_event($event_id)
    {
        $rows = $this->db->select('id')->where('event_id', (int) $event_id)
            ->get($this->table)->result_array();
        return array_map('intval', array_column($rows, 'id'));
    }

    /** Photos « pending » abandonnées (upload direct jamais confirmé). */
    public function stale_pending($hours = 48, $limit = 500)
    {
        return $this->db->where('status', 'pending')
            ->where('created_at <', date('Y-m-d H:i:s', time() - $hours * 3600))
            ->limit((int) $limit)
            ->get($this->table)->result_array();
    }
}
