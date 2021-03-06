<?php

include_once __DIR__.'/../../core.php';

$enable_readonly = !get_var('Modifica Viste di default');
$record = $records[0];

echo '
<form action="" method="post" role="form">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="op" value="update">

	<div class="pull-right">
		<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> '._('Salva modifiche').'</button>
	</div>
	<div class="clearfix"></div><br>

	<!-- DATI -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title">'._('Opzioni di visualizzazione').'</h3>
		</div>

		<div class="panel-body">';
$options = ($record['options2'] == '') ? $record['options'] : $record['options2'];
if ($options == 'menu') {
    echo '
			<p><strong>'._('Il modulo che stai analizzando è un semplice menu').'.</strong></p>';
} elseif ($options == 'custom') {
    echo '
			<p><strong>'._("Il modulo che stai analizzando possiede una struttura complessa, che prevede l'utilizzo di file personalizzati per la gestione delle viste").'.</strong></p>';
}

echo '
			<div class="row">
				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Codice del modulo').'", "name": "name", "value": "'.$record['name'].'", "readonly": "1" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Nome del modulo').'", "name": "title", "value": "'.$record['title'].'", "help": "Il nome che identifica il modulo" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-6">
					{[ "type": "textarea", "label": "'._('Query di default').'", "name": "options", "value": "'.str_replace(']}', '] }', $record['options']).'", "readonly": "1", "class": "autosize" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "textarea", "label": "'._('Query personalizzata').'", "name": "options2", "value": "'.str_replace(']}', '] }', $record['options2']).'", "class": "autosize", "help": "La query in sostituzione a quella di default: custom, menu oppure <SQL>" ]}
				</div>
			</div>';
if ($options != '' && $options != 'menu' && $options != 'custom') {
    $module_query = $options;
    $total = Modules::getQuery($id_record);
    if (strpos($module_query, '|select|') === false) {
        $module_query = json_decode($module_query, true);
        $module_query = $module_query['main_query'][0]['query'];
    }
    $module_query = str_replace('|select|', $total['select'], $module_query);
    $module_query = str_replace('|period_start|', $_SESSION['period_start'], $module_query);
    $module_query = str_replace('|period_end|', $_SESSION['period_end'], $module_query);

    echo '
			<div class="row">
				<div class="col-xs-12 col-md-12">
					<p><strong>'._('Query risultante').':</strong></p>
					<p>'.$module_query.'</p>
				</div>
			</div>';
}

echo '
		</div>
	</div>
</form>';

