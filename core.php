<?php

// Impostazioni per la corretta interpretazione di UTF-8
header('Content-Type: text/html; charset=UTF-8');

$handler = null;
if (extension_loaded('mbstring')) {
    mb_internal_encoding('UTF-8');
    mb_http_output('UTF-8');
    mb_http_input('UTF-8');
    mb_language('uni');
    mb_regex_encoding('UTF-8');
    $handler = 'mb_output_handler';
}
ob_start($handler);

// Impostazioni di configurazione PHP
date_default_timezone_set('Europe/Rome');

// Caricamento delle impostazioni personalizzabili
if (file_exists(__DIR__.'/config.inc.php')) {
    include_once __DIR__.'/config.inc.php';
}

// Individuazione dei percorsi di base
$docroot = __DIR__;
$rootdir = substr($_SERVER['SCRIPT_NAME'], 0, strrpos($_SERVER['SCRIPT_NAME'], '/'));
if (strrpos($rootdir, '/'.basename($docroot).'/') !== false) {
    $rootdir = substr($rootdir, 0, strrpos($rootdir, '/'.basename($docroot).'/')).'/'.basename($docroot);
}
$rootdir = str_replace('%2F', '/', rawurlencode($rootdir));

// Aggiunta delle variabili globali
define('DOCROOT', $docroot);
define('ROOTDIR', $rootdir);

// Caricamento delle dipendenze e delle librerie del progetto
require_once __DIR__.'/vendor/autoload.php';

// Redirect al percorso HTTPS se impostato nella configurazione
if (!empty($redirectHTTPS) && !isHTTPS(true)) {
    header('HTTP/1.1 301 Moved Permanently');
    header('Location: https://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']);
    exit();
}

// Forzamento del debug
// $debug = true;

// Logger per la segnalazione degli errori
$logger = new Monolog\Logger(_('OpenSTAManager'));
$logger->pushProcessor(new Monolog\Processor\UidProcessor());
$logger->pushProcessor(new Monolog\Processor\WebProcessor());

use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

$handlers = [];
// File di log di base (logs/all.log)
$handlers[] = new StreamHandler(__DIR__.'/logs/error.log', Monolog\Logger::ERROR);
$handlers[] = new StreamHandler(__DIR__.'/logs/setup.log', Monolog\Logger::EMERGENCY);

// Impostazioni di debug
if (!empty($debug)) {
    // Ignoramento degli avvertimenti e delle informazioni relative alla deprecazione di componenti
    if (empty($strict)) {
        error_reporting(E_ALL & ~E_NOTICE & ~E_USER_DEPRECATED);
    }

    // File di log ordinato in base alla data
    $handlers[] = new RotatingFileHandler(__DIR__.'/logs/error.log', 0, Monolog\Logger::ERROR);
    $handlers[] = new RotatingFileHandler(__DIR__.'/logs/setup.log', 0, Monolog\Logger::EMERGENCY);

    if (version_compare(PHP_VERSION, '5.5.9') >= 0) {
        $prettyPageHandler = new Whoops\Handler\PrettyPageHandler();

        // Imposta Whoops come gestore delle eccezioni di default
        $whoops = new Whoops\Run();
        $whoops->pushHandler($prettyPageHandler);

        // Abilita la gestione degli errori nel caso la richiesta sia di tipo AJAX
        if (\Whoops\Util\Misc::isAjaxRequest()) {
            $whoops->pushHandler(new Whoops\Handler\JsonResponseHandler());
        }

        $whoops->register();
    }

    // Istanziamento della barra di debug
    $debugbar = new DebugBar\StandardDebugBar();
    $debugbar->addCollector(new DebugBar\Bridge\MonologCollector($logger));
} else {
    // Disabilita la segnalazione degli errori
    error_reporting(0);
}

// Imposta il formato di salvataggio dei log
$monologFormatter = new Monolog\Formatter\LineFormatter('[%datetime%] %channel%.%level_name%: %message% %extra%'.PHP_EOL);
foreach ($handlers as $handler) {
    $logger->pushHandler($handler->setFormatter($monologFormatter));
}

// Imposta Monolog come gestore degli errori (si sovrappone a Whoops)
Monolog\ErrorHandler::register($logger);

// Istanziamento della gestione di date e numeri
$formatter = !empty($formatter) ? $formatter : [];
Translator::setLocaleFormatter($formatter);

// Istanziamento del gestore delle traduzioni del progetto
$lang = !empty($lang) ? $lang : 'it';
$translator = new Translator($lang);
$translator->addLocalePath($docroot.'/locale');
$translator->addLocalePath($docroot.'/modules/*/locale');

// Individuazione di versione e revisione del progetto
$version = Update::getVersion();
$revision = Update::getRevision();

if (!API::isAPIRequest()) {
    session_set_cookie_params(0, $rootdir);
    session_start();
}

$dbo = Database::getConnection();

$continue = $dbo->isInstalled() && Auth::check() && !Update::isUpdateAvailable();

// Controllo sulla presenza dei permessi di accesso basilari
if (!$continue && slashes($_SERVER['SCRIPT_FILENAME']) != slashes(DOCROOT.'/index.php')) {
    redirect(ROOTDIR.'/index.php?op=logout');
    exit();
}

