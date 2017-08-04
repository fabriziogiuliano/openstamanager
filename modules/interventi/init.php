<?php

include_once __DIR__.'/../../core.php';

if (isset($id_record)) {
    $records = $dbo->fetchArray('SELECT *, (SELECT colore FROM in_statiintervento WHERE idstatointervento=in_interventi.idstatointervento) AS colore, (SELECT idpreventivo FROM co_preventivi_interventi WHERE idintervento=in_interventi.id LIMIT 0,1) AS idpreventivo FROM in_interventi WHERE id='.prepare($id_record).Modules::getAdditionalsQuery($id_module));
}

$jscript_modules[] = $rootdir.'/modules/interventi/js/interventi_helperjs.js';