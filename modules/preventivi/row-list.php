<?php

include_once __DIR__.'/../../core.php';

/*
ARTICOLI + RIGHE GENERICHE
*/
$q_art = "SELECT *, IFNULL((SELECT codice FROM mg_articoli WHERE id=idarticolo),'') AS codice, IFNULL((SELECT descrizione FROM co_iva WHERE id=idiva),'') AS desc_iva  FROM co_righe_preventivi WHERE idpreventivo=".prepare($id_record).' ORDER BY `order`';
$rs = $dbo->fetchArray($q_art);

echo '
<table class="table table-striped table-hover table-condensed table-bordered">
    <tr>
        <th>'._('Descrizione').'</th>
        <th width="120">'._('Q.tà').'</th>
        <th width="80">'._('U.m.').'</th>
        <th width="120">'._('Costo unitario').'</th>
        <th width="120">'._('Iva').'</th>
        <th width="120">'._('Imponibile').'</th>
        <th width="60"></th>
    </tr>
    <tbody class="sortable">';

// se ho almeno un articolo caricato mostro la riga
if (!empty($rs)) {
    foreach ($rs as $r) {
        echo '
        <tr data-id="'.$r['id'].'">
            <td>';
        if (!empty($r['idarticolo'])) {
            echo Modules::link('Articoli', $r['idarticolo'], $r['codice'].' - '.$r['descrizione']);
        } else {
            echo nl2br($r['descrizione']);
        }

        echo '
            </td>';

        // q.tà
        echo '
            <td class="text-center">
                '.Translator::numberToLocale($r['qta'] - $r['qta_evasa']).'
            </td>';

        // um
        echo '
            <td class="text-center">
                '.$r['um'].'
            </td>';

        // costo unitario
        echo '
            <td class="text-right">
                '.Translator::numberToLocale($r['subtotale'] / $r['qta']).' &euro;';

        if ($r['sconto_unitario'] > 0) {
            echo '
            <br><small class="label label-danger">- sconto '.Translator::numberToLocale($r['sconto_unitario']).($r['tipo_sconto'] == 'PRC' ? '%' : ' &euro;').'</small>';
        }

        echo '
            </td>';

        // iva
        echo '
            <td class="text-right">
                '.Translator::numberToLocale($r['iva']).' &euro;
                <br><small class="help-block">'.$r['desc_iva'].'</small>
            </td>';

        // Imponibile
        echo '
            <td class="text-right">
                '.Translator::numberToLocale($r['subtotale'] - $r['sconto']).' &euro;
            </td>';

        // Possibilità di rimuovere una riga solo se il preventivo non è stato pagato
        echo '
            <td class="text-center">';

        if ($records[0]['stato'] != 'Pagato' && strpos($r['descrizione'], 'SCONTO') === false) {
            echo "
                <form action='".$rootdir.'/editor.php?id_module='.$id_module.'&id_record='.$id_record."' method='post' id='delete-form-".$r['id']."' role='form'>
                    <input type='hidden' name='backto' value='record-edit'>
                    <input type='hidden' name='op' value='unlink_articolo'>
                    <input type='hidden' name='idriga' value='".$r['id']."'>
                    <input type='hidden' name='idarticolo' value='".$r['idarticolo']."'>

                    <div class='btn-group'>
                        <a class='btn btn-xs btn-warning' title='Modifica riga' onclick=\"launch_modal( 'Modifica riga', '".$rootdir.'/modules/preventivi/edit_riga.php?id_module='.$id_module.'&id_record='.$id_record.'&idriga='.$r['id']."', 1 );\"><i class='fa fa-edit'></i></a>

                        <a href='javascript:;' class='btn btn-xs btn-danger' title='Rimuovi questa riga' onclick=\"if( confirm('Rimuovere questa riga dal preventivo?') ){ $('#delete-form-".$r['id']."').submit(); }\"><i class='fa fa-trash-o'></i></a>
                    </div>
                </form>";
        }

        if (strpos($r['descrizione'], 'SCONTO') === false) {
            echo '
                <div class="handle clickable" style="padding:10px">
                    <i class="fa fa-sort"></i>
                </div>';
        }
        echo '
            </td>
        </tr>';
    }
}

// Calcoli
$imponibile = sum(array_column($rs, 'subtotale'));
$sconto = sum(array_column($rs, 'sconto'));
$iva = sum(array_column($rs, 'iva'));

$imponibile_scontato = sum($imponibile, -$sconto);

$totale = sum([
    $imponibile_scontato,
    $iva,
]);

echo '
    </tbody>';

// SCONTO
if (abs($sconto) > 0) {
    echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('Imponibile')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($imponibile).' &euro;
        </td>
        <td></td>
    </tr>';

    echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('Sconto')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($sconto).' &euro;
        </td>
        <td></td>
    </tr>';

    // Totale imponibile
    echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('Imponibile scontato')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($imponibile_scontato).' &euro;
        </td>
        <td></td>
    </tr>';
} else {
    // Totale imponibile
    echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('Imponibile')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($imponibile).' &euro;
        </td>
        <td></td>
    </tr>';
}

// Totale iva
echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('IVA')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($iva).' &euro;
        </td>
        <td></td>
    </tr>';

// Totale preventivo
echo '
    <tr>
        <td colspan="5" class="text-right">
            <b>'.strtoupper(_('Totale')).':</b>
        </td>
        <td align="right">
            '.Translator::numberToLocale($totale).' &euro;
        </td>
        <td></td>
    </tr>';

echo '
</table>';

echo '
<script>
$(document).ready(function(){
	$(".sortable").each(function() {
        $(this).sortable({
            axis: "y",
            handle: ".handle",
			cursor: "move",
			dropOnEmpty: true,
			scroll: true,
			start: function(event, ui) {
				ui.item.data("start", ui.item.index());
			},
			update: function(event, ui) {
				$.post("'.$rootdir.'/actions.php", {
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
