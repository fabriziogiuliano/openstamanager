<?php

include_once __DIR__.'/../../core.php';

// Prezzo modificabile solo se l'utente loggato è un tecnico (+ può vedere i prezzi) o se è amministratore
$rs = $dbo->fetchArray('SELECT nome FROM zz_groups WHERE id IN(SELECT idgruppo FROM zz_users WHERE idutente='.prepare($_SESSION['idutente']).')');
for ($i = 0; $i < count($rs); ++$i) {
    $gruppi[$i] = $rs[$i]['nome'];
}

$can_edit_prezzi = (in_array('Amministratori', $gruppi)) || (get_var('Mostra i prezzi al tecnico') == 1 && (in_array('Tecnici', $gruppi)));

$idriga = get('idriga');
$idautomezzo = (get('idautomezzo') == 'undefined') ? '' : get('idautomezzo');

if (empty($idriga)) {
    $op = 'addarticolo';
    $button = '<i class="fa fa-plus"></i> '._('Aggiungi');

    // valori default
    $idarticolo = '';
    $descrizione = '';
    $qta = 1;
    $um = '';

    $prezzo_vendita = '0';

    $sconto_unitario = 0;

    $idimpianto = 0;
} else {
    $op = 'editarticolo';
    $button = '<i class="fa fa-edit"></i> '._('Modifica');

    // carico record da modificare
    $q = "SELECT *, (SELECT codice FROM mg_articoli WHERE id=mg_articoli_interventi.idarticolo) AS codice_articolo, (SELECT CONCAT(codice, ' - ', descrizione) FROM mg_articoli WHERE id=mg_articoli_interventi.idarticolo) AS descrizione_articolo FROM mg_articoli_interventi WHERE id=".prepare($idriga);
    $rsr = $dbo->fetchArray($q);

    $idarticolo = $rsr[0]['idarticolo'];
    $codice_articolo = $rsr[0]['codice_articolo'];
    $descrizione = $rsr[0]['descrizione'];
    $descrizione_articolo = $rsr[0]['descrizione_articolo']; // necessario per inizializzazione select2 idarticolo
    $qta = $rsr[0]['qta'];
    $um = $rsr[0]['um'];

    $prezzo_vendita = $rsr[0]['prezzo_vendita'];

    $sconto_unitario = $rsr[0]['sconto_unitario'];
    $tipo_sconto = $rsr[0]['tipo_sconto'];

    $idautomezzo = $rsr[0]['idautomezzo'];

    $idimpianto = $rsr[0]['idimpianto'];
}

// Lettura idanagrafica cliente e percentuale di sconto/rincaro in base al listino
$rs = $dbo->fetchArray('SELECT idanagrafica FROM in_interventi WHERE id='.prepare($id_record));
$idanagrafica = $rs[0]['idanagrafica'];

/*
    Form di inserimento
*/
echo '
<form id="add_form" action="'.$rootdir.'/editor.php?id_module='.$id_module.'&id_record='.$id_record.'" method="post">
    <input type="hidden" name="op" value="'.$op.'">
    <input type="hidden" id="idautomezzo" name="idautomezzo" value="'.$idautomezzo.'">
    <input type="hidden" name="idriga" value="'.$idriga.'">';

if ($idarticolo != '') {
    echo '
    <input type="hidden" id="idarticolo_originale" name="idarticolo_originale" value="'.$idarticolo.'">';
}

// Articolo
echo '
    <div class="row">
        <div class="col-md-6">
            {[ "type": "select", "label": "'._('Articolo').'", "name": "idarticolo", "required": 1, "value": "'.$idarticolo.'", "ajax-source": "articoli" ]}
        </div>
    </div>';

// Descrizione
echo '
    <div class="row">
        <div class="col-md-12">
            {[ "type": "textarea", "label": "'._('Descrizione').'", "name": "descrizione", "required": 1, "value": "'.$descrizione.'" ]}
        </div>
    </div>
    <br>';

// Quantità
echo '
    <div class="row">
        <div class="col-md-4">
            {[ "type": "number", "label": "'._('Q.tà').'", "name": "qta", "required": 1, "value": "'.$qta.'", "decimals": "qta" ]}
        </div>';

// Unità di misura
echo '
        <div class="col-md-4">
            {[ "type": "select", "label": "'._('Unità di misura').'", "icon-after": "add|'.Modules::getModule('Unità di misura')['id'].'", "name": "um", "value": "'.$um.'", "ajax-source": "misure" ]}
        </div>';

