<?php

include_once __DIR__.'/../../core.php';

$id_record = $get['idautomezzo'];

// Form di inserimento responsabili automezzo
echo '
<form action="'.$rootdir.'/editor.php?id_module='.Modules::getModule('Automezzi')['id'].'&id_record='.$id_record.'" method="post">
    <input type="hidden" name="op" value="addtech">
    <input type="hidden" name="backto" value="record-edit">
    <input type="hidden" name="id_record" value="'.$id_record.'">

    <div class="row">';

// Tecnico
echo '
        <div class="col-md-6">
            {[ "type": "select", "label": "'._('Tecnico').'", "name": "idtecnico", "required": 1, "values": "query=SELECT an_anagrafiche.idanagrafica AS id, ragione_sociale AS descrizione FROM an_anagrafiche INNER JOIN (an_tipianagrafiche_anagrafiche INNER JOIN an_tipianagrafiche ON an_tipianagrafiche_anagrafiche.idtipoanagrafica=an_tipianagrafiche.idtipoanagrafica) ON an_anagrafiche.idanagrafica=an_tipianagrafiche_anagrafiche.idanagrafica WHERE (descrizione=\'Tecnico\') AND deleted=0 ORDER BY ragione_sociale", "value": "'.$idtecnico.'" ]}
        </div>';

// Data di partenza
echo '
        <div class="col-md-3">
            {[ "type": "date", "label": "'._('Data dal').'", "name": "data_inizio", "required": 1, "class": "text-center", "value": "-now-" ]}
        </div>';

// Data di fine
echo '
        <div class="col-md-3">
            {[ "type": "date", "label": "'._('Data al').'", "name": "data_fine", "value": "", "min-date": "-now-" ]}
        </div>';

echo '
	</div>

    <!-- PULSANTI -->
	<div class="row">
		<div class="col-md-12 text-right">
			<button type="submit" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> '._('Aggiungi').'</button>
		</div>
    </div>
</form>';

echo '
<script src="'.$rootdir.'/lib/init.js"></script>';

echo '
<script type="text/javascript">
    $(function () {
        $("#data_inizio").on("dp.change", function (e) {
            $("#data_fine").data("DateTimePicker").minDate(e.date);
        })
    });
</script>';
