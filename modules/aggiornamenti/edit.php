<?php

include_once __DIR__.'/../../core.php';


if (get_var('Attiva aggiornamenti')) {
    $alerts = [];

    if (!extension_loaded('zip')) {
        $alerts[_('Estensione ZIP')] = _('da abilitare');
    }

    $upload_max_filesize = ini_get('upload_max_filesize');
    $upload_max_filesize = str_replace(['k', 'M'], ['000', '000000'], $upload_max_filesize);
    // Dimensione minima: 16MB
    if ($upload_max_filesize < 16000000) {
        $alerts['upload_max_filesize'] = '16MB';
    }

    $post_max_size = ini_get('post_max_size');
    $post_max_size = str_replace(['k', 'M'], ['000', '000000'], $post_max_size);
    // Dimensione minima: 16MB
    if ($post_max_size < 16000000) {
        $alerts['post_max_size'] = '16MB';
    }

    if (!empty($alerts)) {
        echo '
<div class="alert alert-warning">
    <p>'.str_replace('_CONFIG_', '<b>php.ini</b>', _('Devi modificare il seguenti parametri del file di configurazione PHP (_CONFIG_) per poter caricare gli aggiornamenti')).':<ul>';
        foreach ($alerts as $key => $value) {
            echo '
        <li><b>'.$key.'</b> = '.$value.'</li>';
        }
        echo '
    </ul></p>
</div>';
    }

    echo '
        <div class="row">';
    // Aggiornamento
    echo '
            <div class="col-xs-12 col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title">'._('Carica un aggiornamento').'</h3>
                    </div>
                    <div class="box-body">
                        <form action="'.$rootdir.'/controller.php?id_module='.$id_module.'" method="post" enctype="multipart/form-data" class="form-inline" id="update">
                            <input type="hidden" name="op" value="upload">
                            <input type="hidden" name="type" value="update">

                            <label><input type="file" name="blob"></label>

                            <button type="button" class="btn btn-primary" onclick="if( confirm(\''._('Avviare la procedura?').'\') ){ $(\'#update\').submit(); }">
                                <i class="fa fa-upload"></i> '._('Carica').'...
                            </button>
                        </form>
                    </div>
                </div>
            </div>';

    // Nuovo modulo
    echo '
            <div class="col-xs-12 col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">'._('Carica un nuovo modulo').'</h3>
                    </div>
                    <div class="box-body">
                        <form action="'.$rootdir.'/controller.php?id_module='.$id_module.'" method="post" enctype="multipart/form-data" class="form-inline" id="module">
                            <input type="hidden" name="op" value="upload">
                            <input type="hidden" name="type" value="new">

                            <label><input type="file" name="blob"></label>
                            <button type="button" class="btn btn-primary" onclick="if( confirm(\''._('Avviare la procedura?').'\') ){ $(\'#module\').submit(); }">
                                <i class="fa fa-upload"></i> '._('Carica').'...
                            </button>
                        </form>
                    </div>
                </div>
            </div>';
    echo '
        </div>';
}

// Elenco moduli installati
echo '
<div class="row">
    <div class="col-md-12 col-lg-6">
        <h3>'._('Moduli installati').'</h3>
        <table class="table table-hover table-bordered table-condensed">
            <tr>
                <th>'._('Nome').'</th>
                <th width="50">'._('Versione').'</th>
                <th width="30">'._('Stato').'</th>
                <th width="30">'._('Compatibilità').'</th>
                <th width="20"></th>
            </tr>';

$modules = $dbo->fetchArray('SELECT * FROM zz_modules WHERE parent IS NULL ORDER BY `order` ASC');

$osm_version = Update::getVersion();

