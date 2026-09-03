<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Mailer — envoi d'e-mails transactionnels via SMTP (O2Switch).
 *
 * Dégrade proprement : si aucun mot de passe SMTP n'est configuré,
 * send() renvoie FALSE sans erreur (les flux d'inscription/activation
 * continuent normalement). Templates HTML sobres, aux couleurs Archivents.
 */
class Mailer {

    protected $CI;
    protected $cfg;

    public function __construct()
    {
        $this->CI  = &get_instance();
        $this->cfg = $this->CI->config->item('mailer') ?: array();
    }

    /** SMTP prêt (mot de passe renseigné) ? */
    public function is_configured()
    {
        return ! empty($this->cfg['smtp_pass']) && ! empty($this->cfg['smtp_host']);
    }

    /**
     * Envoie un e-mail HTML. Retourne TRUE/FALSE.
     * No-op (FALSE) si SMTP non configuré.
     */
    public function send($to, $subject, $html)
    {
        if ( ! $this->is_configured())
        {
            log_message('info', 'Mailer: SMTP non configuré, e-mail ignoré ('.$subject.').');
            return FALSE;
        }

        $this->CI->load->library('email');
        $this->CI->email->clear(TRUE);
        $this->CI->email->initialize(array(
            'protocol'    => 'smtp',
            'smtp_host'   => $this->cfg['smtp_host'],
            'smtp_user'   => $this->cfg['smtp_user'],
            'smtp_pass'   => $this->cfg['smtp_pass'],
            'smtp_port'   => (int) $this->cfg['smtp_port'],
            'smtp_crypto' => $this->cfg['smtp_crypto'],
            'smtp_timeout'=> 15,
            'mailtype'    => 'html',
            'charset'     => 'utf-8',
            'newline'     => "\r\n",
            'crlf'        => "\r\n",
        ));
        $this->CI->email->from($this->cfg['from_email'], $this->cfg['from_name']);
        $this->CI->email->to($to);
        $this->CI->email->subject($subject);
        $this->CI->email->message($this->layout($subject, $html));

        $ok = $this->CI->email->send(FALSE);
        if ( ! $ok)
        {
            log_message('error', 'Mailer: échec envoi ('.$subject.') : '.$this->CI->email->print_debugger(array('headers')));
        }
        return $ok;
    }

    /* =================================================================
     |  Messages métier
     | ================================================================= */

    public function welcome($to, $nom, $is_free, $plan_nom, $studio_slug, $verify_url = NULL)
    {
        $adresse = html_escape($studio_slug).'.archivents.com';
        if ($is_free)
        {
            $body = '<p>Bonjour '.html_escape($nom).',</p>'
                  . '<p>Bienvenue sur <b>Archivents</b> ! Votre espace <b>'.$adresse.'</b> est créé '
                  . 'et votre formule <b>'.html_escape($plan_nom).'</b> est active immédiatement.</p>'
                  . '<p>Vous pouvez déjà créer votre première galerie.</p>'
                  . $this->button('Accéder à mon espace', site_url('admin/dashboard'));
        }
        else
        {
            $body = '<p>Bonjour '.html_escape($nom).',</p>'
                  . '<p>Merci pour votre inscription sur <b>Archivents</b>. Votre espace <b>'.$adresse.'</b> est créé '
                  . 'avec la formule <b>'.html_escape($plan_nom).'</b>, <b>en attente d\'activation</b>.</p>'
                  . '<p>Réglez votre forfait par Orange Money, MTN MoMo ou virement, ou répondez à cet e-mail '
                  . 'pour une activation immédiate.</p>'
                  . $this->button('Accéder à mon espace', site_url('admin/dashboard'));
        }
        if ($verify_url)
        {
            $body .= '<p style="margin-top:20px;padding-top:16px;border-top:1px solid #eee;">'
                   . 'Pour finir, merci de <b>confirmer votre adresse e-mail</b> :</p>'
                   . $this->button('Confirmer mon e-mail', $verify_url)
                   . '<p style="font-size:12px;color:#8a857c;word-break:break-all;">'.html_escape($verify_url).'</p>';
        }
        return $this->send($to, 'Bienvenue sur Archivents', $body);
    }

