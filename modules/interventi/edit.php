<?php

include_once __DIR__.'/../../core.php';

unset($_SESSION['superselect']['idanagrafica']);
$_SESSION['superselect']['idanagrafica'] = $records[0]['idanagrafica'];

if ($records[0]['firma_file'] == '') {
    $frase = _('Anteprima e firma');
    $info_firma = '';
} else {
    $frase = _('Nuova anteprima e firma');
    $info_firma = '<span class="label label-success"><i class="fa fa-edit"></i> '.str_replace(['_TIMESTAMP_', '_PERSON_'], ['<b>'.date('d/m/Y \\a\\l\\l\\e H:i', strtotime($records[0]['firma_data'])).'</b>', '<b>'.$records[0]['firma_nome'].'</b>'], _('Firmato il _TIMESTAMP_ da _PERSON_')).'</span>';
}

?><form action="" method="post">
	<input type="hidden" name="op" value="update">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="id_record" value="<?php echo $id_record ?>">

	<!-- DATI CLIENTE -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _('Dati cliente') ?></h3>
		</div>

		<div class="panel-body">
            <!-- EVENTUALE FIRMA GIA' EFFETTUATA -->
            <?php echo $info_firma ?>
			<div class="pull-right">
				<button type="button" class="btn btn-primary " onclick="launch_modal( '<?php echo _('Anteprima e firma') ?>', '<?php echo $rootdir ?>/modules/interventi/add_firma.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>&anteprima=1', 1 );"><i class="fa fa-desktop"></i> <?php echo $frase ?>...</button>

				<a class="btn btn-info" target="_blank" href="<?php echo $rootdir ?>/pdfgen.php?ptype=interventi&idintervento=<?php echo $id_record ?>"><i class="fa fa-print"></i> <?php echo _('Stampa intervento') ?></a>
				<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> <?php echo _('Salva modifiche'); ?></button>
				<br>
			</div>
			<div class="clearfix"></div>

			<!-- RIGA 1 -->
			<div class="row">
				<div class="col-md-3">
					<?php
                        echo Modules::link('Anagrafiche', $records[0]['idanagrafica'], null, null, 'class="pull-right"');
                    ?>
					{[ "type": "select", "label": "<?php echo _('Cliente'); ?>", "name": "idanagrafica", "required": 1, "values": "query=SELECT an_anagrafiche.idanagrafica AS id, ragione_sociale AS descrizione FROM an_anagrafiche INNER JOIN (an_tipianagrafiche_anagrafiche INNER JOIN an_tipianagrafiche ON an_tipianagrafiche_anagrafiche.idtipoanagrafica=an_tipianagrafiche.idtipoanagrafica) ON an_anagrafiche.idanagrafica=an_tipianagrafiche_anagrafiche.idanagrafica WHERE descrizione='Cliente' AND deleted=0 ORDER BY ragione_sociale", "value": "$idanagrafica$", "ajax-source": "clienti" ]}
				</div>

				<div class="col-md-3">
					{[ "type": "select", "label": "<?php echo _('Sede'); ?>", "name": "idsede", "values": "query=SELECT 0 AS id, 'Sede legale' AS descrizione UNION SELECT id, CONCAT_WS( ' - ', nomesede, citta ) AS descrizione FROM an_sedi WHERE idanagrafica='$idanagrafica$'", "value": "$idsede$", "ajax-source": "sedi" ]}
				</div>

				<div class="col-md-3">
					{[ "type": "select", "label": "<?php echo _('Per conto di'); ?>", "name": "idclientefinale", "value": "$idclientefinale$", "ajax-source": "clienti" ]}
				</div>

				<div class="col-md-3">
					{[ "type": "select", "label": "<?php echo _('Referente'); ?>", "name": "idreferente", "value": "$idreferente$", "ajax-source": "referenti" ]}
				</div>
			</div>



			<!-- RIGA 2 -->
			<div class="row">
				<div class="col-md-3">
					<?php
                    if (($records[0]['idpreventivo'] != '')) {
                        echo '
                        '.Modules::link('Preventivi', $records[0]['idpreventivo'], null, null, 'class="pull-right"');
                    }
                    ?>

					{[ "type": "select", "label": "<?php echo _('Preventivo'); ?>", "name": "idpreventivo", "value": "$idpreventivo$", "ajax-source": "preventivi" ]}
				</div>

				<div class="col-md-3">
					<?php
                        $rs = $dbo->fetchArray('SELECT id, idcontratto FROM co_righe_contratti WHERE idintervento='.prepare($id_record));
                        if (count($rs) == 1) {
                            $idcontratto = $rs[0]['idcontratto'];
                            $idcontratto_riga = $rs[0]['id'];
                        } else {
                            $idcontratto = '';
                            $idcontratto_riga = '';
                        }

                        if (($idcontratto != '')) {
                            echo '
                            '.Modules::link('Contratti', $idcontratto, null, null, 'class="pull-right"');
                        }
                    ?>

					{[ "type": "select", "label": "<?php echo _('Contratto'); ?>", "name": "idcontratto", "value": "<?php echo $idcontratto; ?>", "ajax-source": "contratti" ]}
					<input type='hidden' name='idcontratto_riga' value='<?php echo $idcontratto_riga ?>'>
				</div>
			</div>
		</div>
	</div>



	<!-- DATI INTERVENTO -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _('Dati intervento') ?></h3>
		</div>

		<div class="panel-body">
			<div class="pull-right">
				<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> <?php echo _('Salva modifiche'); ?></button>
			</div>
			<div class="clearfix"></div>


			<!-- RIGA 3 -->
			<div class="row">
				<div class="col-md-3">
					{[ "type": "span", "label": "<?php echo _('Codice'); ?>", "name": "codice", "value": "$codice$" ]}
				</div>

				<div class="col-md-3">
					{[ "type": "date", "label": "<?php echo _('Data richiesta'); ?>", "name": "data_richiesta", "required": 1, "value": "$data_richiesta$" ]}
				</div>
			</div>


			<!-- RIGA 4 -->
			<div class="row">
				<div class="col-md-4">
					{[ "type": "select", "label": "<?php echo _('Tipo attività'); ?>", "name": "idtipointervento", "required": 1, "values": "query=SELECT idtipointervento AS id, descrizione FROM in_tipiintervento", "value": "$idtipointervento$" ]}
				</div>

				<div class="col-md-4">
					{[ "type": "select", "label": "<?php echo _('Stato'); ?>", "name": "idstatointervento", "required": 1, "values": "query=SELECT idstatointervento AS id, descrizione, colore AS _bgcolor_ FROM in_statiintervento", "value": "$idstatointervento$" ]}
				</div>

				<div class="col-md-4">
					{[ "type": "select", "label": "<?php echo _('Automezzo'); ?>", "name": "idautomezzo", "values": "query=SELECT id, CONCAT_WS( ')', CONCAT_WS( ' (', CONCAT_WS( ', ', nome, descrizione), targa ), '' ) AS descrizione FROM dt_automezzi", "value": "$idautomezzo$" ]}
				</div>
			</div>


			<!-- RIGA 5 -->
			<div class="row">
				<div class="col-md-12">
					{[ "type": "textarea", "label": "<?php echo _('Richiesta'); ?>", "name": "richiesta", "required": 1, "class": "autosize", "value": "$richiesta$", "extra": "rows='5'" ]}
				</div>

				<div class="col-md-12">
					{[ "type": "textarea", "label": "<?php echo _('Descrizione'); ?>", "name": "descrizione", "class": "autosize", "value": "$descrizione$", "extra": "rows='10'" ]}
				</div>

				<div class="col-md-12">
					{[ "type": "textarea", "label": "<?php echo _('Note interne'); ?>", "name": "informazioniaggiuntive", "class": "autosize", "value": "$informazioniaggiuntive$", "extra": "rows='5'" ]}
				</div>
			</div>
		</div>
	</div>

	<!-- ORE LAVORO -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _('Ore di lavoro') ?></h3>
		</div>

		<div class="panel-body">
			<div class="pull-right">
				<a class='btn btn-default' onclick="$('.extra').removeClass('hide'); $(this).addClass('hide'); $('#dontshowall_dettagli').removeClass('hide');" id='showall_dettagli'><i class='fa fa-square-o'></i> <?php echo _('Mostra dettagli costi') ?></a>
				<a class='btn btn-info hide' onclick="$('.extra').addClass('hide'); $(this).addClass('hide'); $('#showall_dettagli').removeClass('hide');" id='dontshowall_dettagli'><i class='fa fa-check-square-o'></i> <?php echo _('Mostra dettagli costi') ?></a>
				<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> <?php echo _('Salva modifiche'); ?></button>
			</div>
			<div class="clearfix"></div>
			<br>

			<div class="row">
				<div class="col-md-12" id="tecnici">
					<script>$('#tecnici').load('<?php echo $rootdir ?>/modules/interventi/ajax_tecnici.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>');</script>
				</div>
			</div>
		</div>
	</div>


    <!-- ARTICOLI -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title"><?php echo _('Materiale utilizzato') ?></h3>
        </div>

        <div class="panel-body">
            <div id="articoli">
                <?php include $docroot.'/modules/interventi/ajax_articoli.php'; ?>
            </div>

            <?php if ($records[0]['stato'] != 'Fatturato' && $records[0]['stato'] != 'Completato') {
        ?>
                <button type="button" class="btn btn-primary" onclick="launch_modal( '<?php echo _('Aggiungi articolo') ?>', '<?php echo $rootdir ?>/modules/interventi/add_articolo.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>&idriga=0&idautomezzo='+$('#idautomezzo').find(':selected').val(), 1);"><i class="fa fa-plus"></i> <?php echo _('Aggiungi articolo') ?>...</button>
            <?php

    } ?>
        </div>
    </div>

    <!-- SPESE AGGIUNTIVE -->
    <div class="panel panel-primary">
        <div class="panel-heading">
            <h3 class="panel-title"><?php echo _('Altre spese') ?></h3>
        </div>

        <div class="panel-body">
            <div id="righe">
                <?php include $docroot.'/modules/interventi/ajax_righe.php'; ?>
            </div>

            <?php if ($records[0]['stato'] != 'Fatturato' && $records[0]['stato'] != 'Completato') {
        ?>
                <button type="button" class="btn btn-primary" onclick="launch_modal( '<?php echo _('Aggiungi altre spese') ?>', '<?php echo $rootdir ?>/modules/interventi/add_righe.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>', 1 );"><i class="fa fa-plus"></i> <?php echo _('Aggiungi altre spese') ?>...</button>
            <?php

    } ?>
        </div>
    </div>

    <!-- COSTI TOTALI -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _('Costi totali') ?></h3>
		</div>

		<div class="panel-body">
			<div class="pull-right">
				<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> <?php echo _('Salva modifiche'); ?></button>
			</div>
			<div class="clearfix"></div>
			<br>

			<div class="row">
				<div class="col-md-12" id="costi">
					<script>$('#costi').load('<?php echo $rootdir ?>/modules/interventi/ajax_costi.php?id_module=<?php echo $id_module ?>&id_record=<?php echo $id_record ?>');</script>
				</div>
			</div>
		</div>
	</div>
