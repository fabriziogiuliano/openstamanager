<?php

include_once __DIR__.'/../../core.php';

$idriga = get('idriga');

// Info preventivo
$q = 'SELECT numero, idanagrafica FROM co_preventivi WHERE id='.prepare($id_record);
$rs = $dbo->fetchArray($q);
$numero = $rs[0]['numero'];
$idanagrafica = $rs[0]['idanagrafica'];

if (empty($idriga)) {
    $op = 'addriga';
    $button = _('Aggiungi');

    // valori default
    $idarticolo = '';
    $descrizione = '';
    $qta = 1;
    $um = '';
    $idiva = get_var('Iva predefinita');
    $subtot = 0;
    $sconto = 0;
} else {
    $op = 'editriga';
    $button = _('Modifica');

    // carico record da modificare
    $q = 'SELECT * FROM co_righe_preventivi WHERE idpreventivo='.prepare($id_record).' AND id='.prepare($idriga);
    $rsr = $dbo->fetchArray($q);

    $idarticolo = !empty($rsr[0]['idarticolo']) ? $rsr[0]['idarticolo'] : '';
    $descrizione = $rsr[0]['descrizione'];
    $qta = $rsr[0]['qta'];
    $um = $rsr[0]['um'];
    $idiva = $rsr[0]['idiva'];
    $subtot = $rsr[0]['subtotale'] / $rsr[0]['qta'];
    $sconto = $rsr[0]['sconto_unitario'];
    $tipo_sconto = $rsr[0]['tipo_sconto'];

    $prc_guadagno = $rsr[0]['prc_guadagno'];
    if ($prc_guadagno > 0) {
        $prc_guadagno = '+'.$prc_guadagno;
    }
}

/*
    Form add / edit
*/
echo '
<p>'.str_replace('_NUM_', $numero, _('Preventivo numero _NUM_')).'</p>
<form id="form" action="'.$rootdir.'/editor.php?id_module='.$id_module.'&id_record='.$id_record.'" method="post">
    <input type="hidden" name="op" value="'.$op.'">
    <input type="hidden" name="idriga" value="'.$idriga.'">
    <input type="hidden" name="backto" value="record-edit">';

// Elenco articoli raggruppati per gruppi e sottogruppi
echo '
    <div class="row">
        <div class="col-md-12">
            {[ "type": "select", "label": "'._('Articolo').'", "name": "idarticolo", "value": "'.$idarticolo.'", "ajax-source": "articoli", "extra": "onchange=\"session_set(\'superselect,idarticolo\', $(this).val(), 0); $data = $(this).selectData(); $(\'#prezzo\').val($data.prezzo_vendita); $(\'#desc\').val($data.descrizione); $(\'#um\').selectSetNew($data.um, $data.um);\"" ]}
        </div>
    </div>';

// Descrizione
echo '
    <div class="row">
        <div class="col-md-12">
            {[ "type": "textarea", "label": "'._('Descrizione').'", "name": "descrizione", "id": "desc", "value": "'.$descrizione.'", "required": 1 ]}
        </div>
    </div>';

// Quantità
echo '
    <div class="row">
        <div class="col-md-3">
            {[ "type": "number", "label": "'._('Q.tà').'", "name": "qta", "value": "'.$qta.'", "required": 1, "decimals": "qta" ]}
        </div>';

// Unità di misura
echo '
        <div class="col-md-3">
            {[ "type": "select", "label": "'._('Unità di misura').'", "icon-after": "add|'.Modules::getModule('Unità di misura')['id'].'", "name": "um", "value": "'.$um.'", "ajax-source": "misure" ]}
        </div>';

// Sconto
echo '
        <div class="col-md-6">
            {[ "type": "number", "label": "'._('Sconto/rincaro articoli per questo cliente').'", "icon-after": "%", "name": "prc_guadagno", "value": "'.$prc_guadagno.'" ]}
        </div>
    </div>';

/*
if (get_var('Percentuale rivalsa INPS') != '' || get_var("Percentuale ritenuta d'acconto") != '') {
    echo '
    <div class="row">';

    // Rivalsa INPS
    if (get_var('Percentuale rivalsa INPS') != '') {
        echo '
        <div class="col-md-6">
            {[ "type": "select", "label": "'._('Rivalsa INPS').'", "name": "idrivalsainps", "required": 1, "value": "'.get_var('Percentuale rivalsa INPS').'", "values": "query=SELECT * FROM co_rivalsainps" ]}
        </div>';
    }

    // Ritenuta d'acconto
    if (get_var("Percentuale ritenuta d'acconto") != '') {
        echo '
        <div class="col-md-6">
            {[ "type": "select", "label": "'._("Ritenuta d'acconto").'", "name": "idritenutaacconto", "required": 1, "value": "'.get_var("Percentuale ritenuta d'acconto").'", "values": "query=SELECT * FROM co_ritenutaacconto" ]}
        </div>';
    }

    echo '
    </div>';
}

*/

// Iva
echo '
    <div class="row">
        <div class="col-md-6">
            {[ "type": "select", "label": "'._('Iva').'", "name": "idiva", "required": 1, "value": "'.$idiva.'", "values": "query=SELECT * FROM co_iva ORDER BY descrizione ASC" ]}
        </div>';

// Costo unitario
echo '
        <div class="col-md-3">
            {[ "type": "number", "label": "'._('Costo unitario').'", "name": "prezzo", "required": 1, "value": "'.$subtot.'", "icon-after": "&euro;" ]}
        </div>';

// Sconto unitario
echo '
        <div class="col-md-3">
            {[ "type": "number", "label": "'._('Sconto unitario').'", "name": "sconto", "value": "'.$sconto.'", "icon-after": "choice|untprc|'.$tipo_sconto.'" ]}
        </div>
    </div>';

echo '

    <!-- PULSANTI -->
	<div class="row">
		<div class="col-md-12 text-right">
			<button type="submit" class="btn btn-primary pull-right"><i class="fa fa-plus"></i> '.$button.'</button>
		</div>
    </div>
</form>';

echo '
	<script src="'.$rootdir.'/lib/init.js"></script>';
