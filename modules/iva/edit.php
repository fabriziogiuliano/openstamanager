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
					{[ "type": "text", "label": "<?php echo _('Descrizione') ?>", "name": "descrizione", "required": 1, "value": "$descrizione$" ]}
				</div>
			</div>

			<div class="row">
				<div class="col-xs-12 col-md-6">
					{[ "type": "number", "label": "<?php echo _('Percentuale') ?>", "name": "percentuale", "value": "$percentuale$", "icon-after": "<i class=\"fa fa-percent\"></i>" ]}
				</div>

				<div class="col-xs-12 col-md-6">
					{[ "type": "number", "label": "<?php echo _('Indetraibile') ?>", "name": "indetraibile", "value": "$indetraibile$", "icon-after": "<i class=\"fa fa-percent\"></i>" ]}
				</div>
			</div>

            <div class="row">
				<div class="col-xs-12 col-md-12">
					{[ "type": "textarea", "label": "<?php echo _('Dicitura fissa in fattura') ?>", "name": "dicitura", "value": "$dicitura$" ]}
				</div>
			</div>
		</div>
	</div>

</form>

<a class="btn btn-danger ask" data-backto="record-list">
    <i class="fa fa-trash"></i> <?php echo _('Elimina'); ?>
</a>