</form>

{( "name": "filelist_and_upload", "id_module": "<?php echo $id_module ?>", "id_record": "<?php echo $id_record ?>" )}

<!-- EVENTUALE FIRMA GIA' EFFETTUATA -->
<div class="text-center">
    <?php
    if ($records[0]['firma_file'] == '') {
        echo '
    <p class="alert alert-warning"><i class="fa fa-warning"></i> '._('Questo intervento non è ancora stato firmato dal cliente').'.</p>';
    } else {
        echo '
    <img src="'.$rootdir.'/files/interventi/'.$records[0]['firma_file'].'" class="img-thumbnail"><br>
    <div class="alert alert-success"><i class="fa fa-check"></i> '.str_replace(['_TIMESTAMP_', '_PERSON_'], ['<b>'.date('d/m/Y \\a\\l\\l\\e H:i', strtotime($records[0]['firma_data'])).'</b>', '<b>'.$records[0]['firma_nome'].'</b>'], _('Firmato il _TIMESTAMP_ da _PERSON_')).'</div>';
    }
    ?>
</div>

<script>
	$('#idanagrafica').change( function(){
		session_set('superselect,idanagrafica', $(this).val(), 0);

		$("#idsede").selectReset();
		$("#idpreventivo").selectReset();
		$("#idcontratto").selectReset();
	});

	$('#idpreventivo').change( function(){
		if($('#idcontratto').val() && $(this).val()){
			$('#idcontratto').val('').trigger('change');
		}
	});

	$('#idcontratto').change( function(){
		if($('#idpreventivo').val() && $(this).val()){
			$('#idpreventivo').val('').trigger('change');
			$('input[name=idcontratto_riga]').val('');
		}
	});

	$('#matricola').change( function(){
		session_set('superselect,marticola', $(this).val(), 0);
	});
</script>

<a class="btn btn-danger ask" data-backto="record-list">
    <i class="fa fa-trash"></i> <?php echo _('Elimina') ?>
</a>

<script src="<?php echo $rootdir ?>/modules/interventi/js/interventi_helperjs.js"></script>
