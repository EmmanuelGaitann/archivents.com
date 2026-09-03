<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_URI — rend la détection d'URI insensible à la casse du SOUS-DOSSIER.
 *
 * Problème corrigé : en local (XAMPP/Windows), Apache sert indifféremment
 * /Archiven/... et /archiven/..., mais CI_URI retire le préfixe du dossier
 * (dirname de SCRIPT_NAME, ex. « /Archiven ») en comparant STRICTEMENT la
 * casse. Taper l'URL en minuscules laissait « archiven/pricing » comme URI
 * → 404 sur toutes les routes.
 *
 * Correction : si le chemin demandé commence par le préfixe du script à la
 * casse près, on récrit REQUEST_URI avec la casse canonique puis on délègue
 * au parent (comportement d'origine conservé partout ailleurs).
 *
 * En production (application à la racine du domaine), le préfixe est « / » :
 * cette classe n'a AUCUN effet.
 */
class MY_URI extends CI_URI {

    protected function _parse_request_uri()
    {
        if (isset($_SERVER['REQUEST_URI'], $_SERVER['SCRIPT_NAME']))
        {
            $parts = parse_url('http://dummy'.$_SERVER['REQUEST_URI']);
            $path  = isset($parts['path']) ? $parts['path'] : '';
            $query = isset($parts['query']) ? '?'.$parts['query'] : '';

            // Préfixes possibles, comme dans CI_URI : le script complet
            // (/Archiven/index.php) puis son dossier (/Archiven).
            $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME']);
            foreach (array($script, dirname($script)) as $prefix)
            {
                if ($prefix === '' || $prefix === '/' || $prefix === '.')
                {
                    continue;
                }
                $len = strlen($prefix);
                $same_ci  = (strncasecmp($path, $prefix, $len) === 0);
                $same_cs  = (strncmp($path, $prefix, $len) === 0);
                $boundary = (strlen($path) === $len || substr($path, $len, 1) === '/');

                if ($same_ci && ! $same_cs && $boundary)
                {
                    $_SERVER['REQUEST_URI'] = $prefix.substr($path, $len).$query;
                    break;
                }
            }
        }

        return parent::_parse_request_uri();
    }
}
