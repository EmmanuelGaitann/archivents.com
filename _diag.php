<?php
/**
 * _diag.php — diagnostic de déploiement Archivents (À SUPPRIMER APRÈS USAGE).
 *
 * Placez ce fichier À LA RACINE du site (à côté de index.php), puis ouvrez :
 *     https://archivents.com/_diag.php
 *
 * Il n'affiche AUCUN secret (mots de passe masqués) — seulement des états.
 * Objectif : voir la VRAIE cause du HTTP 500, que la production masque.
 *
 * >>> SUPPRIMEZ ce fichier dès le diagnostic terminé. <<<
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

function line($label, $val) { printf("%-32s %s\n", $label.' :', $val); }
function ok($b) { return $b ? 'OK' : 'ÉCHEC'; }

echo "===== DIAGNOSTIC ARCHIVENTS =====\n\n";

/* 1. PHP ---------------------------------------------------------------- */
echo "-- PHP --\n";
line('Version PHP', PHP_VERSION);
line('PHP >= 8.0 requis', version_compare(PHP_VERSION, '8.0', '>=') ? 'OK' : 'TROP ANCIEN (corriger dans cPanel > MultiPHP Manager)');
line('SAPI', PHP_SAPI);
echo "\n";

/* 2. Extensions requises ------------------------------------------------ */
echo "-- Extensions --\n";
foreach (array('mysqli','curl','gd','fileinfo','mbstring','openssl','json') as $ext) {
    line($ext, ok(extension_loaded($ext)));
}
echo "\n";

/* 3. Emplacement / fichiers clés ---------------------------------------- */
echo "-- Fichiers --\n";
line('Dossier courant', __DIR__);
line('index.php présent', ok(is_file(__DIR__.'/index.php')));
line('application/ présent', ok(is_dir(__DIR__.'/application')));
line('system/ présent', ok(is_dir(__DIR__.'/system')));
line('.htaccess présent', ok(is_file(__DIR__.'/.htaccess')));
line('.env présent', ok(is_file(__DIR__.'/.env')));
echo "\n";

/* 4. Dossiers accessibles en écriture ----------------------------------- */
echo "-- Écriture (permissions) --\n";
foreach (array('application/logs','application/cache','uploads') as $d) {
    $p = __DIR__.'/'.$d;
    line($d, is_dir($p) ? (is_writable($p) ? 'OK (écriture)' : 'NON INSCRIPTIBLE (chmod 755)') : 'ABSENT');
}
echo "\n";

/* 5. Chargement du .env ------------------------------------------------- */
echo "-- .env (secrets) --\n";
$envloaded = array();
if (is_file(__DIR__.'/.env')) {
    foreach (file(__DIR__.'/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $l) {
        $l = trim($l);
        if ($l === '' || $l[0] === '#' || strpos($l, '=') === false) continue;
        $k = trim(substr($l, 0, strpos($l, '=')));
        $envloaded[$k] = true;
    }
}
foreach (array('R2_ACCESS_KEY_ID','R2_SECRET_ACCESS_KEY','R2_BUCKET','ARCHIVENTS_STORAGE','SMTP_PASS') as $k) {
    line($k, isset($envloaded[$k]) ? 'présent' : 'MANQUANT');
}
echo "\n";

/* 6. Connexion à la base (identifiants PRODUCTION O2Switch) ------------- */
echo "-- Base de données (production) --\n";
$host = '127.0.0.1'; $port = 3306;
$user = 'sc3koga4651_emmanuel';
$pass = 'Emmanuel.01';
$name = 'sc3koga4651_archiven';
line('Hôte', $host.':'.$port);
line('Utilisateur', $user);
line('Base', $name);
mysqli_report(MYSQLI_REPORT_OFF);
$mysqli = @mysqli_connect($host, $user, $pass, $name, $port);
if ($mysqli) {
    line('Connexion', 'OK');
    $r = @mysqli_query($mysqli, "SHOW TABLES");
    line('Nombre de tables', $r ? mysqli_num_rows($r) : 'requête échouée');
    $r2 = @mysqli_query($mysqli, "SELECT COUNT(*) c FROM users");
    line('Comptes (users)', $r2 ? mysqli_fetch_assoc($r2)['c'] : 'table users ABSENTE (importer install.sql)');
    mysqli_close($mysqli);
} else {
    line('Connexion', 'ÉCHEC — '.mysqli_connect_error());
    echo "\n  >> Cause probable : identifiants ou nom de base incorrects,\n";
    echo "     ou base non créée. Vérifiez cPanel > Bases de données MySQL,\n";
    echo "     et que l'utilisateur est bien rattaché à la base.\n";
}
echo "\n";

/* 7. Test du cœur CodeIgniter ------------------------------------------- */
echo "-- CodeIgniter --\n";
line('system/core/CodeIgniter.php', ok(is_file(__DIR__.'/system/core/CodeIgniter.php')));
line('MY_Controller.php', ok(is_file(__DIR__.'/application/core/MY_Controller.php')));
line('MY_URI.php', ok(is_file(__DIR__.'/application/core/MY_URI.php')));
echo "\n";

/* 8. Dernière erreur PHP journalisée par CodeIgniter -------------------- */
echo "-- Dernières erreurs CI (application/logs) --\n";
$logfile = __DIR__.'/application/logs/log-'.date('Y-m-d').'.php';
if (is_file($logfile)) {
    $lines = array_slice(array_filter(file($logfile), function($l){ return strpos($l,'ERROR')===0 || strpos($l,'CRITICAL')===0; }), -8);
    echo $lines ? implode('', $lines) : "(aucune ligne ERROR aujourd'hui)\n";
} else {
    echo "(pas de fichier de log aujourd'hui — soit aucune erreur loguée, soit dossier non inscriptible)\n";
}

echo "\n===== FIN — PENSEZ À SUPPRIMER _diag.php =====\n";
