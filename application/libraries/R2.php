<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * R2 — client Cloudflare R2 (S3-compatible) SANS dépendance (SigV4 pur PHP).
 *
 * Le serveur ne touche jamais les octets média en upload : le navigateur PUT
 * directement vers des URLs présignées (simple ou multipart). Côté serveur :
 *   - émettre les URLs présignées (presignUpload / presignPart),
 *   - orchestrer le multipart (createMultipart / completeMultipart / abort),
 *   - confirmer/inspecter/supprimer un objet (exists / delete / put).
 * Lecture via cdn.archivents.com (publicUrl / imageUrl à l'edge, format=auto).
 *
 * Config via environnement (.env chargé au bootstrap) :
 *   R2_ACCOUNT_ID, R2_ENDPOINT, R2_BUCKET, R2_ACCESS_KEY_ID,
 *   R2_SECRET_ACCESS_KEY, R2_PUBLIC_URL, CF_IMAGES_BASE, CF_TRANSFORM.
 */
class R2 {

    protected $bucket, $access, $secret, $host, $region = 'auto', $service = 's3';
    protected $publicUrl, $imagesBase, $transform;
    protected $sizes = array('thumb' => 300, 'medium' => 1600, 'full' => 2400, 'orig' => 0);
    protected $quality = 82;

    public function __construct()
    {
        $this->bucket     = $this->env('R2_BUCKET', 'archivents-prod');
        $this->access     = $this->env('R2_ACCESS_KEY_ID');
        $this->secret     = $this->env('R2_SECRET_ACCESS_KEY');
        $this->publicUrl  = rtrim($this->env('R2_PUBLIC_URL'), '/');
        $this->imagesBase = rtrim($this->env('CF_IMAGES_BASE', $this->publicUrl), '/');
        $this->transform  = ($this->env('CF_TRANSFORM', '1') !== '0');

        $endpoint = $this->env('R2_ENDPOINT');
        $this->host = ($endpoint !== '')
            ? preg_replace('#^https?://#', '', rtrim($endpoint, '/'))
            : $this->env('R2_ACCOUNT_ID').'.r2.cloudflarestorage.com';
    }

    protected function env($k, $default = '')
    {
        if (isset($_ENV[$k]) && $_ENV[$k] !== '') return $_ENV[$k];
        $v = getenv($k);
        return ($v !== FALSE && $v !== '') ? $v : $default;
    }

    public function is_configured()
    {
        return $this->access !== '' && $this->secret !== '' && $this->host !== '' && $this->bucket !== '';
    }

    /* =================================================================
     |  Clés d'objet
     | ================================================================= */

    /** Clé normalisée (minuscules) tenant/event/uuid.ext — jamais le nom brut. */
    public function buildKey($tenant, $eventId, $originalFilename)
    {
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        if ($ext === 'jpeg') $ext = 'jpg';
        $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
        return $this->slug($tenant).'/'.$this->slug($eventId).'/'.bin2hex(random_bytes(8)).'.'.$ext;
    }

    protected function slug($s)
    {
        $s = preg_replace('/[^a-z0-9\-]+/', '-', strtolower((string) $s));
        return trim($s, '-') ?: 'x';
    }

    /* =================================================================
     |  Lecture (URLs de livraison)
     | ================================================================= */

    public function publicUrl($key)
    {
        return $this->publicUrl.'/'.$this->encodeKey($key);
    }

    /** Transformation Cloudflare (format=auto) ; repli original si désactivé. */
    public function imageUrl($key, $preset = 'medium')
    {
        if ( ! $this->transform || $this->imagesBase === '') return $this->publicUrl($key);
        $w = isset($this->sizes[$preset]) ? (int) $this->sizes[$preset] : $this->sizes['medium'];
        $opts = array('format=auto');
        if ($w > 0) { $opts[] = 'width='.$w; $opts[] = 'fit=scale-down'; }
        $opts[] = 'quality='.$this->quality;
        return $this->imagesBase.'/cdn-cgi/image/'.implode(',', $opts).'/'.$this->encodeKey($key);
    }

    /* =================================================================
     |  Upload simple présigné (PUT direct navigateur, <= 5 Go)
     | ================================================================= */

    public function presignUpload($key, $contentType = 'image/jpeg', $expires = 900)
    {
        return $this->presignQuery('PUT', $key, array(), $expires);
    }

    /* =================================================================
     |  Upload MULTIPART (vidéo / gros fichiers, reprenable)
     |  Le serveur n'orchestre que : create -> [presign part]* -> complete.
     | ================================================================= */

    /** Initie un upload multipart. Retourne l'UploadId (ou NULL). */
    public function createMultipart($key, $contentType = 'application/octet-stream')
    {
        list($status, $body) = $this->signedRequest('POST', $key, array('uploads' => ''), '', $contentType);
        if ($status < 200 || $status >= 300) return NULL;
        return preg_match('#<UploadId>(.*?)</UploadId>#', $body, $m) ? $m[1] : NULL;
    }

    /** URL présignée pour envoyer une partie (PUT direct navigateur). */
    public function presignPart($key, $uploadId, $partNumber, $expires = 3600)
    {
        return $this->presignQuery('PUT', $key, array(
            'partNumber' => (string) (int) $partNumber,
            'uploadId'   => $uploadId,
        ), $expires);
    }

    /**
     * Finalise le multipart. $parts = [ ['PartNumber'=>n, 'ETag'=>'"..."'], ... ].
     */
    public function completeMultipart($key, $uploadId, array $parts)
    {
        usort($parts, function ($a, $b) { return (int) $a['PartNumber'] - (int) $b['PartNumber']; });
        $xml = '<CompleteMultipartUpload>';
        foreach ($parts as $p)
        {
            $etag = $p['ETag'];
            if ($etag[0] !== '"') $etag = '"'.$etag.'"';
            $xml .= '<Part><PartNumber>'.(int) $p['PartNumber'].'</PartNumber><ETag>'.$etag.'</ETag></Part>';
        }
        $xml .= '</CompleteMultipartUpload>';

        list($status, $body) = $this->signedRequest('POST', $key, array('uploadId' => $uploadId), $xml, 'application/xml');
        return $status >= 200 && $status < 300 && strpos($body, '<Error') === FALSE;
    }

    /** Abandonne un upload multipart (nettoyage). */
    public function abortMultipart($key, $uploadId)
    {
        list($status) = $this->signedRequest('DELETE', $key, array('uploadId' => $uploadId));
        return $status >= 200 && $status < 300;
    }

    /* =================================================================
     |  Écriture / inspection serveur (requêtes signées)
     | ================================================================= */

    public function put($key, $body, $contentType = 'application/octet-stream')
    {
        list($status) = $this->signedRequest('PUT', $key, array(), $body, $contentType, array(
            'Cache-Control: public, max-age=31536000, immutable',
        ));
        return $status >= 200 && $status < 300;
    }

    /**
     * Supprime un objet. TRUE = supprimé (ou déjà absent : 404 = succès),
     * FALSE = échec réel (réseau après retries, ou refus) — l'appelant DOIT
     * alors mettre la clé en file r2_orphans, jamais ignorer.
     */
    public function delete($key)
    {
        list($status) = $this->signedRequest('DELETE', $key, array());
        if ($status === 404) return TRUE; // objet déjà parti : rien à payer
        $ok = ($status >= 200 && $status < 300);
        if ( ! $ok)
        {
            log_message('error', 'R2 delete ÉCHOUÉ (status '.$status.') : '.$key);
        }
        return $ok;
    }

    /**
     * TRUE = l'objet existe, FALSE = absent (404), NULL = réseau indisponible
     * (après retries) — dans ce cas on NE SAIT PAS, ne pas conclure « absent ».
     */
    public function exists($key)
    {
        list($status) = $this->signedRequest('HEAD', $key, array());
        if ($status === 200) return TRUE;
        if ($status === 0 && $this->lastNetworkError) return NULL;
        return FALSE;
    }

    /**
     * Taille réelle (octets) d'un objet : int si présent, NULL s'il est
     * ABSENT (404), FALSE si le RÉSEAU est indisponible (après retries).
     * Sert à vérifier côté serveur la taille déclarée par le client
     * (intégrité du quota de stockage).
     */
    public function size($key)
    {
        list($status) = $this->signedRequest('HEAD', $key, array());
        if ($status === 0 && $this->lastNetworkError)
        {
            return FALSE;
        }
        if ($status !== 200)
        {
            return NULL;
        }
        return ($this->lastLength !== NULL && $this->lastLength >= 0) ? (int) $this->lastLength : 0;
    }

    /* =================================================================
     |  SigV4 & HTTP
     | ================================================================= */

    /** URL présignée (query-auth), signe l'en-tête host uniquement. */
    protected function presignQuery($method, $key, array $extra, $expires)
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $date    = gmdate('Ymd');
        $scope   = $date.'/'.$this->region.'/'.$this->service.'/aws4_request';
        $uri     = '/'.$this->bucket.'/'.$this->encodeKey($key);

        $q = array(
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $this->access.'/'.$scope,
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expires,
            'X-Amz-SignedHeaders' => 'host',
        ) + $extra;
        ksort($q);
        $cq = $this->canonicalQuery($q);

        $canonicalRequest = $method."\n".$uri."\n".$cq."\nhost:".$this->host."\n\nhost\nUNSIGNED-PAYLOAD";
        $sig = $this->signature($amzDate, $scope, $canonicalRequest, $date);

        return 'https://'.$this->host.$uri.'?'.$cq.'&X-Amz-Signature='.$sig;
    }

    /** Requête S3 signée (Authorization header). Retourne array(status, body). */
    protected function signedRequest($method, $key, array $query = array(), $body = '', $contentType = NULL, array $extraHeaders = array())
    {
        $amzDate = gmdate('Ymd\THis\Z');
        $date    = gmdate('Ymd');
        $scope   = $date.'/'.$this->region.'/'.$this->service.'/aws4_request';
        $uri     = '/'.$this->bucket.'/'.$this->encodeKey($key);
        $payloadHash = hash('sha256', $body === NULL ? '' : $body);

        ksort($query);
        $cq = $this->canonicalQuery($query);

        $signed = array(
            'host'                 => $this->host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'           => $amzDate,
        );
        if ($contentType !== NULL) $signed['content-type'] = $contentType;
        ksort($signed);

        $canonicalHeaders = '';
        foreach ($signed as $k => $v) { $canonicalHeaders .= $k.':'.$v."\n"; }
        $signedHeaders = implode(';', array_keys($signed));

        $canonicalRequest = $method."\n".$uri."\n".$cq."\n".$canonicalHeaders."\n".$signedHeaders."\n".$payloadHash;
        $sig = $this->signature($amzDate, $scope, $canonicalRequest, $date);

        $headers = array(
            'Authorization: AWS4-HMAC-SHA256 Credential='.$this->access.'/'.$scope
                .', SignedHeaders='.$signedHeaders.', Signature='.$sig,
            'x-amz-date: '.$amzDate,
            'x-amz-content-sha256: '.$payloadHash,
        );
        if ($contentType !== NULL) $headers[] = 'Content-Type: '.$contentType;
        foreach ($extraHeaders as $h) $headers[] = $h;

        $url = 'https://'.$this->host.$uri.($cq !== '' ? '?'.$cq : '');
        return $this->http($method, $url, $headers, $body);
    }

    /** @var int|null Content-Length de la dernière réponse (HEAD/GET). */
    protected $lastLength = NULL;

    /**
     * Requête HTTP avec RETRY sur panne réseau (timeout / connexion coupée).
     * Un timeout ne doit jamais passer pour un « objet absent » ni faire
     * échouer silencieusement une suppression (orphelins facturés) : on
     * réessaie jusqu'à 3 fois (pause 250 ms puis 750 ms), et un échec final
     * est signalé par status 0 + $this->lastNetworkError = TRUE.
     */
    protected function http($method, $url, array $headers, $body = '')
    {
        $this->lastNetworkError = FALSE;
        $attempts = 3;

        for ($try = 1; $try <= $attempts; $try++)
        {
            $ch = curl_init($url);
            curl_setopt_array($ch, array(
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_NOBODY         => ($method === 'HEAD'),
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT        => 120,
            ));
            if ($method === 'PUT' || $method === 'POST')
            {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body === NULL ? '' : $body);
            }
            $resp   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $len    = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
            $this->lastLength = ($len !== FALSE && $len >= 0) ? (int) $len : NULL;
            $err    = curl_error($ch);
            curl_close($ch);

            if ($resp !== FALSE || $status !== 0)
            {
                return array($status, is_string($resp) ? $resp : '');
            }

            log_message('error', 'R2 réseau (tentative '.$try.'/'.$attempts.', '.$method.') : '.$err);
            if ($try < $attempts)
            {
                usleep($try === 1 ? 250000 : 750000);
            }
        }

        $this->lastNetworkError = TRUE;
        return array(0, '');
    }

    /** @var bool La dernière requête a-t-elle échoué au niveau RÉSEAU (après retries) ? */
    protected $lastNetworkError = FALSE;

    public function last_network_error()
    {
        return $this->lastNetworkError;
    }

    protected function signature($amzDate, $scope, $canonicalRequest, $date)
    {
        $stringToSign = "AWS4-HMAC-SHA256\n".$amzDate."\n".$scope."\n".hash('sha256', $canonicalRequest);
        $kDate    = hash_hmac('sha256', $date, 'AWS4'.$this->secret, TRUE);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, TRUE);
        $kService = hash_hmac('sha256', $this->service, $kRegion, TRUE);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, TRUE);
        return hash_hmac('sha256', $stringToSign, $kSigning);
    }

    protected function encodeKey($key)
    {
        $parts = explode('/', ltrim((string) $key, '/'));
        return implode('/', array_map('rawurlencode', $parts));
    }

    protected function canonicalQuery(array $q)
    {
        $pairs = array();
        foreach ($q as $k => $v) $pairs[] = rawurlencode($k).'='.rawurlencode($v);
        return implode('&', $pairs);
    }
}
