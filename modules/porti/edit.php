<?php
include_once __DIR__.'/../../core.php';

?><form action="" method="post" role="form">
	<input type="hidden" name="backto" value="record-edit">
	<input type="hidden" name="op" value="update">

	<div class="pull-right">
		<button type="submit" class="btn btn-success"><i class="fa fa-check"></i> <?php echo _('Salva modifiche'); ?></button>
	</div>
	<div class="clearfix"></div><br>

	<!-- DATI -->
	<div class="panel panel-primary">
		<div class="panel-heading">
			<h3 class="panel-title"><?php echo _('Dati') ?></h3>
		</div>

		<div class="panel-body">
			<div class="row">
				<div class="col-xs-12 col-md-12">
					{[ "type": "text", "label": "<?php echo _('Descrizione') ?>", "name": "descrizione", "required": 1,  "value": "$descrizione$" ]}
				</div>
			</div>
		</div>
	</div>

</form>

<?php
$documenti = $dbo->fetchArray('SELECT id FROM dt_ddt WHERE idporto='.prepare($id_record).'
UNION SELECT id FROM co_documenti WHERE idporto='.prepare($id_record).'
UNION SELECT id FROM co_preventivi WHERE idporto='.prepare($id_record));

echo '
<div class="alert alert-danger" style="margin:0px;">
    '.str_replace('_NUM_', count($documenti), _('Ci sono _NUM_ documenti collegati')).'.
</div>';

?>

<a class="btn btn-danger ask" data-backto="record-list">
    <i class="fa fa-trash"></i> <?php echo _('Elimina'); ?>
</a>