if (!empty($options) && $options != 'custom') {
    echo '

<form action="" method="post" role="form">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="op" value="fields">

	<div class="row">
		<div class="col-xs-12 col-md-9">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">'._('Campi disponibili').'</h3>
				</div>

				<div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12 text-right">
                            <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> '._('Salva').'</button>
                        </div>
                    </div>
                    <hr>

					<div class="data">';

    $key = 0;
    $fields = $dbo->fetchArray('SELECT * FROM zz_views WHERE id_module='.prepare($record['id']).' ORDER BY `order` ASC');
    foreach ($fields as $key => $field) {
        $editable = !($field['default'] && $enable_readonly);

        echo '
					<div class="box ';
        if ($field['enabled']) {
            echo 'box-success';
        } else {
            echo 'box-danger';
        }
        echo '">
							<div class="box-header with-border">
								<h3 class="box-title">
									<a data-toggle="collapse" href="#field-'.$field['id'].'">'.str_replace('_POSITION_', $field['order'], _('Campo in posizione _POSITION_')).' ('.$field['name'].')</a>
								</h3>';
        if ($editable) {
            echo '
                                <a class="btn btn-danger ask pull-right" data-backto="record-edit" data-id="'.$field['id'].'">
                                    <i class="fa fa-trash"></i> '._('Elimina').'
                                </a>';
        }
        echo '
							</div>
							<div id="field-'.$field['id'].'" class="box-body collapse">
								<div class="row">
									<input type="hidden" value="'.$field['id'].'" name="id['.$key.']">

									<div class="col-xs-12 col-md-6">
										{[ "type": "text", "label": "'._('Nome').'", "name": "name['.$key.']", "value": "'.$field['name'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Nome con cui il campo viene identificato e visualizzato nella tabella" ]}
									</div>

									<div class="col-xs-12 col-md-6">
										{[ "type": "text", "label": "'._('Query prevista').'", "name": "query['.$key.']", "value": "'.$field['query'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "required": "1", "help": "Nome effettivo del campo sulla tabella oppure subquery che permette di ottenere il valore del campo" ]}
									</div>
								</div>

								<div class="row">
									<div class="col-xs-12 col-md-6">
										{[ "type": "select", "label": "'._('Gruppi con accesso').'", "name": "gruppi['.$key.'][]", "multiple": "1",  "values": "query=SELECT id, nome AS descrizione FROM zz_groups ORDER BY id ASC", "value": "';
        $results = $dbo->fetchArray('SELECT GROUP_CONCAT(DISTINCT id_gruppo SEPARATOR \',\') AS gruppi FROM zz_group_view WHERE id_vista='.prepare($field['id']));

        echo $results[0]['gruppi'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Gruppi di utenti in grado di visualizzare questo campo" ]}
									</div>

									<div class="col-xs-12 col-md-6">
										{[ "type": "select", "label": "'._('Visibilità').'", "name": "enabled['.$key.']", "values": "list=\"0\":\"'._('Nascosto (variabili di stato)').'\",\"1\": \"'._('Visibile nella sezione').'\"", "value": "'.$field['enabled'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Stato del campo: visibile nella tabella oppure nascosto" ]}
									</div>
								</div>

								<div class="row">
									<div class="col-xs-12 col-md-3">
										{[ "type": "checkbox", "label": "'._('Ricercabile').'", "name": "search['.$key.']", "value": "'.$field['search'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Indica se il campo è ricercabile" ]}
									</div>

									<div class="col-xs-12 col-md-3">
										{[ "type": "checkbox", "label": "'._('Ricerca lenta').'", "name": "slow['.$key.']", "value": "'.$field['slow'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Indica se la ricerca per questo campo è lenta (da utilizzare nel caso di evidenti rallentamenti, mostra solo un avviso all\'utente)" ]}
									</div>

									<div class="col-xs-12 col-md-3">
										{[ "type": "checkbox", "label": "'._('Sommabile').'", "name": "sum['.$key.']", "value": "'.$field['summable'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Indica se il campo è da sommare" ]}
									</div>

                                    <div class="col-xs-12 col-md-3">
										{[ "type": "checkbox", "label": "'._('Formattabile').'", "name": "format['.$key.']", "value": "'.$field['format'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "Indica se il campo è formattabile in modo automatico" ]}
									</div>
								</div>

								<div class="row">
									<div class="col-xs-12 col-md-6">
										{[ "type": "text", "label": "'._('Ricerca tramite').'", "name": "search_inside['.$key.']", "value": "'.$field['search_inside'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "'._('Query personalizzata per la ricerca (consigliata per colori e icone)').'" ]}
									</div>

									<div class="col-xs-12 col-md-6">
										{[ "type": "text", "label": "'._('Ordina tramite').'", "name": "order_by['.$key.']", "value": "'.$field['order_by'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ', "help": "'._("Query personalizzata per l'ordinamento (date e numeri formattati tramite query)").'" ]}
									</div>
								</div>
							</div>
						</div>';
    }
    echo '
				</div>

                <div class="row">
                    <div class="col-xs-12 text-right">
                        <button type="button" class="btn btn-info" id="add"><i class="fa fa-plus"></i> '._('Aggiungi').'</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> '._('Salva').'</button>
                    </div>
                </div>

				</div>
			</div>
		</div>

		<div class="col-xs-12 col-md-3">
			<div class="panel panel-primary">
				<div class="panel-heading">
					<h3 class="panel-title">'._('Ordine di visualizzazione').'</h3>
				</div>

				<div class="panel-body sortable">';

    foreach ($fields as $field) {
        echo '
            <p data-id="'.$field['id'].'">
                <i class="fa fa-sort"></i>
                ';
        if ($field['enabled']) {
            echo '<strong class="text-success">'.$field['name'].'</strong>';
        } else {
            echo '<span class="text-danger">'.$field['name'].'</span>';
        }
        echo '
            </p>';
    }

    echo '
			</div>
		</div>
	</div>
</form>';

    echo '
<form class="hide" id="template">
	<div class="box">
		<div class="box-header with-border">
			<h3 class="box-title">'._('Nuovo campo').'</h3>
		</div>
		<div class="box-body">
			<div class="row">
				<input type="hidden" value="" name="id[-id-]">

				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Nome').'", "name": "name[-id-]" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Query prevista').'", "name": "query[-id-]" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-6">
					{[ "type": "select", "label": "'._('Gruppi con accesso').'", "name": "gruppi[-id-][]", "multiple": "1",  "values": "query=SELECT id, nome AS descrizione FROM zz_groups ORDER BY id ASC" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "select", "label": "'._('Visibilità').'", "name": "enabled[-id-]", "values": "list=\"0\":\"'._('Nascosto (variabili di stato)').'\",\"1\": \"'._('Visibile nella sezione').'\"" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-3">
					{[ "type": "checkbox", "label": "'._('Ricercabile').'", "name": "search[-id-]" ]}
				</div>

				<div class="col-xs-12 col-md-3">
					{[ "type": "checkbox", "label": "'._('Ricerca lenta').'", "name": "slow[-id-]" ]}
				</div>

				<div class="col-xs-12 col-md-3">
					{[ "type": "checkbox", "label": "'._('Sommabile').'", "name": "sum[-id-]" ]}
				</div>

                <div class="col-xs-12 col-md-3">
					{[ "type": "checkbox", "label": "'._('Formattabile').'", "name": "format[-id-]" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Ricerca tramite').'", "name": "search_inside[-id-]" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "text", "label": "'._('Ordina tramite').'", "name": "order_by[-id-]" ]}
				</div>
			</div>
		</div>
	</div>
</form>';

    echo '
<form action="" method="post" role="form">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="op" value="filters">

    <div class="col-xs-12 col-md-12">
        <div class="panel panel-warning">
            <div class="panel-heading">
                <h3 class="panel-title">'._('Filtri per gruppo di utenti').'</h3>
            </div>

            <div class="panel-body">
                <div class="row">
                    <div class="col-xs-12 text-right">
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> '._('Salva').'</button>
                    </div>
                </div>
                <hr>

                <div class="data">';

    $num = 0;
    $additionals = $dbo->fetchArray('SELECT * FROM zz_group_module WHERE idmodule='.prepare($record['id']).' ORDER BY `id` ASC');
    foreach ($additionals as $num => $additional) {
        $editable = !($additional['default'] && $enable_readonly);

        echo '
                    <div class="box ';
        if ($additional['enabled']) {
            echo 'box-success';
        } else {
            echo 'box-danger';
        }
        echo '">
                            <div class="box-header with-border">
                                <h3 class="box-title">
                                    <a data-toggle="collapse" href="#additional-'.$additional['id'].'">'.str_replace('_NUM_', $num, _('Filtro _NUM_')).'</a>
                                </h3>';
        if ($editable) {
            echo '
                                <a class="btn btn-danger ask pull-right" data-backto="record-edit" data-op="delete_filter" data-id="'.$additional['id'].'">
                                    <i class="fa fa-trash"></i> '._('Elimina').'
                                </a>';
        }
        echo '
                                <a class="btn btn-warning ask pull-right" data-backto="record-edit" data-msg="'.($additional['enabled'] ? _('Disabilitare questo elemento?') : _('Abilitare questo elemento?')).'" data-op="change" data-id="'.$additional['id'].'" data-class="btn btn-lg btn-warning" data-button="'.($additional['enabled'] ? _('Disabilita') : _('Abilita')).'">
                                    <i class="fa fa-eye-slash"></i> '.($additional['enabled'] ? _('Disabilita') : _('Abilita')).'
                                </a>';
        echo '
                            </div>
                            <div id="additional-'.$additional['id'].'" class="box-body collapse">
                                <div class="row">
                                    <input type="hidden" value="'.$additional['id'].'" name="id['.$num.']">

                                    <div class="col-xs-12 col-md-6">
                                        {[ "type": "textarea", "label": "'._('Query').'", "name": "query['.$num.']", "value": "'.$additional['clause'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ' ]}
                                    </div>

                                    <div class="col-xs-12 col-md-3">
                                        {[ "type": "select", "label": "'._('Gruppo').'", "name": "gruppo['.$num.']",  "values": "query=SELECT id, nome AS descrizione FROM zz_groups ORDER BY id ASC", "value": "'.$additional['idgruppo'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ' ]}
                                    </div>

                                    <div class="col-xs-12 col-md-3">
                                        {[ "type": "select", "label": "'._('Posizione').'", "name": "position['.$num.']", "values": "list=\"0\":\"'._('WHERE').'\",\"1\": \"'._('HAVING').'\"", "value": "'.$additional['position'].'"';
        if (!$editable) {
            echo ', "readonly": "1"';
        }
        echo ' ]}
                                    </div>
                                </div>
                            </div>
                        </div>';
    }
    echo '
                </div>

                <div class="row">
                    <div class="col-xs-12 text-right">
                        <button type="button" class="btn btn-info" id="add"><i class="fa fa-plus"></i> '._('Aggiungi').'</button>
                        <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> '._('Salva').'</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>';

    echo '
<form class="hide" id="template_filter">
	<div class="box">
		<div class="box-header with-border">
			<h3 class="box-title">'._('Nuovo filtro').'</h3>
		</div>
		<div class="box-body">
			<div class="row">
				<input type="hidden" value="" name="id[-id-]">

				<div class="col-xs-12 col-md-6">
					{[ "type": "textarea", "label": "'._('Query').'", "name": "query[-id-]" ]}
				</div>

				<div class="col-xs-12 col-md-3">
					{[ "type": "select", "label": "'._('Gruppo').'", "name": "gruppo[-id-]", "values": "query=SELECT id, nome AS descrizione FROM zz_groups ORDER BY id ASC" ]}
				</div>

                <div class="col-xs-12 col-md-3">
                    {[ "type": "select", "label": "'._('Posizione').'", "name": "position[-id-]",  "list=\"0\":\"'._('WHERE').'\",\"1\": \"'._('HAVING').'\"" ]}
			</div>
		</div>
	</div>
</form>';

    echo '
<script>
function replaceAll(str, find, replace) {
  return str.replace(new RegExp(find, "g"), replace);
}

$(document).ready(function(){
	var n = '.$key.';
	$(document).on("click", "#add", function(){
		$("#template .superselect, #template .superselectajax").select2().select2("destroy");
		n++;
		var text = replaceAll($("#template").html(), "-id-", "" + n);
		$(this).parent().parent().parent().find(".data").append(text);
		start_superselect();
	});

    var i = '.$num.';
	$(document).on("click", "#add_filter", function(){
		$("#template_filter .superselect, #template_filter .superselectajax").select2().select2("destroy");
		i++;
		var text = replaceAll($("#template_filter").html(), "-id-", "" + i);
		$(this).parent().parent().parent().find(".data").append(text);
		start_superselect();
	});

	$( ".sortable" ).disableSelection();
	$(".sortable").each(function() {
		$(this).sortable({
            axis: "y",
			cursor: "move",
			dropOnEmpty: true,
			scroll: true,
			start: function(event, ui) {
				ui.item.data("start", ui.item.index());
			},
			update: function(event, ui) {
				$.get("'.$rootdir.'/actions.php", {
					id: ui.item.data("id"),
					id_module: '.$id_module.',
					id_record: '.$id_record.',
					op: "update_position",
					start: ui.item.data("start"),
					end: ui.item.index()
				});
			}
		});
	});
});
</script>';
}