    /** E-mail de (re)confirmation d'adresse. */
    public function verification($to, $nom, $url)
    {
        $body = '<p>Bonjour '.html_escape($nom).',</p>'
              . '<p>Confirmez votre adresse e-mail pour sécuriser votre compte Archivents :</p>'
              . $this->button('Confirmer mon e-mail', $url)
              . '<p style="font-size:12px;color:#8a857c;margin-top:16px;word-break:break-all;">'.html_escape($url).'</p>';
        return $this->send($to, 'Confirmez votre adresse e-mail', $body);
    }

    /**
     * Jeton de vérification d'e-mail : HMAC déterministe (aucune table),
     * lié à l'utilisateur ET à son adresse (change d'adresse = jeton invalide).
     */
    public function email_verify_token($user_id, $email)
    {
        $secret = config_item('encryption_key') ?: 'archivents-verify';
        return hash_hmac('sha256', 'everify|'.(int) $user_id.'|'.strtolower(trim($email)), $secret);
    }

    /** URL complète de vérification pour un utilisateur. */
    public function email_verify_url($user_id, $email)
    {
        return site_url('verify-email/'.(int) $user_id.'/'.$this->email_verify_token($user_id, $email));
    }

    public function activated($to, $nom, $plan_nom)
    {
        $body = '<p>Bonjour '.html_escape($nom).',</p>'
              . '<p>Bonne nouvelle : votre abonnement <b>'.html_escape($plan_nom).'</b> est désormais '
              . '<b>actif</b>. Vous pouvez profiter de toutes ses fonctionnalités.</p>'
              . $this->button('Ouvrir mon espace', site_url('admin/dashboard'));
        return $this->send($to, 'Votre abonnement Archivents est actif', $body);
    }

    /**
     * Rappel avant expiration (envoyé par le cron subscription_alerts,
     * à J-7 puis J-1). Invite à renouveler par OM/MoMo/virement.
     */
    public function expiring($to, $nom, $plan_nom, $expires_at, $days_left)
    {
        $date  = date('d/m/Y', strtotime($expires_at));
        $quand = ($days_left <= 1) ? '<b>demain</b>' : 'dans <b>'.$days_left.' jours</b> (le '.$date.')';
        $body = '<p>Bonjour '.html_escape($nom).',</p>'
              . '<p>Votre abonnement <b>'.html_escape($plan_nom).'</b> sur Archivents arrive à échéance '
              . $quand.'.</p>'
              . '<p>Pour continuer sans interruption (galeries en ligne, uploads, statistiques), '
              . 'renouvelez par <b>Orange Money, MTN MoMo ou virement</b>, ou répondez simplement '
              . 'à cet e-mail — nous nous occupons du reste.</p>'
              . $this->button('Ouvrir mon espace', site_url('admin/dashboard'))
              . '<p style="font-size:13px;color:#8a857c;">Sans renouvellement, vos galeries restent '
              . 'consultables 30 jours après l\'échéance, puis leurs photos et vidéos sont supprimées.</p>';
        return $this->send($to, ($days_left <= 1)
            ? 'Dernier jour : votre abonnement Archivents expire demain'
            : 'Votre abonnement Archivents expire dans '.$days_left.' jours', $body);
    }

