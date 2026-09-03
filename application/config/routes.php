<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	https://codeigniter.com/userguide3/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There are three reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router which controller/method to use if those
| provided in the URL cannot be matched to a valid route.
|
|	$route['translate_uri_dashes'] = FALSE;
|
| This is not exactly a route, but allows you to automatically route
| controller and method names that contain dashes. '-' isn't a valid
| class or method name character, so it requires translation.
| When you set this option to TRUE, it will replace ALL dashes in the
| controller and method URI segments.
|
| Examples:	my-controller/index	-> my_controller/index
|		my-controller/my-method	-> my_controller/my_method
*/
// La racine passe par le contrôleur Home (un default_controller CI3 ne peut
// PAS pointer vers un sous-dossier comme "admin/auth"). Home = site vitrine
// SaaS (landing + tarifs + inscription).
$route['default_controller'] = 'home';
$route['404_override'] = '';
$route['translate_uri_dashes'] = FALSE;

// --- Site vitrine (marketing SaaS) ---
$route['pricing']     = 'home/pricing';
$route['tarifs']      = 'home/pricing';
$route['register']    = 'home/register';
$route['inscription'] = 'home/register';
$route['bienvenue']   = 'home/registered';
$route['forgot']         = 'admin/auth/forgot';
$route['reset/(:any)']   = 'admin/auth/reset/$1';
$route['verify-email/(:num)/([a-f0-9]+)'] = 'home/verify_email/$1/$2';
$route['mentions-legales'] = 'home/mentions';
$route['confidentialite']  = 'home/confidentialite';
$route['conditions']       = 'home/conditions';

// Contrôle de santé post-déploiement (réécritures + PHP + base de données).
$route['ping'] = 'home/ping';

// --- Back-office ---
$route['admin'] = 'admin/dashboard';
$route['login'] = 'admin/auth/login';
$route['logout'] = 'admin/auth/logout';

// --- Site public (accès par slug d'événement) ---
// Les routes spécifiques DOIVENT précéder le catch-all "e/(slug)".
$route['e/([A-Za-z0-9_-]+)/album/(:num)']    = 'gallery/album/$1/$2';
$route['e/([A-Za-z0-9_-]+)/photos']          = 'gallery/photos/$1';   // AJAX (scroll infini)
$route['e/([A-Za-z0-9_-]+)/password']        = 'gallery/password/$1';
$route['e/([A-Za-z0-9_-]+)/download/(:num)'] = 'gallery/download/$1/$2';
$route['e/([A-Za-z0-9_-]+)']                 = 'gallery/view/$1';