foreach ($modules as $module) {
    // STATO
    if (!empty($module['enabled'])) {
        $text = _('Abilitato');
        $text .= ($module['id'] != $id_module) ? '. '._('Clicca per disabilitarlo').'...' : '';
        $stato = '<i class="fa fa-cog fa-spin text-success" data-toggle="tooltip" title="'.$text.'"></i>';
    } else {
        $stato = '<i class="fa fa-cog text-warning" data-toggle="tooltip" title="'._('Non abilitato').'"></i>';
        $class = 'warning';
    }

    // Possibilità di disabilitare o abilitare i moduli tranne quello degli aggiornamenti
    if ($module['id'] != $id_module) {
        if ($module['enabled']) {
            $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Disabilitare questo modulo?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'disable', id: '".$module['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\">".$stato."</a>\n";
        } else {
            $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Abilitare questo modulo?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'enable', id: '".$module['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\"\">".$stato."</a>\n";
        }
    }

    // COMPATIBILITA'
    $compatibilities = explode(',', $module['compatibility']);
    // Controllo per ogni versione se la regexp combacia per dire che è compatibile o meno
    $comp = false;
    foreach ($compatibilities as $compatibility) {
        $comp = (preg_match('/'.$compatibility.'/', $osm_version)) ? true : $comp;
    }

    if ($comp) {
        $compatible = '<i class="fa fa-check-circle text-success" data-toggle="tooltip" title="'._('Compatibile').'"></i>';
        $class = 'success';
    } else {
        $compatible = '<i class="fa fa-warning text-danger" data-toggle="tooltip" title="'._('Non compabitile!')._('Questo modulo è compatibile solo con le versioni').': '.$module['compatibility'].'"></i>';
        $class = 'danger';
    }

    echo '
            <tr class="'.$class.'">
                <td>'.$module['name'].'</td>
                <td align="right">'.$module['version'].'</td>
                <td align="center">'.$stato.'</td>
                <td align="center">'.$compatible.'</td>';

    echo '
                <td>';
    // Possibilità di disinstallare solo se il modulo non è tra quelli predefiniti
    if (empty($module['default'])) {
        echo "
                    <a href=\"javascript:;\" data-toggle='tooltip' title=\""._('Disinstalla')."...\" onclick=\"if( confirm('"._('Vuoi disinstallare questo modulo?').' '._('Tutti i dati salvati andranno persi!')."') ){ if( confirm('"._('Sei veramente sicuro?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'uninstall', id: '".$module['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); } }\"><i class='fa fa-trash-o'></i></a>";
    }
    echo '
                </td>
            </tr>';

    // Prima di cambiare modulo verifico se ci sono sottomoduli
    $submodules = $dbo->fetchArray('SELECT * FROM zz_modules WHERE parent='.prepare($module['id']).' ORDER BY `order` ASC');
    foreach ($submodules as $sub) {
        // STATO
    if (!empty($sub['enabled'])) {
        $text = _('Abilitato');
        $text .= ($sub['id'] != $id_module) ? '. '._('Clicca per disabilitarlo').'...' : '';
        $stato = '<i class="fa fa-cog fa-spin text-success" data-toggle="tooltip" title="'.$text.'"></i>';
    } else {
        $stato = '<i class="fa fa-cog text-warning" data-toggle="tooltip" title="'._('Non abilitato').'"></i>';
        $class = 'warning';
    }

    // Possibilità di disabilitare o abilitare i moduli tranne quello degli aggiornamenti
    if ($sub['id'] != $id_module) {
        if ($sub['enabled']) {
            $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Disabilitare questo modulo?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'disable', id: '".$sub['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\">".$stato."</a>\n";
        } else {
            $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Abilitare questo modulo?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'enable', id: '".$sub['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\"\">".$stato."</a>\n";
        }
    }

    // COMPATIBILITA'
    $compatibilities = explode(',', $sub['compatibility']);
    // Controllo per ogni versione se la regexp combacia per dire che è compatibile o meno
    $comp = false;
        foreach ($compatibilities as $compatibility) {
            $comp = (preg_match('/'.$compatibility.'/', $osm_version)) ? true : $comp;
        }

        if ($comp) {
            $compatible = '<i class="fa fa-check-circle text-success" data-toggle="tooltip" title="'._('Compatibile').'"></i>';
            $class = 'success';
        } else {
            $compatible = '<i class="fa fa-warning text-danger" data-toggle="tooltip" title="'._('Non compabitile!')._('Questo modulo è compatibile solo con le versioni').': '.$sub['compatibility'].'"></i>';
            $class = 'danger';
        }

        echo '
            <tr class="'.$class.'">
                <td><small>&nbsp;&nbsp;- '.$sub['name'].'</small></td>
                <td align="right">'.$sub['version'].'</td>
                <td align="center">'.$stato.'</td>
                <td align="center">'.$compatible.'</td>';

        echo '
                <td>';
    // Possibilità di disinstallare solo se il modulo non è tra quelli predefiniti
    if (empty($sub['default'])) {
        echo "
                    <a href=\"javascript:;\" data-toggle='tooltip' title=\""._('Disinstalla')."...\" onclick=\"if( confirm('"._('Vuoi disinstallare questo modulo?').' '._('Tutti i dati salvati andranno persi!')."') ){ if( confirm('"._('Sei veramente sicuro?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'uninstall', id: '".$sub['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); } }\"><i class='fa fa-trash-o'></i></a>";
    }
        echo '
                </td>
            </tr>';
    }
}

echo '
        </table>
    </div>';

// Widgets
echo '
    <div class="col-md-12 col-lg-6">
        <h3>'._('Widgets').'</h3>
        <table class="table table-hover table-bordered table-condensed">
            <tr>
                <th>'._('Nome').'</th>
                <th width="200">'._('Posizione').'</th>
                <th width="30">'._('Stato').'</th>
                <th width="30">'._('Posizione').'</th>
            </tr>';

$widgets = $dbo->fetchArray('SELECT zz_widgets.id, zz_widgets.name AS widget_name, zz_modules.name AS module_name, zz_widgets.enabled AS enabled, location FROM zz_widgets INNER JOIN zz_modules ON zz_widgets.id_module=zz_modules.id ORDER BY `id_module` ASC, `zz_widgets`.`order` ASC');

$previous = '';

foreach ($widgets as $widget) {
    // Nome modulo come titolo sezione
    if ($widget['module_name'] != $previous) {
        echo '
            <tr>
                <th colspan="4">'.$widget['module_name'].'</th>
            </tr>';
    }

    // STATO
    if ($widget['enabled']) {
        $stato = '<i class="fa fa-cog fa-spin text-success" data-toggle="tooltip" title="'._('Abilitato').'. '._('Clicca per disabilitarlo').'..."></i>';
        $class = 'success';
    } else {
        $stato = '<i class="fa fa-cog text-warning" data-toggle="tooltip" title="'._('Non abilitato').'"></i>';
        $class = 'warning';
    }

    // Possibilità di disabilitare o abilitare i moduli tranne quello degli aggiornamenti
    if ($widget['enabled']) {
        $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Disabilitare questo widget?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'disable_widget', id: '".$widget['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\">".$stato."</a>\n";
    } else {
        $stato = "<a href='javascript:;' onclick=\"if( confirm('"._('Abilitare questo widget?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'enable_widget', id: '".$widget['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\"\">".$stato."</a>\n";
    }

    // POSIZIONE
    if ($widget['location'] == 'controller_top') {
        $location = _('Schermata modulo in alto');
    } elseif ($widget['location'] == 'controller_right') {
        $location = _('Schermata modulo a destra');
    }

    if ($widget['location'] == 'controller_right') {
        $posizione = "<i class='fa fa-arrow-up text-warning' data-toggle='tooltip' title=\""._('Clicca per cambiare la posizione...')."\"></i>&nbsp;<i class='fa fa-arrow-right text-success' data-toggle='tooltip' title=\"\"></i>";
        $posizione = "<a href='javascript:;' onclick=\"if( confirm('"._('Cambiare la posizione di questo widget?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'change_position_widget_top', id: '".$widget['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\"\">".$posizione."</a>\n";
    } elseif ($widget['location'] == 'controller_top') {
        $posizione = "<i class='fa fa-arrow-up text-success' data-toggle='tooltip' title=\"\"></i>&nbsp;<i class='fa fa-arrow-right text-warning' data-toggle='tooltip' title=\""._('Clicca per cambiare la posizione...').'"></i></i>';
        $posizione = "<a href='javascript:;' onclick=\"if( confirm('"._('Cambiare la posizione di questo widget?')."') ){ $.post( '".$rootdir.'/editor.php?id_module='.$id_module."', { op: 'change_position_widget_right', id: '".$widget['id']."' }, function(response){ location.href='".$rootdir.'/controller.php?id_module='.$id_module."'; }); }\"\">".$posizione."</a>\n";
    }

    echo '
            <tr class="'.$class.'">
                <td>'.$widget['widget_name'].'</td>
                <td align="right"><small>'.$location.'</small></td>
                <td align="center">'.$stato.'</td>
                <td align="center">'.$posizione.'</td>
            </tr>';

    $previous = $widget['module_name'];
}

echo '
        </table>
    </div>
</div>';
