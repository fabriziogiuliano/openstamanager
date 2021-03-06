<?php

include_once __DIR__.'/../../core.php';

switch (post('op')) {
    case 'update':
        // Calcolo il totale da pagare
        $rs = $dbo->fetchArray('SELECT SUM(da_pagare) AS totale_da_pagare FROM co_scadenziario GROUP BY iddocumento HAVING iddocumento=(SELECT iddocumento FROM co_scadenziario s WHERE id='.prepare($id_record).')');
        $totale_da_pagare = $rs[0]['totale_da_pagare'];

        $totale_utente = 0;

        // Verifico se il totale sommato è uguale al totale da pagare
        foreach ($post['scadenza'] as $idscadenza => $da_pagare) {
            $totale_utente += $da_pagare;
        }

        if ($totale_utente == $totale_da_pagare) {
            foreach ($post['scadenza'] as $idscadenza => $da_pagare) {
                $dbo->query('UPDATE co_scadenziario SET da_pagare='.prepare($da_pagare).', pagato='.prepare(post('pagato')[$idscadenza]).', scadenza='.prepare(post('data')[$idscadenza]).' WHERE id='.prepare($idscadenza));
            }

            $_SESSION['infos'][] = _('Scadenze aggiornate!');
        } else {
            $_SESSION['errors'][] = _('Il totale degli importi inseriti non corrisponde al totale da pagare!');
        }

        break;
}
