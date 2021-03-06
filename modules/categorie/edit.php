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
				<div class="col-xs-12 col-md-8">
					{[ "type": "text", "label": "<?php echo _('Nome') ?>", "name": "nome", "required": 1, "value": "$nome$" ]}
				</div>

				<div class="col-xs-12 col-md-4">
					{[ "type": "text", "label": "<?php echo _('Colore'); ?>", "name": "colore", "class": "colorpicker text-center", "value": "$colore$", "extra": "maxlength='7'", "icon-after": "<div class='img-circle square'></div>" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-12">
					{[ "type": "textarea", "label": "<?php echo _('Nota') ?>", "name": "nota", "value": "$nota$" ]}
				</div>
			</div>
		</div>
	</div>

</form>

<div class="panel panel-primary">
	<div class="panel-heading">
		<h3 class="panel-title"><?php echo _('Sottocategorie'); ?></h3>
	</div>

	<div class="panel-body">
		<div class="pull-left">
			<a class="btn btn-primary" data-href="<?php echo $rootdir?>/add.php?id_module=<?php echo $id_module; ?>&id_original=<?php echo $id_record ?>" data-toggle="modal" data-title="<?php echo _('Aggiungi riga'); ?>" data-target="#bs-popup"><i class="fa fa-plus"></i> <?php echo _('Sottocategoria'); ?></a><br>
		</div>
		<div class="clearfix"></div>
		<hr>

		<div class="row">
			<div class="col-md-12">
				<table class="table table-striped table-hover table-condensed">
				<tr>
					<th><?php echo _('Nome'); ?></th>
					<th><?php echo _('Colore'); ?></th>
					<th><?php echo _('Nota'); ?></th>
					<th><?php echo _('Opzioni'); ?></th>
				</tr>

				<?php include $docroot.'/modules/'.Modules::getModule($id_module)['directory'].'/row-list.php'; ?>
				</table>
			</div>
		</div>
	</div>
</div>

<script>
	$(document).ready( function(){
		$('.colorpicker').colorpicker().on('changeColor', function(){
			$('#colore').parent().find('.square').css( 'background', $('#colore').val() );
		});

		$('#colore').parent().find('.square').css( 'background', $('#colore').val() );
	});
</script>

<a class="btn btn-danger ask" data-backto="record-list">
    <i class="fa fa-trash"></i> <?php echo _('Elimina'); ?>
</a>
