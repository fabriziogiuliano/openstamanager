<?php

include_once __DIR__.'/core.php';

$pageTitle = 'Bug';
$jscript_modules[] = $js.'/ckeditor.js';

if (filter('op') == 'send') {
    $dati = $post;

    // Parametri e-mail
    $replace = [
        'Server SMTP' => 'email_host',
        'Username SMTP' => 'email_username',
        'Porta SMTP' => 'email_porta',
        'Sicurezza SMTP' => 'email_secure',
        'Password SMTP' => 'email_password',
    ];
    $rs = $dbo->fetchArray("SELECT * FROM zz_settings WHERE sezione = 'Email'");
    foreach ($rs as $r) {
        if (!empty($replace[$r['nome']])) {
            $dati[$replace[$r['nome']]] = $r['valore'];
        }
    }

    // Preparazione email
    $mail = new PHPMailer();

    // Se non specificato l'host uso le impostazioni di invio mail di default del server
    if (!empty($dati['email_host'])) {
        $mail->IsSMTP();
        $mail->IsHTML();
        $mail->SMTPDebug = 2;

        $mail->Host = $dati['email_host'];
        $mail->Port = $dati['email_porta'];

        // Controllo se è necessaria l'autenticazione per il server di posta
        if (!empty($dati['email_username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $dati['email_username'];
            $mail->Password = $dati['email_password'];
        }

        if (in_array(strtolower($dati['email_secure']), ['ssl', 'tls'])) {
            $mail->SMTPSecure = strtolower($dati['email_secure']);
        }
    }

    $mail->WordWrap = 50;

    // Mittente
    $mail->From = $dati['email_from'];
    $mail->FromName = $_SESSION['username'];
    $mail->AddReplyTo($dati['email_from']);

    // Destinatario
    $mail->AddAddress($dati['email_to']);

    // Copia
    if (!empty($dati['email_cc'])) {
        $mail->AddCC($dati['email_cc']);
    }

    // Copia nascosta
    if (!empty($dati['email_bcc'])) {
        $mail->AddBCC($dati['email_bcc']);
    }

    $mail->Subject = 'Segnalazione bug OSM '.$version.' ('.(!empty($revision) ? 'R'.$revision : _('In sviluppo')).')';
    $mail->AltBody = _('Questa email arriva dal modulo bug di segnalazione bug di OSM');
    $body = $dati['body'].'<hr><br>'._('IP').': '.get_client_ip()."<br>\n";

    // Se ho scelto di inoltrare i file di log, allego
    if (!empty($post['log']) && file_exists($docroot.'/logs/error.log')) {
        $mail->AddAttachment($docroot.'/logs/error.log');
    }

    // Se ho scelto di inoltrare copia del db
    if (!empty($post['sql'])) {
        $dump = "SET foreign_key_checks = 0;\n";
        $dump .= backup_tables();
        $dump .= "SET foreign_key_checks = 1;\n";

        $backup_file = 'Backup OSM '.date('Y-m-d').' '.date('H_i_s').'.sql';

        if (file_put_contents($docroot.'/'.$backup_file, $dump)) {
            $mail->AddAttachment($docroot.'/'.$backup_file);
            $_SESSION['infos'][] = _('Backup del database eseguito ed allegato correttamente!');
        } else {
            $_SESSION['errors'][] = _('Errore durante la creazione del file di backup!');
        }
    }

    // Se ho scelto di inoltrare le INFO del mio sistema
    if (!empty($post['info'])) {
        $body .= $_SERVER['HTTP_USER_AGENT'].' - '.getOS();
    }

    $mail->Body = $body;

    // Invio mail
    if (!$mail->send()) {
        $_SESSION['errors'][] = _("Errore durante l'invio della segnalazione").': '.$mail->ErrorInfo;
    } else {
        $_SESSION['infos'][] = _('Email inviata correttamente!');
    }

    $mail->SmtpClose();

    if (!empty($post['sql'])) {
        unlink($docroot.'/'.$backup_file);
    }

    redirect($rootdir.'/bug.php');
    exit();
}

if (file_exists($docroot.'/include/custom/top.php')) {
    include $docroot.'/include/custom/top.php';
} else {
    include $docroot.'/include/top.php';
}

$email_to = '';
$email_from = '';
$rs = $dbo->fetchArray("SELECT * FROM zz_settings WHERE sezione = 'Email'");
foreach ($rs as $r) {
    if (($r['nome'] == 'Server SMTP' || $r['nome'] == 'Indirizzo per le email in uscita' || $r['nome'] == 'Destinatario') && $r['valore'] == '') {
        $alert = true;
    }

    if ($r['nome'] == 'Destinatario') {
        $email_to = $r['valore'];
    } elseif ($r['nome'] == 'Indirizzo per le email in uscita') {
        $email_from = $r['valore'];
    }
}

if (!empty($alert)) {
    echo '
	<div class="alert alert-warning">
		<i class="fa fa-warning"></i>
		<b>'._('Attenzione!').'</b> '._('Per utilizzare correttamente il modulo di segnalazione bug devi configurare alcuni parametri email nella scheda impostazioni').'.
        '.Modules::link('Opzioni', $dbo->fetchArray("SELECT `idimpostazione` FROM `zz_settings` WHERE sezione='Email'")[0]['idimpostazione'], _('Correggi'), null, 'class="btn btn-warning pull-right"').'
		<div class="clearfix"></div>
	</div>';
}

echo '
	<div class="box">
		<div class="box-header">
			<h3 class="box-title"><i class="fa fa-bug"></i>'._('Segnalazione bug').'</h3></h3>
		</div>

		<div class="box-body">
			<form method="post" action="'.$rootdir.'/bug.php?op=send">
				<table class="table table-bordered table-condensed table-striped table-hover">
					<tr>
						<th width="150" class="text-right">'._('Da').':</th>
						<td>
                            {[ "type": "email", "placeholder": "'._('Mittente').'", "name": "email_from", "value": "'.$email_from.'", "required": 1 ]}
						</td>
					</tr>

					<!-- A -->
					<tr>
						<th class="text-right">'._('A').':</th>
						<td>
                            {[ "type": "email", "placeholder": "'._('Destinatario').'", "name": "email_to", "value": "'.$email_to.'", "required": 1 ]}
						</td>
					</tr>

					<!-- Cc -->
					<tr>
						<th class="text-right">'._('Cc').':</th>
						<td>
                            {[ "type": "email", "placeholder": "'._('Copia a').'...", "name": "email_cc" ]}
						</td>
					</tr>

					<!-- Bcc -->
					<tr>
						<th class="text-right">'._('Bcc').':</th>
						<td>
                            {[ "type": "email", "placeholder": "'._('Copia nascosta a').'...", "name": "email_bcc" ]}
						</td>
					</tr>

					<!-- Versione -->
					<tr>
						<th class="text-right">'._('Versione OSM').':</th>

						<td>
                            {[ "type": "span", "placeholder": "'._('Versione OSM').'", "value": "'.$version.' ('.(!empty($revision) ? 'R'.$revision : _('In sviluppo')).')" ]}
						</td>
					</tr>
				</table>

				<div class="row">
                    <div class="col-xs-12 col-md-4">
                        {[ "type": "checkbox", "placeholder": "'._('Allega file di log').'", "name": "log", "value": "1" ]}
					</div>

                    <div class="col-xs-12 col-md-4">
                        {[ "type": "checkbox", "placeholder": "'._('Allega copia del database').'", "name": "sql", "value": "0" ]}
					</div>

                    <div class="col-xs-12 col-md-4">
                        {[ "type": "checkbox", "placeholder": "'._('Allega informazioni sul PC').'", "name": "info", "value": "1" ]}
					</div>
				</div>

                <div class="clearfix"></div>
                <br>

                {[ "type": "textarea", "label": "'._('Descrizione del bug').'", "name": "body" ]}

                <!-- PULSANTI -->
                <div class="row">
                    <div class="col-md-12 text-right">
                        <button type="submit" class="btn btn-primary" id="send" disabled><i class="fa fa-envelope"></i> '._('Invia segnalazione').'</button>
                    </div>
                </div>
			</form>
		</div>
	</div>

	<script>
		$(document).ready(function(){
			var html = "<p>'._('Se hai riscontrato un bug ricordati di specificare').':</p>" +
			"<ul>" +
				"<li>'._('Modulo esatto (o pagina relativa) in cui questi si è verificato').';</li>" +
				"<li>'._('Dopo quali specifiche operazioni hai notato il malfunzionameto').'.</li>" +
			"</ul>" +
			"<p>'._('Assicurati inoltre di controllare che il checkbox relativo ai file di log sia contrassegnato, oppure riporta qui l\'errore visualizzato').'.</p>" +
			"<p>'._('Ti ringraziamo per il tuo contributo').',<br>" +
			"'._('Lo staff di OSM').'</p>";

			var firstFocus = 1;

			CKEDITOR.replace("body", {
				toolbar: [
					{ name: "document", items: [ "NewPage", "Preview", "-", "Templates" ] }, // Defines toolbar group with name (used to create voice label) and items in 3 subgroups
					["Bold","Italic","Underline","Superscript","-","NumberedList","BulletedList","Outdent","Indent","Blockquote","-","Format",], // Defines toolbar group without name
				]
			});

			CKEDITOR.instances.body.on("key", function() {
				setTimeout(function(){
					if(CKEDITOR.instances.body.getData() == ""){
						$("#send").prop("disabled", true);
					}
					else $("#send").prop("disabled", false);
				}, 10);
			});

			CKEDITOR.instances.body.setData( html, function() {});

			CKEDITOR.instances.body.on("focus", function() {
				if(firstFocus){
					CKEDITOR.instances.body.setData("", function() {
						CKEDITOR.instances.body.focus();
					});
					firstFocus = 0;
				}
			});
		});
	</script>';

if (file_exists($docroot.'/include/custom/bottom.php')) {
    include $docroot.'/include/custom/bottom.php';
} else {
    include $docroot.'/include/bottom.php';
}
