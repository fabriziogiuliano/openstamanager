<?php

include_once __DIR__.'/../../core.php';

$module = Modules::getModule($id_module);

if ($module['name'] == 'Fatture di vendita') {
    $dir = 'entrata';
} else {
    $dir = 'uscita';
}

$record = $dbo->fetchArray('SELECT * FROM co_documenti WHERE id='.prepare($id_record));
$numero = ($record[0]['numero_esterno'] != '') ? $record[0]['numero_esterno'] : $record[0]['numero'];
$idconto = $record[0]['idconto'];
$idanagrafica = $record[0]['idanagrafica'];

// Preventivo
echo '
    <div class="row">
        <div class="col-md-6">
            {[ "type": "select", "label": "'._('DDT').'", "name": "id_ddt", "required": 1, "values": "query=SELECT id, CONCAT(\'nr. \', if(numero_esterno != \'\', numero_esterno, numero), \' del \', DATE_FORMAT(data, \'%d-%m-%Y\')) AS descrizione, numero, numero_esterno, DATE_FORMAT(data, \'%d-%m-%Y\') AS data FROM dt_ddt WHERE idanagrafica='.prepare($idanagrafica).' AND idstatoddt IN (SELECT id FROM dt_statiddt WHERE descrizione=\'Bozza\') AND idtipoddt=(SELECT id FROM dt_tipiddt WHERE dir='.prepare($dir).') ORDER BY data DESC, numero DESC" ]}
        </div>
    </div>';

echo '
    <div class="row">
        <div id="righeddt" class="col-md-12"></div>
    </div>';

echo '
	<script src="'.$rootdir.'/lib/init.js"></script>';

?>

<script>
	$('#id_ddt').change( function(){
        $('#righeddt').html('<i>Caricamento in corso...</i>');

        $('#righeddt').load(globals.rootdir + '/modules/fatture/add_ddt_righe.php?id_module=' + globals.id_module + '&id_record=' + globals.id_record + '&idddt=' + $(this).find('option:selected').val());
    });
</script>