// Impianto
echo '
        <div class="col-md-4">
            {[ "type": "select", "label": "'._('Impianto su cui installare').'", "name": "idimpianto", "value": "'.$idimpianto.'", "ajax-source": "impianti" ]}
        </div>
    </div>';

// Prezzo di vendita
echo '
    <div class="row">
        <div class="col-md-6">
            {[ "type": "number", "label": "'._('Costo unitario').'", "name": "prezzo_vendita", "required": 1, "value": "'.$prezzo_vendita.'", "icon-after": "&euro;" ]}
        </div>';

// Sconto
echo '
        <div class="col-md-6">
            {[ "type": "number", "label": "'._('Sconto unitario').'", "name": "sconto", "icon-after": "choice|untprc|'.$tipo_sconto.'", "value": "'.$sconto_unitario.'" ]}
        </div>
    </div>';

// Informazioni aggiuntive
echo '
    <div class="row" id="prezzi_articolo">
        <div class="col-md-4 text-center">
            <button type="button" class="btn btn-sm btn-info btn-block disabled" onclick="$(\'#prezzi\').toggleClass(\'hide\'); $(\'#prezzi\').load(\''.$rootdir."/ajax_autocomplete.php?module=Articoli&op=getprezzi&idarticolo=' + $('#idarticolo option:selected').val() + '&idanagrafica=".$idanagrafica.'\');" disabled>
                <i class="fa fa-search"></i> '._('Visualizza ultimi prezzi (cliente)').'
            </button>
            <div id="prezzi" class="hide"></div>
        </div>

        <div class="col-md-4 text-center">
            <button type="button" class="btn btn-sm btn-info btn-block disabled" onclick="$(\'#prezziacquisto\').toggleClass(\'hide\'); $(\'#prezziacquisto\').load(\''.$rootdir."/ajax_autocomplete.php?module=Articoli&op=getprezziacquisto&idarticolo=' + $('#idarticolo option:selected').val() + '&idanagrafica=".$idanagrafica.'\');" disabled>
                <i class="fa fa-search"></i> '._('Visualizza ultimi prezzi (acquisto)').'
            </button>
            <div id="prezziacquisto" class="hide"></div>
        </div>

        <div class="col-md-4 text-center">
            <button type="button" class="btn btn-sm btn-info btn-block disabled" onclick="$(\'#prezzivendita\').toggleClass(\'hide\'); $(\'#prezzivendita\').load(\''.$rootdir."/ajax_autocomplete.php?module=Articoli&op=getprezzivendita&idarticolo=' + $('#idarticolo option:selected').val() + '&idanagrafica=".$idanagrafica.'\');" disabled>
                <i class="fa fa-search"></i> '._('Visualizza ultimi prezzi (vendita)').'
            </button>
            <div id="prezzivendita" class="hide"></div>
        </div>
    </div>
    <br>';

echo '
    <script>
    $(document).ready(function () {
        $("#idarticolo").on("change", function(){
            $("#prezzi_articolo button").attr("disabled", !$(this).val());
            if($(this).val()){
                $("#prezzi_articolo button").removeClass("disabled");

                session_set("superselect,idarticolo", $(this).val(), 0);
                $data = $(this).selectData();
                $("#prezzo").val($data.prezzo_vendita);
                $("#descrizione").val($data.descrizione);
                $("#um").selectSetNew($data.um, $data.um);
            }else{
                $("#prezzi_articolo button").addClass("disabled");
            }

            $("#prezzi").html("");
            $("#prezzivendita").html("");
            $("#prezziacquisto").html("");
        });
    });
    </script>';

echo '

    <!-- PULSANTI -->
	<div class="row">
		<div class="col-md-12 text-right">
			<button type="submit" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> '._('Aggiungi').'</button>
		</div>
    </div>
</form>';

echo '
	<script src="'.$rootdir.'/lib/init.js"></script>';

?>

<script type="text/javascript">
    $(document).ready(function() {
        $('#add_form').ajaxForm({
            beforeSubmit: function(){
                return ($('#idarticolo').val() && $('#qta').val() && $('#qta').val());
            },

            success: function(){
                $('#bs-popup').modal('hide');

                // ricarico la pagina ajax_referente
                $('#articoli').load(globals.rootdir + '/modules/interventi/ajax_articoli.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>');

                $('#costi').load(globals.rootdir + '/modules/interventi/ajax_costi.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>');
            }
        });
    });
</script>