    /**
     * Avis d'expiration (l'échéance est passée). Rappelle le délai de grâce
     * de 30 jours avant la purge des médias (Cron::purge_media).
     */
    public function expired($to, $nom, $plan_nom)
    {
        $body = '<p>Bonjour '.html_escape($nom).',</p>'
              . '<p>Votre abonnement <b>'.html_escape($plan_nom).'</b> sur Archivents est <b>arrivé à échéance</b>.</p>'
              . '<p><b>Vos galeries et vos photos sont conservées encore 30 jours.</b> '
              . 'Renouvelez pendant cette période pour tout retrouver exactement comme avant : '
              . 'Orange Money, MTN MoMo, virement, ou répondez à cet e-mail.</p>'
              . $this->button('Renouveler mon abonnement', site_url('pricing'))
              . '<p style="font-size:13px;color:#8a857c;">Passé ce délai, les photos et vidéos de vos '
              . 'événements seront définitivement supprimées.</p>';
        return $this->send($to, 'Votre abonnement Archivents a expiré — 30 jours pour renouveler', $body);
    }

    /**
     * Alerte OPÉRATEUR (contact@archivents.com) : nouvelle souscription.
     * Pour un forfait payant, c'est le signal « paiement à encaisser puis
     * activer dans admin → Abonnements ».
     */
    public function operator_new_signup($nom, $email, $plan_nom, $prix, $devise, $is_free, $studio_slug)
    {
        $ope = $this->CI->config->item('contact');
        $to  = ! empty($ope['email']) ? $ope['email'] : $this->cfg['from_email'];

        $body = '<p><b>Nouvelle inscription sur Archivents</b></p>'
              . '<p>Studio : <b>'.html_escape($nom).'</b> ('.html_escape($studio_slug).')<br>'
              . 'E-mail : '.html_escape($email).'<br>'
              . 'Forfait : <b>'.html_escape($plan_nom).'</b>'
              . ($is_free ? ' — gratuit, activé automatiquement.'
                          : ' — '.number_format((float) $prix, 0, ',', ' ').' '.html_escape($devise)
                            .' <b>en attente de paiement</b>.').'</p>'
              . ($is_free ? ''
                  : '<p>Dès réception du paiement (OM/MoMo/virement), activez l\'abonnement :</p>'
                    .$this->button('Ouvrir les abonnements', site_url('admin/subscriptions')));
        return $this->send($to, $is_free
            ? 'Archivents — nouvelle inscription (forfait Test)'
            : 'Archivents — nouvelle souscription '.$plan_nom.' à activer', $body);
    }

    public function reset_link($to, $url)
    {
        $body = '<p>Vous avez demandé la réinitialisation de votre mot de passe Archivents.</p>'
              . '<p>Ce lien est valable <b>1 heure</b>. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail.</p>'
              . $this->button('Réinitialiser mon mot de passe', $url)
              . '<p style="font-size:12px;color:#8a857c;margin-top:16px;word-break:break-all;">'.html_escape($url).'</p>';
        return $this->send($to, 'Réinitialisation de votre mot de passe', $body);
    }

    /* =================================================================
     |  Gabarit HTML
     | ================================================================= */

    protected function button($label, $url)
    {
        return '<p style="margin:24px 0;"><a href="'.$url.'" '
             . 'style="display:inline-block;background:#bd5c33;color:#fff;text-decoration:none;'
             . 'padding:12px 22px;border-radius:6px;font-weight:600;">'.html_escape($label).'</a></p>';
    }

    protected function layout($title, $body)
    {
        return '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"></head>'
             . '<body style="margin:0;background:#faf9f5;font-family:Arial,Helvetica,sans-serif;color:#181919;">'
             . '<div style="max-width:560px;margin:0 auto;padding:32px 24px;">'
             . '<div style="font-family:Georgia,serif;font-size:26px;font-weight:bold;color:#181919;margin-bottom:24px;">'
             . 'Archiv<span style="color:#bd5c33;font-style:italic;">ents</span></div>'
             . '<div style="background:#fff;border:1px solid #e4e2e2;border-radius:12px;padding:28px;line-height:1.6;">'
             . $body
             . '</div>'
             . '<p style="font-size:12px;color:#8a857c;text-align:center;margin-top:24px;">'
             . '© '.date('Y').' Archivents — la plateforme des photographes d\'événement.</p>'
             . '</div></body></html>';
    }
}