// Operazione aggiuntive (richieste non API)
if (!API::isAPIRequest()) {
    /*
    // Controllo CSRF
    if(!CSRF::getInstance()->validate()){
        die(_('Constrollo CSRF fallito!'));
    }*/

    // Aggiunta del wrapper personalizzato per la generazione degli input
    if (!empty($HTMLWrapper)) {
        HTMLBuilder\HTMLBuilder::setWrapper($HTMLWrapper);
    }

    // Aggiunta dei gestori personalizzati per la generazione degli input
    foreach ((array) $HTMLHandlers as $key => $value) {
        HTMLBuilder\HTMLBuilder::setHandler($key, $value);
    }

    // Aggiunta dei gestori per componenti personalizzate
    foreach ((array) $HTMLManagers as $key => $value) {
        HTMLBuilder\HTMLBuilder::setManager($key, $value);
    }

    // Registrazione globale del template per gli input HTML
    register_shutdown_function('translateTemplate');

    // Impostazione della sessione di base
    $_SESSION['infos'] = (array) $_SESSION['infos'];
    $_SESSION['warnings'] = (array) $_SESSION['warnings'];
    $_SESSION['errors'] = (array) $_SESSION['errors'];

    // Imposto il periodo di visualizzazione dei record dal 01-01-yyy al 31-12-yyyy
    if (!empty($_GET['period_start'])) {
        $_SESSION['period_start'] = $_GET['period_start'];
        $_SESSION['period_end'] = $_GET['period_end'];
    } elseif (!isset($_SESSION['period_start'])) {
        $_SESSION['period_start'] = date('Y').'-01-01';
        $_SESSION['period_end'] = date('Y').'-12-31';
    }

    // Impostazione del tema grafico di default
    $theme = !empty($theme) ? $theme : 'default';

    $assets = $rootdir.'/assets/dist';
    $css = $assets.'/css';
    $js = $assets.'/js';
    $img = $assets.'/img';

    // CSS di base del progetto
    $css_modules = [];

    $css_modules[] = $css.'/app.min.css';
    $css_modules[] = $css.'/style.min.css';
    $css_modules[] = $css.'/themes.min.css';
    $css_modules[] = [
        'href' => $css.'/print.min.css',
        'media' => 'print',
    ];

    // JS di base del progetto
    $jscript_modules = [];

    $jscript_modules[] = $js.'/app.min.js';
    $jscript_modules[] = $js.'/custom.min.js';
    $jscript_modules[] = $js.'/i18n/parsleyjs/'.$lang.'.min.js';
    $jscript_modules[] = $js.'/i18n/select2/'.$lang.'.min.js';
    $jscript_modules[] = $js.'/i18n/moment/'.$lang.'.min.js';
    $jscript_modules[] = $js.'/i18n/fullcalendar/'.$lang.'.min.js';

    if (Auth::check()) {
        $jscript_modules[] = $rootdir.'/lib/functions.js';
        $jscript_modules[] = $rootdir.'/lib/init.js';
    }

    if ($continue) {
        if (!empty($debugbar)) {
            $debugbar->addCollector(new DebugBar\DataCollector\PDO\PDOCollector($dbo->getPDO()));
        }

        $id_module = filter('id_module');
        $id_record = filter('id_record');
        $id_plugin = filter('id_plugin');
        $id_parent = filter('id_parent');

        $user = Auth::user();

        if (!empty($id_module)) {
            $module = Modules::getModule($id_module);

            $pageTitle = $module['title'];

            Permissions::addModule($id_module);
        }

        if (!empty($skip_permissions)) {
            Permissions::skip();
        }

        Permissions::check();

        // Retrocompatibilità
        $user_idanagrafica = $user['idanagrafica'];

        $rs = $dbo->fetchArray('SELECT * FROM `zz_modules` LEFT JOIN (SELECT `idmodule`, `permessi` FROM `zz_permissions` WHERE `idgruppo`=(SELECT `idgruppo` FROM `zz_users` WHERE `idutente`='.prepare($_SESSION['idutente']).')) AS `zz_permissions` ON `zz_modules`.`id`=`zz_permissions`.`idmodule` LEFT JOIN (SELECT `idmodule`, `clause` FROM `zz_group_module` WHERE `idgruppo`=(SELECT `idgruppo` FROM `zz_users` WHERE `idutente`='.prepare($_SESSION['idutente']).')) AS `zz_group_module` ON `zz_modules`.`id`=`zz_group_module`.`idmodule`');

        $modules_info = [];
        for ($i = 0; $i < count($rs); ++$i) {
            foreach ($rs[$i] as $name => $value) {
                if ($name == 'permessi' && (Auth::admin() || $value == null)) {
                    if (Auth::admin()) {
                        $value = 'rw';
                    } else {
                        $value = '-';
                    }
                }
                if ($name != 'idmodule' && $name != 'updated_at' && $name != 'created_at' && $name != 'clause') {
                    $modules_info[$rs[$i]['name']][$name] = $value;
                } elseif ($name == 'clause') {
                    $additional_where[$rs[$i]['name']] = !empty($value) ? ' AND '.$value : $value;
                }
            }

            $modules_info[$rs[$i]['id']]['name'] = $rs[$i]['name'];
        }
    }

    // Istanziamento di HTMLHelper (retrocompatibilità)
    $html = new HTMLHelper();
}

// Variabili GET e POST
$post = Filter::getPOST();
$get = Filter::getGET();
