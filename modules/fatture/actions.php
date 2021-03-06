<?php

include_once __DIR__.'/../../core.php';

// Necessaria per la funzione add_movimento_magazzino
include_once $docroot.'/modules/articoli/modutil.php';

$module = Modules::getModule($id_module);

if ($module['name'] == 'Fatture di vendita') {
    $dir = 'entrata';
} else {
    $dir = 'uscita';
}

switch (post('op')) {
    case 'add':
        $idanagrafica = post('idanagrafica');
        $data = $post['data'];
        $dir = $post['dir'];
        $idtipodocumento = post('idtipodocumento');

        $numero = get_new_numerofattura($data);
        if ($dir == 'entrata') {
            $numero_esterno = get_new_numerosecondariofattura($data);
            $idconto = get_var('Conto predefinito fatture di vendita');
        } else {
            $numero_esterno = '';
            $idconto = get_var('Conto predefinito fatture di acquisto');
        }

        $campo = ($dir == 'entrata') ? 'idpagamento_vendite' : 'idpagamento_acquisti';

        // Tipo di pagamento predefinito dall'anagrafica
        $query = 'SELECT id FROM co_pagamenti WHERE id=(SELECT '.$campo.' AS pagamento FROM an_anagrafiche WHERE idanagrafica='.prepare($idanagrafica).')';
        $rs = $dbo->fetchArray($query);
        $idpagamento = $rs[0]['id'];

        // Se la fattura è di vendita e non è stato associato un pagamento predefinito al cliente leggo il pagamento dalle impostazioni
        if ($dir == 'entrata' && $idpagamento == '') {
            $idpagamento = get_var('Tipo di pagamento predefinito');
        }

        $query = 'INSERT INTO co_documenti (numero, numero_esterno, idanagrafica, idconto, idtipodocumento, idpagamento, data, idstatodocumento, idsede) VALUES ('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($idanagrafica).', '.prepare($idconto).', '.prepare($idtipodocumento).', '.prepare($idpagamento).', '.prepare($data).", (SELECT `id` FROM `co_statidocumento` WHERE `descrizione`='Bozza'), (SELECT idsede_fatturazione FROM an_anagrafiche WHERE idanagrafica=".prepare($idanagrafica).') )';
        $dbo->query($query);

        $id_record = $dbo->lastInsertedID();

        $_SESSION['infos'][] = str_replace('_NUM_', $numero, _('Aggiunta fattura numero _NUM_!'));

        break;

    case 'update':
        if (isset($post['id_record'])) {
            $numero_esterno = post('numero_esterno');
            $data = $post['data'];
            $idanagrafica = post('idanagrafica');
            $idagente = post('idagente');
            $note = post('note');
            $note_aggiuntive = post('note_aggiuntive');
            $idtipodocumento = post('idtipodocumento');
            $idstatodocumento = post('idstatodocumento');
            $idpagamento = post('idpagamento');
            $idcausalet = post('idcausalet');
            $idspedizione = post('idspedizione');
            $idporto = post('idporto');
            $idaspettobeni = post('idaspettobeni');
            $idvettore = post('idvettore');
            $n_colli = post('n_colli');
            $idsede = post('idsede');
            $idconto = post('idconto');
            $totale_imponibile = get_imponibile_fattura($id_record);
            $totale_fattura = get_totale_fattura($id_record);

            $tipo_sconto = $post['tipo_sconto_generico'];
            $sconto = $post['sconto_generico'];

            if ($dir == 'uscita') {
                $idrivalsainps = post('idrivalsainps');
                $idritenutaacconto = post('idritenutaacconto');
                $bollo = post('bollo');
            } else {
                $idrivalsainps = 0;
                $idritenutaacconto = 0;
                $bollo = 0;
            }

            // Leggo la descrizione del pagamento
            $query = 'SELECT descrizione FROM co_pagamenti WHERE id='.prepare($idpagamento);
            $rs = $dbo->fetchArray($query);
            $pagamento = $rs[0]['descrizione'];

            // Query di aggiornamento
            $query = 'UPDATE co_documenti SET '.
                ' data='.prepare($data).','.
                ' idstatodocumento='.prepare($idstatodocumento).','.
                ' idtipodocumento='.prepare($idtipodocumento).','.
                ' idanagrafica='.prepare($idanagrafica).','.
                ' idagente='.prepare($idagente).','.
                ' idpagamento='.prepare($idpagamento).','.
                ' idcausalet='.prepare($idcausalet).','.
                ' idspedizione='.prepare($idspedizione).','.
                ' idporto='.prepare($idporto).','.
                ' idaspettobeni='.prepare($idaspettobeni).','.
                ' idvettore='.prepare($idvettore).','.
                ' n_colli='.prepare($n_colli).','.
                ' idsede='.prepare($idsede).','.
                ' numero_esterno='.prepare($numero_esterno).','.
                ' tipo_sconto_globale='.prepare($tipo_sconto).','.
                ' sconto_globale='.prepare($sconto).','.
                ' note='.prepare($note).','.
                ' note_aggiuntive='.prepare($note_aggiuntive).','.
                ' idconto='.prepare($idconto).','.
                ' idrivalsainps='.prepare($idrivalsainps).','.
                ' idritenutaacconto='.prepare($idritenutaacconto).','.
                ' bollo=0, rivalsainps=0, ritenutaacconto=0, iva_rivalsainps=0 '.
                ' WHERE id='.prepare($id_record);

            $dbo->query($query);
            $query = 'SELECT descrizione FROM co_statidocumento WHERE id='.prepare($idstatodocumento);
            $rs = $dbo->fetchArray($query);

            $dbo->query("DELETE FROM co_righe_documenti WHERE descrizione LIKE '%SCONTO%' AND iddocumento=".prepare($id_record));

            // Sconto unitario, quello percentuale viene gestito a fondo pagina
            if ($tipo_sconto == 'UNT' && $sconto > 0) {
                $subtotale = -$sconto;

                // Calcolo anche l'iva da scontare
                $rsi = $dbo->fetchArray('SELECT descrizione, percentuale FROM co_iva WHERE id='.prepare(get_var('Iva predefinita')));
                $iva = $subtotale / 100 * $rsi[0]['percentuale'];

                $dbo->query('INSERT INTO co_righe_documenti(iddocumento, descrizione, idiva, desc_iva, iva, subtotale, sconto, qta, idgruppo, `order`) VALUES( '.prepare($id_record).", 'SCONTO', ".prepare($idiva).', '.prepare($rsi[0]['descrizione']).', '.prepare($iva).', '.prepare($subtotale).', 0, 1, (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))');
            }

            // Ricalcolo inps, ritenuta e bollo (se la fattura non è stata pagata)
            if ($dir == 'entrata') {
                ricalcola_costiagg_fattura($id_record);
            } else {
                ricalcola_costiagg_fattura($id_record, $idrivalsainps, $idritenutaacconto, $bollo);
            }

            // Elimino la scadenza e tutti i movimenti, poi se la fattura è emessa le ricalcolo
            if ($rs[0]['descrizione'] == 'Bozza') {
                elimina_scadenza($id_record);
                elimina_movimento($id_record, 0);
                elimina_movimento($id_record, 1);
            } elseif ($rs[0]['descrizione'] == 'Emessa') {
                elimina_scadenza($id_record);
                elimina_movimento($id_record, 0);
            }

            // Se la fattura è in stato "Emessa" posso inserirla in scadenziario e aprire il mastrino cliente
            if ($rs[0]['descrizione'] == 'Emessa') {
                aggiungi_scadenza($id_record, $pagamento);
                aggiungi_movimento($id_record, $dir);
            }

            $_SESSION['infos'][] = _('Fattura modificata correttamente!');
        }

        break;

    // eliminazione documento
    case 'delete':
        if ($dir == 'uscita') {
            $non_rimovibili = $dbo->fetchArray("SELECT COUNT(*) AS non_rimovibili FROM co_righe_documenti WHERE serial IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') AND iddocumento=".prepare($id_record))[0]['non_rimovibili'];
            if ($non_rimovibili != 0) {
                $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                return;
            }
        }

        // Se ci sono dei preventivi collegati li rimetto nello stato "In attesa di pagamento"
        $query = 'SELECT idpreventivo FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idpreventivo IS NOT NULL';
        $rs = $dbo->fetchArray($query);

        for ($i = 0; $i < sizeof($rs); ++$i) {
            $dbo->query("UPDATE co_preventivi SET idstato=(SELECT id FROM co_statipreventivi WHERE descrizione='In lavorazione') WHERE id=".prepare($rs[$i]['idpreventivo']));
        }

        // Se ci sono degli interventi collegati li rimetto nello stato "Completato"
        $query = 'SELECT idintervento FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idintervento IS NOT NULL';
        $rs = $dbo->fetchArray($query);

        for ($i = 0; $i < sizeof($rs); ++$i) {
            $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Completato') WHERE id=".prepare($rs[$i]['idintervento']));
        }

        // Se ci sono degli articoli collegati (ma non collegati a preventivi o interventi) li rimetto nel magazzino
        $query = 'SELECT id, idarticolo FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND NOT idarticolo=0 AND idpreventivo=0 AND idintervento IS NULL';
        $rs = $dbo->fetchArray($query);

        for ($i = 0; $i < sizeof($rs); ++$i) {
            rimuovi_articolo_dafattura($rs[$i]['idarticolo'], $id_record, $rs[$i]['id']);
        }

        $dbo->query('DELETE FROM co_documenti WHERE id='.prepare($id_record));
        $dbo->query('DELETE FROM co_righe_documenti WHERE iddocumento='.prepare($id_record));
        $dbo->query('DELETE FROM co_scadenziario WHERE iddocumento='.prepare($id_record));
        $dbo->query('DELETE FROM mg_movimenti WHERE iddocumento='.prepare($id_record));

        // Azzeramento collegamento della rata contrattuale alla pianificazione
        $dbo->query('UPDATE co_ordiniservizio_pianificazionefatture SET iddocumento=0 WHERE iddocumento='.prepare($id_record));

        elimina_scadenza($id_record);
        elimina_movimento($id_record);

        $_SESSION['infos'][] = _('Fattura eliminata!');

        break;

    // Duplicazione fattura
    case 'copy':
        if ($id_record) {
            // Calcolo prossimo numero fattura
            $numero = get_new_numerofattura(date('Y-m-d'));

            if ($dir == 'entrata') {
                $numero_esterno = get_new_numerosecondariofattura(date('Y-m-d'));
            } else {
                $numero_esterno = '';
            }

            // Lettura dati fattura attuale
            $rs = $dbo->fetchArray('SELECT * FROM co_documenti WHERE id='.prepare($id_record));

            // Duplicazione intestazione
            $dbo->query('INSERT INTO co_documenti(numero, numero_esterno, data, idanagrafica, idcausalet, idspedizione, idporto, idaspettobeni, idvettore, n_colli, idsede, idtipodocumento, idstatodocumento, idpagamento, idconto, idrivalsainps, idritenutaacconto, rivalsainps, iva_rivalsainps, ritenutaacconto, bollo, note, note_aggiuntive, buono_ordine) VALUES('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($rs[0]['data']).', '.prepare($rs[0]['idanagrafica']).', '.prepare($rs[0]['idcausalet']).', '.prepare($rs[0]['idspedizione']).', '.prepare($rs[0]['idporto']).', '.prepare($rs[0]['idaspettobeni']).', '.prepare($rs[0]['idvettore']).', '.prepare($rs[0]['n_colli']).', '.prepare($rs[0]['idsede']).', '.prepare($rs[0]['idtipodocumento']).', (SELECT id FROM co_statidocumento WHERE descrizione=\'Bozza\'), '.prepare($rs[0]['idpagamento']).', '.prepare($rs[0]['idconto']).', '.prepare($rs[0]['idrivalsainps']).', '.prepare($rs[0]['idritenutaacconto']).', '.prepare($rs[0]['rivalsainps']).', '.prepare($rs[0]['iva_rivalsainps']).', '.prepare($rs[0]['ritenutaacconto']).', '.prepare($rs[0]['bollo']).', '.prepare($rs[0]['note']).', '.prepare($rs[0]['note_aggiuntive']).', '.prepare($rs[0]['buono_ordine']).')');
            $id_record = $dbo->lastInsertedID();

            // Duplicazione righe
            $rs = $dbo->fetchArray('SELECT * FROM co_righe_documenti WHERE iddocumento='.prepare($id_record));

            for ($i = 0; $i < sizeof($rs); ++$i) {
                $dbo->query('INSERT INTO co_righe_documenti(iddocumento, idordine, idddt, idintervento, idarticolo, idpreventivo, idcontratto, idtecnico, idagente, idautomezzo, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, idritenutaacconto, ritenutaacconto, idrivalsainps, rivalsainps, um, qta, lotto, serial, altro, idgruppo, `order`) VALUES('.prepare($id_record).', 0, 0, 0, '.prepare($rs[$i]['idarticolo']).', '.prepare($rs[$i]['idpreventivo']).', '.prepare($rs[$i]['idcontratto']).', '.prepare($rs[$i]['idtecnico']).', '.prepare($rs[$i]['idagente']).', '.prepare($rs[$i]['idautomezzo']).', '.prepare($rs[$i]['idiva']).', '.prepare($rs[$i]['desc_iva']).', '.prepare($rs[$i]['iva']).', '.prepare($rs[$i]['iva_indetraibile']).', '.prepare($rs[$i]['descrizione']).', '.prepare($rs[$i]['subtotale']).', '.prepare($rs[$i]['sconto']).', '.prepare($rs[$i]['idritenutaacconto']).', '.prepare($rs[$i]['ritenutaacconto']).', '.prepare($rs[$i]['idrivalsainps']).', '.prepare($rs[$i]['rivalsainps']).', '.prepare($rs[$i]['um']).', '.prepare($rs[$i]['qta']).', '.prepare($rs[$i]['lotto']).', '.prepare($rs[$i]['serial']).', '.prepare($rs[$i]['altro']).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))');

                // Scarico/carico nuovamente l'articolo da magazzino
                if (!empty($rs[$i]['idarticolo'])) {
                    add_articolo_infattura($id_record, $rs[$i]['idarticolo'], $rs[$i]['descrizione'], $rs[$i]['idiva'], $rs[$i]['qta'], $rs[$i]['subtotale'], 0, 0, 0);
                }
            }

            // Ricalcolo inps, ritenuta e bollo (se la fattura non è stata pagata)
            if ($dir == 'entrata') {
                ricalcola_costiagg_fattura($id_record);
            } else {
                ricalcola_costiagg_fattura($id_record, $rs[0]['idrivalsainps'], $rs[0]['idritenutaacconto'], $rs[0]['bollo']);
            }

            $_SESSION['infos'][] = _('Fattura duplicata correttamente!');

            redirect($rootdir.'/editor.php?id_module='.$id_module.'&id_record='.$id_record);
        }

        break;

    case 'reopen':
        if (!empty($id_record)) {
            if ($dbo->query("UPDATE co_documenti SET idstatodocumento=(SELECT id FROM co_statidocumento WHERE descrizione='Bozza') WHERE id=".prepare($id_record))) {
                elimina_scadenza($id_record);
                elimina_movimento($id_record, 1);
                ricalcola_costiagg_fattura($id_record);
                $_SESSION['infos'][] = _('Fattura riaperta!');
            }
        }

        break;

    case 'addintervento':
        if (!empty($id_record) && isset($post['idintervento'])) {
            $idintervento = post('idintervento');
            $descrizione = post('descrizione');
            $idiva = post('idiva');
            $idconto = post('idconto');

            $prezzo = $post['prezzo'];
            $qta = 1;

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            // Leggo l'anagrafica del cliente
            $rs = $dbo->fetchArray('SELECT idanagrafica, codice, (SELECT MIN(orario_inizio) FROM in_interventi_tecnici WHERE idintervento='.prepare($idintervento).') AS data FROM `in_interventi` WHERE id='.prepare($idintervento));
            $idanagrafica = $rs[0]['idanagrafica'];
            $data = $rs[0]['data'];
            $codice = $rs[0]['codice'];

            if ($rs = $dbo->fetchArray('SELECT (SELECT SUM(km) FROM in_interventi_tecnici GROUP BY idintervento HAVING idintervento=in_interventi.id) AS km, (SELECT costo_orario FROM in_tipiintervento WHERE idtipointervento=in_interventi.idtipointervento) AS prezzo_ore_unitario, (SELECT costo_km FROM in_tipiintervento WHERE idtipointervento=in_interventi.idtipointervento) AS prezzo_km_unitario, (SELECT costo_diritto_chiamata FROM in_tipiintervento WHERE idtipointervento=in_interventi.idtipointervento) AS prezzo_diritto_chiamata, (SELECT SUM(TIME_TO_SEC(TIMEDIFF(orario_fine, orario_inizio))) FROM in_interventi_tecnici GROUP BY idintervento HAVING in_interventi_tecnici.idintervento=in_interventi.id) AS t1, (SELECT SUM(km) FROM in_interventi_tecnici GROUP BY idintervento HAVING idintervento=in_interventi.id) AS km, (SELECT SUM(prezzo_ore_consuntivo) FROM in_interventi_tecnici GROUP BY idintervento HAVING idintervento=in_interventi.id) AS tot_ore_consuntivo, (SELECT SUM(prezzo_km_consuntivo) FROM in_interventi_tecnici GROUP BY idintervento HAVING idintervento=in_interventi.id) AS tot_km_consuntivo, (SELECT COUNT(idtecnico) FROM in_interventi_tecnici WHERE idintervento=in_interventi.id) AS n_tecnici FROM `in_interventi` WHERE in_interventi.id='.prepare($idintervento).' AND in_interventi.id NOT IN (SELECT idintervento FROM co_righe_documenti WHERE idintervento IS NOT NULL AND NOT idintervento IS NULL)')) {
                // Collego in fattura eventuali articoli collegati all'intervento
                $rs2 = $dbo->fetchArray('SELECT mg_articoli_interventi.*, idarticolo FROM mg_articoli_interventi INNER JOIN mg_articoli ON mg_articoli_interventi.idarticolo=mg_articoli.id WHERE idintervento='.prepare($idintervento).' AND ( idintervento NOT IN(SELECT idintervento FROM co_righe_preventivi WHERE idpreventivo IN(SELECT idpreventivo FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).')) AND idintervento NOT IN(SELECT idintervento FROM co_righe_contratti WHERE idcontratto IN(SELECT idcontratto FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).')) )');
                for ($i = 0; $i < sizeof($rs2); ++$i) {
                    add_articolo_infattura($id_record, $rs2[$i]['idarticolo'], $rs2[$i]['descrizione'], $idiva, $rs2[$i]['qta'], $rs2[$i]['prezzo_vendita'] * $rs2[$i]['qta'], $rs2[$i]['sconto'], $rs2[$i]['sconto_unitario'], $rs2[$i]['tipo_sconto'], $idintervento, '', $rs2[$i]['serial']);
                }

                // Subtot
                $prezzo_ore_consuntivo = $rs[0]['tot_ore_consuntivo'];
                $prezzo_km_consuntivo = $rs[0]['tot_km_consuntivo'];
                $prezzo_ore_unitario = $rs[0]['prezzo_ore_unitario'];
                $prezzo_km_unitario = $rs[0]['prezzo_km_unitario'];
                $prezzo_diritto_chiamata = $rs[0]['prezzo_diritto_chiamata'];
                $km = $rs[0]['km'];

                // Aggiunta km come "Trasferta" (se c'è)
                if ($prezzo_km_consuntivo > 0) {
                    // Calcolo iva
                    $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                    $rs = $dbo->fetchArray($query);
                    $desc_iva = $rs[0]['descrizione'];

                    $subtot = $prezzo_km_consuntivo;
                    $iva = ($subtot) / 100 * $rs[0]['percentuale'];
                    $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                    // Calcolo rivalsa inps
                    $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(get_var('Percentuale rivalsa INPS'));
                    $rs = $dbo->fetchArray($query);
                    $rivalsainps = $subtot / 100 * $rs[0]['percentuale'];

                    // Calcolo ritenuta d'acconto
                    $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(get_var("Percentuale ritenuta d'acconto"));
                    $rs = $dbo->fetchArray($query);
                    $ritenutaacconto = ($subtot + $rivalsainps) / 100 * $rs[0]['percentuale'];

                    $query = 'INSERT INTO co_righe_documenti(iddocumento, idintervento, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) VALUES('.prepare($id_record).', NULL, '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare('Trasferta intervento '.$codice.' del '.Translator::dateToLocale($data)).', '.prepare($subtot).", 'km' ".prepare($km).', '.prepare(get_var('Percentuale rivalsa INPS')).', '.prepare($rivalsainps).', '.prepare(get_var("Percentuale ritenuta d'acconto")).', '.prepare($ritenutaacconto).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                    $dbo->query($query);
                }

                // Aggiunta spese aggiuntive come righe generiche
                $query = 'SELECT * FROM in_righe_interventi WHERE idintervento='.prepare($idintervento).' AND ( idintervento NOT IN(SELECT idintervento FROM co_righe_preventivi WHERE idpreventivo IN(SELECT idpreventivo FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).')) AND idintervento NOT IN(SELECT idintervento FROM co_righe_contratti WHERE idcontratto IN(SELECT idcontratto FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).')) )';
                $rsr = $dbo->fetchArray($query);
                if (sizeof($rsr) > 0) {
                    for ($i = 0; $i < sizeof($rsr); ++$i) {
                        // Calcolo iva
                        $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                        $rs = $dbo->fetchArray($query);
                        $desc_iva = $rs[0]['descrizione'];

                        $subtot = $rsr[$i]['prezzo_vendita'] * $rsr[$i]['qta'];
                        $iva = ($subtot) / 100 * $rs[0]['percentuale'];
                        $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                        // Calcolo rivalsa inps
                        $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(get_var('Percentuale rivalsa INPS'));
                        $rs = $dbo->fetchArray($query);
                        $rivalsainps = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];

                        // Calcolo ritenuta d'acconto
                        $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(get_var("Percentuale ritenuta d'acconto"));
                        $rs = $dbo->fetchArray($query);
                        $ritenutaacconto = ($subtot - $sconto + $rivalsainps) / 100 * $rs[0]['percentuale'];

                        $query = 'INSERT INTO co_righe_documenti(iddocumento, idintervento, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) VALUES('.prepare($id_record).', NULL, '.prepare($idconto).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($rsr[$i]['descrizione']).', '.prepare($subtot).", 0, 0, 'UNT', ".prepare($rsr[$i]['um']).', '.prepare($rsr[$i]['qta']).', '.prepare(get_var('Percentuale rivalsa INPS')).', '.prepare($rivalsainps).', '.prepare(get_var("Percentuale ritenuta d'acconto")).', '.prepare($ritenutaacconto).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                        $dbo->query($query);
                    }
                }

                $intervento = $dbo->fetchArray('SELECT (manodopera_scontato + viaggio_scontato - vw_activity_subtotal.sconto_globale) AS prezzo FROM in_interventi JOIN vw_activity_subtotal ON vw_activity_subtotal.id = in_interventi.id WHERE in_interventi.id = '.prepare($idintervento));

                $prezzo = $intervento[0]['prezzo'];

                // Calcolo iva
                $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($query);

                $subtot = $prezzo - $prezzo_km_consuntivo;
                $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
                $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
                $desc_iva = $rs[0]['descrizione'];

                // Calcolo rivalsa inps
                $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(get_var('Percentuale rivalsa INPS'));
                $rs = $dbo->fetchArray($query);
                $rivalsainps = $subtot / 100 * $rs[0]['percentuale'];

                // Calcolo ritenuta d'acconto
                $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(get_var("Percentuale ritenuta d'acconto"));
                $rs = $dbo->fetchArray($query);
                $ritenutaacconto = ($subtot + $rivalsainps) / 100 * $rs[0]['percentuale'];

                // Aggiunta riga intervento sul documento
                $query = 'INSERT INTO co_righe_documenti(iddocumento, idintervento, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idintervento).', '.prepare($idconto).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).", '-', ".prepare($qta).', '.prepare(get_var('Percentuale rivalsa INPS')).', '.prepare($rivalsainps).', '.prepare(get_var("Percentuale ritenuta d'acconto")).', '.prepare($ritenutaacconto).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                if ($dbo->query($query)) {
                    // Ricalcolo inps, ritenuta e bollo
                    if ($dir == 'entrata') {
                        ricalcola_costiagg_fattura($id_record);
                    } else {
                        ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                    }

                    // Metto l'intervento in stato "Fatturato"
                    $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Fatturato') WHERE id=".prepare($idintervento));

                    $_SESSION['infos'][] = str_replace('_NUM_', $idintervento, _('Intervento _NUM_ aggiunto!'));
                } else {
                    $_SESSION['errors'][] = str_replace('_NUM_', $idintervento, _("Errore durante l'inserimento dell'intervento _NUM_ in fattura!"));
                }
            }
        }
        break;

    case 'addpreventivo':
        if (!empty($id_record) && isset($post['idpreventivo'])) {
            $idpreventivo = post('idpreventivo');
            $descrizione = post('descrizione');
            $idiva = post('idiva');
            $idconto = post('idconto');

            $prezzo = $post['prezzo'];
            $qta = 1;

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            $subtot = 0;
            $aggiorna_budget = ($post['aggiorna_budget'] == 'on') ? 1 : 0;

            // Leggo l'anagrafica del cliente
            $rs = $dbo->fetchArray('SELECT idanagrafica, numero FROM `co_preventivi` WHERE id='.prepare($idpreventivo));
            $idanagrafica = $rs[0]['idanagrafica'];
            $numero = $rs[0]['numero'];

            // Calcolo iva
            $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
            $rs = $dbo->fetchArray($query);
            $iva = $prezzo / 100 * $rs[0]['percentuale'];
            $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
            $desc_iva = $rs[0]['descrizione'];

            // Calcolo rivalsa inps
            $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(get_var('Percentuale rivalsa INPS'));
            $rs = $dbo->fetchArray($query);
            $rivalsainps = ($prezzo - $sconto) / 100 * $rs[0]['percentuale'];

            // Calcolo ritenuta d'acconto
            $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(get_var("Percentuale ritenuta d'acconto"));
            $rs = $dbo->fetchArray($query);
            $ritenutaacconto = ($prezzo - $sconto + $rivalsainps) / 100 * $rs[0]['percentuale'];

            if (!empty($post['import'])) {
                // Replicazione delle righe del preventivo sul documento
                $righe = $dbo->fetchArray('SELECT idarticolo, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, um, qta, sconto, sconto_unitario, tipo_sconto FROM co_righe_preventivi WHERE idpreventivo='.prepare($idpreventivo));
                foreach ($righe as $key => $riga) {
                    $dbo->insert('co_righe_documenti', [
                        'iddocumento' => $id_record,
                        'idpreventivo' => $idpreventivo,
                        'idconto' => $idconto,
                        'idarticolo' => $riga['idarticolo'],
                        'idiva' => $riga['idiva'],
                        'desc_iva' => $riga['desc_iva'],
                        'iva' => $riga['iva'],
                        'iva_indetraibile' => $riga['iva_indetraibile'],
                        'descrizione' => str_replace('SCONTO', 'SCONTO PREVENTIVO', $riga['descrizione']),
                        'subtotale' => $riga['subtotale'],
                        'um' => $riga['um'],
                        'qta' => $riga['qta'],
                        'sconto' => $riga['sconto'],
                        'sconto_unitario' => $riga['sconto_unitario'],
                        'order' => '#(SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).')#',
                        'idgruppo' => '#(SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).')#',
                        'idritenutaacconto' => get_var("Percentuale ritenuta d'acconto"),
                        'ritenutaacconto' => $ritenutaacconto,
                        'idrivalsainps' => get_var('Percentuale rivalsa INPS'),
                        'rivalsainps' => $rivalsainps,
                    ]);

                    if (!empty($riga['idarticolo'])) {
                        add_movimento_magazzino($riga['idarticolo'], -$riga['qta'], ['iddocumento' => $id_record]);
                    }
                }
            } else {
                // Aggiunta riga preventivo sul documento
                $query = 'INSERT INTO co_righe_documenti(iddocumento, idpreventivo, idconto, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idritenutaacconto, ritenutaacconto, idrivalsainps, rivalsainps, `order`, idgruppo) VALUES('.prepare($id_record).', '.prepare($idpreventivo).', '.prepare($idconto).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($prezzo).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).", '-', 1, ".prepare(get_var("Percentuale ritenuta d'acconto")).', '.prepare($ritenutaacconto).', '.prepare(get_var('Percentuale rivalsa INPS')).', '.prepare($rivalsainps).', (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                $dbo->query($query);

                // Aggiorno lo stato degli interventi collegati al preventivo se ce ne sono
                $query2 = 'SELECT idpreventivo FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND NOT idpreventivo=0 AND idpreventivo IS NOT NULL';
                $rs2 = $dbo->fetchArray($query2);
                for ($j = 0; $j < sizeof($rs2); ++$j) {
                    $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Fatturato') WHERE id IN (SELECT idintervento FROM co_preventivi_interventi WHERE idpreventivo=".prepare($rs2[$j]['idpreventivo']).')');
                }
            }

            $_SESSION['infos'][] = str_replace('_NUM_', $numero, _('Preventivo _NUM_ aggiunto!'));

            // Aggiorno il budget sul preventivo con l'importo inserito in fattura e imposto lo stato del preventivo "In attesa di pagamento" (se selezionato)
            if ($aggiorna_budget) {
                $dbo->query('UPDATE co_preventivi SET budget='.prepare($prezzo).' WHERE id='.prepare($idpreventivo));
            }
            $dbo->query("UPDATE co_preventivi SET idstato=(SELECT id FROM co_statipreventivi WHERE descrizione='In attesa di pagamento') WHERE id=".prepare($idpreventivo));

            // Ricalcolo inps, ritenuta e bollo
            if ($dir == 'entrata') {
                ricalcola_costiagg_fattura($id_record);
            } else {
                ricalcola_costiagg_fattura($id_record, 0, 0, 0);
            }
        }

        break;

    case 'addcontratto':
        if (!empty($id_record) && isset($post['idcontratto'])) {
            $idcontratto = post('idcontratto');
            $descrizione = post('descrizione');
            $idiva = post('idiva');
            $idconto = post('idconto');

            $prezzo = $post['prezzo'];
            $qta = 1;

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            $subtot = 0;
            $aggiorna_budget = ($post['aggiorna_budget'] == 'on') ? 1 : 0;

            // Leggo l'anagrafica del cliente
            $rs = $dbo->fetchArray('SELECT idanagrafica, numero FROM `co_contratti` WHERE id='.prepare($idcontratto));
            $idanagrafica = $rs[0]['idanagrafica'];
            $numero = $rs[0]['numero'];

            // Calcolo iva
            $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
            $rs = $dbo->fetchArray($query);
            $iva = $prezzo / 100 * $rs[0]['percentuale'];
            $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
            $desc_iva = $rs[0]['descrizione'];

            // Calcolo rivalsa inps
            $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(get_var('Percentuale rivalsa INPS'));
            $rs = $dbo->fetchArray($query);
            $rivalsainps = $prezzo / 100 * $rs[0]['percentuale'];

            // Calcolo ritenuta d'acconto
            $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(get_var("Percentuale ritenuta d'acconto"));
            $rs = $dbo->fetchArray($query);
            $ritenutaacconto = ($prezzo) / 100 * $rs[0]['percentuale'];

            // Aggiunta riga contratto sul documento
            $query = 'INSERT INTO co_righe_documenti(iddocumento, idcontratto, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idcontratto).', '.prepare($idconto).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($prezzo).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).", '-', 1, ".prepare(get_var('Percentuale rivalsa INPS')).', '.prepare($rivalsainps).', '.prepare(get_var("Percentuale ritenuta d'acconto")).', '.prepare($ritenutaacconto).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
            if ($dbo->query($query)) {
                $_SESSION['infos'][] = str_replace('_NUM_', $numero, _('Contratto _NUM_ aggiunto!'));

                // Aggiorno il budget sul contratto con l'importo inserito in fattura e imposto lo stato del contratto "In attesa di pagamento" (se selezionato)
                if ($aggiorna_budget) {
                    $dbo->query('UPDATE co_contratti SET budget='.prepare($prezzo).' WHERE id='.prepare($idcontratto));
                }

                $dbo->query("UPDATE co_contratti SET idstato=(SELECT id FROM co_staticontratti WHERE descrizione='In attesa di pagamento') WHERE id=".prepare($idcontratto));

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                }
            }
        }
        break;

    case 'addarticolo':
        if (!empty($id_record) && isset($post['idarticolo'])) {
            $idarticolo = post('idarticolo');
            $descrizione = post('descrizione');

            $idiva = post('idiva');
            $idconto = post('idconto');
            $idum = post('um');

            $qta = $post['qta'];
            $prezzo = $post['prezzo'];

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            // Calcolo idgruppo per questo inserimento
            $ridgruppo = $dbo->fetchArray('SELECT IFNULL(MAX(idgruppo) + 1, 0) AS idgruppo FROM co_righe_documenti WHERE iddocumento = '.prepare($id_record));
            $idgruppo = $ridgruppo[0]['idgruppo'];

            add_articolo_infattura($id_record, $idarticolo, $descrizione, $idiva, $qta, $prezzo * $qta, $sconto, $sconto_unitario, $tipo_sconto, '0', $lotto, $serial, $altro, $idgruppo, $idconto, $idum);

            $_SESSION['infos'][] = _('Articolo aggiunto!');
        }
        break;

    case 'addriga':
        if (!empty($id_record)) {
            // Selezione costi da intervento
            $descrizione = post('descrizione');
            $idiva = post('idiva');
            $idconto = post('idconto');

            $qta = $post['qta'];
            $prezzo = $post['prezzo'];

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            $subtot = $prezzo * $qta;

            // Calcolo iva
            $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
            $rs = $dbo->fetchArray($query);
            $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
            $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
            $desc_iva = $rs[0]['descrizione'];

            // Calcolo rivalsa inps
            $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(post('idrivalsainps'));
            $rs = $dbo->fetchArray($query);
            $rivalsainps = $prezzo * $qta / 100 * $rs[0]['percentuale'];

            // Calcolo ritenuta d'acconto
            $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(post('idritenutaacconto'));
            $rs = $dbo->fetchArray($query);
            $ritenutaacconto = (($prezzo * $qta) + $rivalsainps) / 100 * $rs[0]['percentuale'];

            // Calcolo idgruppo per questo inserimento
            $ridgruppo = $dbo->fetchArray('SELECT IFNULL(MAX(idgruppo) + 1, 0) AS idgruppo FROM co_righe_documenti WHERE iddocumento = '.prepare($id_record));
            $idgruppo = $ridgruppo[0]['idgruppo'];

            // Aggiunta riga generica sul documento
            $query = 'INSERT INTO co_righe_documenti(iddocumento, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idconto).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).', '.prepare($um).', '.prepare($qta).', '.prepare(post('idrivalsainps')).', '.prepare($rivalsainps).', '.prepare(post('idritenutaacconto')).', '.prepare($ritenutaacconto).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';

            if ($dbo->query($query)) {
                $_SESSION['infos'][] = _('Riga aggiunta!');

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record);
                }
            }
        }
        break;

    case 'editriga':
        if (isset($post['idriga'])) {
            // Selezione costi da intervento
            $idriga = post('idriga');
            $descrizione = post('descrizione');
            $idiva = post('idiva');
            $idconto = post('idconto');
            $um = post('um');

            $qta = $post['qta'];
            $prezzo = $post['prezzo'];

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            $subtot = $prezzo * $qta;

            // Lettura idarticolo dalla riga documento
            $rs = $dbo->fetchArray('SELECT idgruppo, iddocumento, idarticolo, qta, abilita_serial FROM co_righe_documenti WHERE id='.prepare($idriga));
            $idarticolo = $rs[0]['idarticolo'];
            $old_qta = $rs[0]['qta'];
            $idgruppo = $rs[0]['idgruppo'];
            $iddocumento = $rs[0]['iddocumento'];
            $abilita_serial = $rs[0]['abilita_serial'];

            // Calcolo iva
            $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
            $rs = $dbo->fetchArray($query);
            $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
            $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
            $desc_iva = $rs[0]['descrizione'];

            // Calcolo rivalsa inps
            $query = 'SELECT * FROM co_rivalsainps WHERE id='.prepare(post('idrivalsainps'));
            $rs = $dbo->fetchArray($query);
            $rivalsainps = $prezzo * $qta / 100 * $rs[0]['percentuale'];

            // Calcolo ritenuta d'acconto
            $query = 'SELECT * FROM co_ritenutaacconto WHERE id='.prepare(post('idritenutaacconto'));
            $rs = $dbo->fetchArray($query);
            $ritenutaacconto = (($prezzo * $qta) + $rivalsainps) / 100 * $rs[0]['percentuale'];

            // Modifica riga generica sul documento
            $query = 'UPDATE co_righe_documenti SET idconto='.prepare($idconto).', idiva='.prepare($idiva).', desc_iva='.prepare($desc_iva).', iva='.prepare($iva).', iva_indetraibile='.prepare($iva_indetraibile).', descrizione='.prepare($descrizione).', subtotale='.prepare($subtot).', sconto='.prepare($sconto).', sconto_unitario='.prepare($sconto_unitario).', tipo_sconto='.prepare($tipo_sconto).', um='.prepare($um).', idritenutaacconto='.prepare(post('idritenutaacconto')).', ritenutaacconto='.prepare($ritenutaacconto).', idrivalsainps='.prepare(post('idrivalsainps')).', rivalsainps='.prepare($rivalsainps).' WHERE idgruppo='.prepare($idgruppo).' AND iddocumento='.prepare($iddocumento);
            if ($dbo->query($query)) {
                // Modifica della quantità
                $dbo->query('UPDATE co_righe_documenti SET qta='.prepare($qta).' WHERE idgruppo='.prepare($idgruppo));

                // Modifica per gestire i serial
                if (!empty($idarticolo)) {
                    $new_qta = $qta - $old_qta;
                    $new_qta = ($old_qta < $qta) ? $new_qta : -$new_qta;

                    if (!empty($abilita_serial)) {
                        if ($old_qta < $qta) {
                            for ($i = 0; $i < $new_qta; ++$i) {
                                $dbo->query('INSERT INTO co_righe_documenti(iddocumento, idarticolo, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order`) SELECT iddocumento, idarticolo, idconto, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idrivalsainps, rivalsainps, idritenutaacconto, ritenutaacconto, idgruppo, `order` FROM co_righe_documenti WHERE id='.prepare($idriga));
                            }
                        } else {
                            if ($dir == 'uscita') {
                                if ($new_qta > $dbo->fetchArray("SELECT COUNT(*) AS rimovibili FROM co_righe_documenti WHERE serial NOT IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') AND idgruppo=".prepare($idgruppo).' AND iddocumento='.prepare($iddocumento))[0]['rimovibili']) {
                                    $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                                    return;
                                } else {
                                    $deletes = $dbo->fetchArray('SELECT id FROM co_righe_documenti AS t WHERE idgruppo = '.prepare($idgruppo).' AND iddocumento='.prepare($iddocumento)." AND serial NOT IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') ORDER BY serial ASC LIMIT ".$new_qta);
                                }
                            } else {
                                $deletes = $dbo->fetchArray('SELECT id FROM co_righe_documenti AS t WHERE idgruppo = '.prepare($idgruppo).' AND iddocumento='.prepare($iddocumento).' ORDER BY serial ASC LIMIT '.$new_qta);
                            }

                            foreach ((array) $deletes as $delete) {
                                $dbo->query('DELETE FROM co_righe_documenti WHERE id = '.prepare($delete['id']));
                            }
                        }
                    }

                    $new_qta = ($old_qta < $qta) ? $new_qta : -$new_qta;

                    $new_qta = ($dir == 'entrata') ? -$new_qta : $new_qta;
                    add_movimento_magazzino($idarticolo, $new_qta, ['iddocumento' => $id_record]);
                }

                $_SESSION['infos'][] = _('Riga modificata!');

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record);
                }
            }
        }
        break;

    // Creazione fattura da ddt
    case 'fattura_da_ddt':
        $totale_fattura = 0.00;
        $data = $post['data'];
        $idanagrafica = $post['idanagrafica'];
        $idarticolo = $post['idarticolo'];
        $idpagamento = $post['idpagamento'];
        $idconto = $post['idconto'];
        $idddt = $post['idddt'];
        $numero = get_new_numerofattura($data);

        if ($dir == 'entrata') {
            $numero_esterno = get_new_numerosecondariofattura($data);
        } else {
            $numero_esterno = '';
        }

        $tipo_documento = ($dir == 'entrata') ? 'Fattura differita di vendita' : 'Fattura differita di acquisto';

        // Creazione nuova fattura
        $dbo->query('INSERT INTO co_documenti(numero, numero_esterno, data, idanagrafica, idtipodocumento, idstatodocumento, idpagamento, idconto) VALUES('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($data).', '.prepare($idanagrafica).', (SELECT id FROM co_tipidocumento WHERE descrizione='.prepare($tipo_documento)."), (SELECT id FROM co_statidocumento WHERE descrizione='Bozza'), ".prepare($idpagamento).', '.prepare($idconto).')');
        $id_record = $dbo->lastInsertedID();

        // Lettura di tutte le righe della tabella in arrivo
        for ($i = 0; $i < sizeof($post['qta_da_evadere']); ++$i) {
            // Processo solo le righe da evadere
            if ($post['evadere'][$i] == 'on') {
                $idrigaddt = post('idrigaddt')[$i];
                $idarticolo = post('idarticolo')[$i];
                $descrizione = post('descrizione')[$i];
                $qta = $post['qta_da_evadere'][$i];
                $um = $post['um'][$i];
                $subtot = $post['subtot'][$i] * $qta;
                $sconto = $post['sconto'][$i];
                $sconto = $sconto * $qta;
                $idiva = post('idiva')[$i];

                $qprc = 'SELECT tipo_sconto, sconto_unitario FROM dt_righe_ddt WHERE id='.$idrigaddt;
                $rsprc = $dbo->fetchArray($qprc);

                $sconto_unitario = $rsprc[0]['sconto_unitario'];
                $tipo_sconto = $rsprc[0]['tipo_sconto'];

                // Leggo la descrizione iva
                $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($query);
                $perc_iva = $rs[0]['percentuale'];
                $desc_iva = $rs[0]['descrizione'];
                $iva = $subtot / 100 * $perc_iva;

                // Calcolo l'iva indetraibile
                $q = 'SELECT indetraibile FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($q);
                $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                // Lettura lotto, serial, altro dalla riga dell'ordine
                $q = 'SELECT lotto, serial, altro, descrizione FROM dt_righe_ddt WHERE id='.prepare($idrigaddt);
                $rs = $dbo->fetchArray($q);

                // Se sto aggiungendo un articolo uso la funzione per inserirlo e incrementare la giacenza
                if (!empty($idarticolo)) {
                    $idiva_acquisto = $idiva;
                    $prezzo_acquisto = $subtot;
                    add_articolo_infattura($id_record, $idarticolo, $rs[0]['descrizione'], $idiva_acquisto, $qta, $prezzo_acquisto, $sconto, $sconto_unitario, $tipo_sconto, $rs[0]['lotto'], $rs[0]['serial'], $rs[0]['altro'], $rs[0]['idgruppo']);
                }

                // Inserimento riga normale
                elseif ($qta != 0) {
                    // Se la riga che sto inserendo è simile ad altre già inserite, aggiorno solo la quantità...
                    $query = 'SELECT id FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND descrizione='.prepare($descrizione).' AND (subtotale/qta)='.($subtot / $qta).' AND um='.prepare($um).' AND sconto='.prepare($sconto / $qta).' AND idiva='.prepare($idiva);
                    $rs = $dbo->fetchArray($query);

                    if (sizeof($rs) > 0) {
                        $query = 'UPDATE co_righe_documenti SET qta=qta+'.$qta.' WHERE id='.prepare($rs[0]['id']);
                    }

                    // ...altrimenti aggiungo una nuova riga
                    else {
                        $query = 'INSERT INTO co_righe_documenti(iddocumento, idarticolo, descrizione, idddt, idiva, desc_iva, iva, iva_indetraibile, subtotale, sconto, um, qta, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idarticolo).', '.prepare($descrizione).', '.prepare($idddt).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($um).', '.prepare($qta).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                    }

                    $dbo->query($query);
                }

                // Scalo la quantità dal ddt
                $dbo->query('UPDATE dt_righe_ddt SET qta_evasa = qta_evasa+'.$qta.' WHERE id='.prepare($idrigaddt));
            }
        }

        ricalcola_costiagg_fattura($id_record);

        $_SESSION['infos'][] = _('Creata una nuova fattura!');

        break;

    // Creazione fattura da ordine
    case 'fattura_da_ordine':
        $totale_fattura = 0.00;
        $data = $post['data'];
        $idanagrafica = $post['idanagrafica'];
        $idarticolo = $post['idarticolo'];
        $idpagamento = $post['idpagamento'];
        $idconto = $post['idconto'];
        $idordine = $post['idordine'];
        $numero = get_new_numerofattura($data);
        $numero_esterno = get_new_numerosecondariofattura($data);

        $tipo_documento = ($dir == 'entrata') ? 'Fattura immediata di vendita' : 'Fattura immediata di acquisto';

        // Creazione nuova fattura
        $dbo->query('INSERT INTO co_documenti(numero, numero_esterno, data, idanagrafica, idtipodocumento, idstatodocumento, idpagamento, idconto) VALUES('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($data).', '.prepare($idanagrafica).', (SELECT id FROM co_tipidocumento WHERE descrizione='.prepare($tipo_documento)."), (SELECT id FROM co_statidocumento WHERE descrizione='Bozza'), ".prepare($idpagamento).', '.prepare($idconto).')');
        $id_record = $dbo->lastInsertedID();

        // Lettura di tutte le righe della tabella in arrivo
        for ($i = 0; $i < sizeof($post['qta_da_evadere']); ++$i) {
            // Processo solo le righe da evadere
            if ($post['evadere'][$i] == 'on') {
                $idrigaordine = post('idrigaordine')[$i];
                $idarticolo = post('idarticolo')[$i];
                $descrizione = post('descrizione')[$i];
                $qta = post('qta_da_evadere')[$i];
                $um = post('um')[$i];
                $subtot = save($post['subtot'][$i] * $qta);
                $idiva = post('idiva')[$i];
                $iva = save($post['iva'][$i] * $qta);
                $sconto = post('sconto')[$i];
                $sconto = $sconto * $qta;

                $qprc = 'SELECT tipo_sconto, sconto_unitario FROM or_righe_ordini WHERE id='.$idrigaordine;
                $rsprc = $dbo->fetchArray($qprc);

                $sconto_unitario = $rsprc[0]['sconto_unitario'];
                $tipo_sconto = $rsprc[0]['tipo_sconto'];

                // Calcolo l'iva indetraibile
                $q = 'SELECT indetraibile FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($q);
                $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                // Leggo la descrizione iva
                $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($query);
                $desc_iva = $rs[0]['descrizione'];

                // Lettura lotto, serial, altro dalla riga dell'ordine
                $q = 'SELECT lotto, serial, altro, descrizione FROM or_righe_ordini WHERE id='.prepare($idrigaordine);
                $rs = $dbo->fetchArray($q);

                // Se sto aggiungendo un articolo uso la funzione per inserirlo e incrementare la giacenza
                if (!empty($idarticolo)) {
                    $idiva_acquisto = $idiva;
                    $prezzo_acquisto = $subtot;
                    $idriga = add_articolo_infattura($id_record, $idarticolo, $rs[0]['descrizione'], $idiva_acquisto, $qta, $prezzo_acquisto, $sconto, $sconto_unitario, $tipo_sconto, '0', $rs[0]['lotto'], $rs[0]['serial'], $rs[0]['altro']);

                    // Imposto la provenienza dell'ordine
                    $dbo->query('UPDATE co_righe_documenti SET idordine='.prepare($idordine).' WHERE id='.prepare($idriga));
                }

                // Inserimento riga normale
                elseif ($qta != 0) {
                    // Se la riga che sto inserendo è simile ad altre già inserite, aggiorno solo la quantità...
                    $query = 'SELECT id FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND descrizione='.prepare($descrizione).' AND (subtotale/qta)='.($subtot / $qta).' AND um='.prepare($um).' AND sconto='.prepare($sconto / $qta).' AND idiva='.prepare($idiva);
                    $rs = $dbo->fetchArray($query);

                    if (sizeof($rs) > 0) {
                        $dbo->query('UPDATE co_righe_documenti SET qta=qta+'.$qta.' WHERE id='.prepare($rs[0]['id']));
                        $idriga = $rs[0]['id'];
                    }

                    // ...altrimenti aggiungo una nuova riga
                    else {
                        $dbo->query('INSERT INTO co_righe_documenti(iddocumento, idarticolo, idordine, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, um, qta, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idarticolo).', '.prepare($idordine).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($um).', '.prepare($qta).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))');
                        $idriga = $dbo->lastInsertedID();
                    }
                }

                // Scalo la quantità dall'ordine
                $dbo->query('UPDATE or_righe_ordini SET qta_evasa = qta_evasa+'.$qta.' WHERE id='.prepare($idrigaordine));
            }
        }

        ricalcola_costiagg_fattura($id_record);
        $_SESSION['infos'][] = _('Creata una nuova fattura!');

        break;

    // aggiungi righe da ddt
    case 'add_ddt':
        $idddt = $post['idddt'];

        $rs = $dbo->fetchArray('SELECT * FROM co_documenti WHERE id='.prepare($id_record));
        $idconto = $rs[0]['idconto'];

        // Lettura di tutte le righe della tabella in arrivo
        for ($i = 0; $i < sizeof($post['qta_da_evadere']); ++$i) {
            // Processo solo le righe da evadere
            if ($post['evadere'][$i] == 'on') {
                $idrigaddt = post('idrigaddt')[$i];
                $idarticolo = post('idarticolo')[$i];
                $descrizione = post('descrizione')[$i];

                $qta = $post['qta_da_evadere'][$i];
                $um = post('um')[$i];

                $subtot = $post['subtot'][$i] * $qta;
                $sconto = $post['sconto'][$i];
                $sconto = $sconto * $qta;

                $qprc = 'SELECT tipo_sconto, sconto_unitario FROM dt_righe_ddt WHERE id='.prepare($idrigaddt);
                $rsprc = $dbo->fetchArray($qprc);

                $sconto_unitario = $rsprc[0]['sconto_unitario'];
                $tipo_sconto = $rsprc[0]['tipo_sconto'];

                $idiva = post('idiva')[$i];

                // Calcolo l'iva indetraibile
                $q = 'SELECT percentuale, indetraibile FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($q);
                $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
                $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                // Leggo la descrizione iva
                $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($query);
                $desc_iva = $rs[0]['descrizione'];

                // Lettura lotto, serial, altro dalla riga dell'ordine
                $q = 'SELECT lotto, serial, altro, descrizione FROM dt_righe_ddt WHERE id='.prepare($idrigaddt);
                $rs = $dbo->fetchArray($q);

                // Se sto aggiungendo un articolo uso la funzione per inserirlo e incrementare la giacenza
                if (!empty($idarticolo)) {
                    $idiva_acquisto = $idiva;
                    $prezzo_acquisto = $subtot;
                    add_articolo_infattura($id_record, $idarticolo, $rs[0]['descrizione'], $idiva_acquisto, $qta, $prezzo_acquisto, 0, 0, 'UNT', 0, $rs[0]['lotto'], $rs[0]['serial'], $rs[0]['altro'], 0, $idconto);
                }

                // Inserimento riga normale
                elseif ($qta != 0) {
                    $query = 'INSERT INTO co_righe_documenti(iddocumento, idarticolo, descrizione, idconto, idddt, idiva, desc_iva, iva, iva_indetraibile, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idarticolo).', '.prepare($descrizione).', '.prepare($idconto).', '.prepare($idddt).', '.prepare($idiva).', '.prepare($desc_iva).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).', '.prepare($um).', '.prepare($qta).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))';
                    $dbo->query($query);
                }

                // Scalo la quantità dal ddt
                $dbo->query('UPDATE dt_righe_ddt SET qta_evasa = qta_evasa+'.$qta.' WHERE id='.prepare($idrigaddt));
            }
        }

        ricalcola_costiagg_fattura($id_record);

        $_SESSION['infos'][] = _('Aggiunti nuovi articoli in fattura!');

        break;

    // Scollegamento intervento da documento
    case 'unlink_intervento':
        if (!empty($id_record) && isset($post['idriga'])) {
            $idriga = post('idriga');

            // Lettura preventivi collegati
            $query = 'SELECT idgruppo, iddocumento, idintervento FROM co_righe_documenti WHERE id='.prepare($idriga);
            $rsp = $dbo->fetchArray($query);
            $id_record = $rsp[0]['iddocumento'];
            $idgruppo = $rsp[0]['idgruppo'];
            $idintervento = $rsp[0]['idintervento'];

            $query = 'DELETE FROM `co_righe_documenti` WHERE iddocumento='.prepare($id_record).' AND idgruppo='.prepare($idgruppo);

            $dbo->query($query);

            // Ricalcolo inps, ritenuta e bollo
            if ($dir == 'entrata') {
                ricalcola_costiagg_fattura($id_record);
            } else {
                ricalcola_costiagg_fattura($id_record, 0, 0, 0);
            }

            // Lettura interventi collegati
            $query = 'SELECT id, idintervento FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idintervento IS NOT NULL';
            $rs = $dbo->fetchArray($query);

            // Se ci sono degli interventi collegati li rimetto nello stato "Completato"
            for ($i = 0; $i < sizeof($rs); ++$i) {
                $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Completato') WHERE id=".prepare($rs[$i]['idintervento']));

                // Rimuovo dalla fattura gli articoli collegati all'intervento
                $rs2 = $dbo->fetchArray('SELECT idarticolo FROM mg_articoli_interventi WHERE idintervento='.prepare($idintervento));
                for ($j = 0; $j < sizeof($rs2); ++$j) {
                    rimuovi_articolo_dafattura($rs[0]['idarticolo'], $id_record, $rs[0]['idrigadocumento']);
                }
            }

            $_SESSION['infos'][] = str_replace('_NUM_', $idintervento, _('Intervento _NUM_ rimosso!'));
        }
        break;

    // Scollegamento articolo da documento
    case 'unlink_articolo':
        if (!empty($id_record) && isset($post['idarticolo'])) {
            $idriga = post('idriga');
            $idarticolo = post('idarticolo');

            $res = rimuovi_articolo_dafattura($idarticolo, $id_record, $idriga);

            if (!$res) {
                $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                return;
            }

            if ($dbo->query('DELETE FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND id='.prepare($idriga))) {
                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                }

                $_SESSION['infos'][] = _('Articolo rimosso!');
            }
        }
        break;

    // Scollegamento preventivo da documento
    case 'unlink_preventivo':
        if (isset($post['idriga'])) {
            $idriga = post('idriga');

            // Lettura preventivi collegati
            $query = 'SELECT idgruppo, iddocumento, idpreventivo FROM co_righe_documenti WHERE id='.prepare($idriga);
            $rsp = $dbo->fetchArray($query);
            $id_record = $rsp[0]['iddocumento'];
            $idgruppo = $rsp[0]['idgruppo'];
            $idpreventivo = $rsp[0]['idpreventivo'];

            $query = 'DELETE FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idgruppo='.prepare($idgruppo);

            if ($dbo->query($query)) {
                // Se ci sono dei preventivi collegati li rimetto nello stato "In attesa di pagamento"
                for ($i = 0; $i < sizeof($rsp); ++$i) {
                    $dbo->query("UPDATE co_preventivi SET idstato=(SELECT id FROM co_statipreventivi WHERE descrizione='In lavorazione') WHERE id=".prepare($rsp[$i]['idpreventivo']));

                    // Aggiorno anche lo stato degli interventi collegati ai preventivi
                    $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Completato') WHERE id IN (SELECT idintervento FROM co_preventivi_interventi WHERE idpreventivo=".prepare($rsp[$i]['idpreventivo']).')');
                }

                /*
                    Rimuovo tutti gli articoli dalla fattura collegati agli interventi che sono collegati a questo preventivo
                */
                $rs2 = $dbo->fetchArray('SELECT idintervento FROM co_preventivi_interventi WHERE idpreventivo='.prepare($idpreventivo)." AND NOT idpreventivo=''");
                for ($i = 0; $i < sizeof($rs2); ++$i) {
                    // Leggo gli articoli usati in questo intervento
                    $rs3 = $dbo->fetchArray('SELECT idarticolo FROM mg_articoli_interventi WHERE idintervento='.prepare($rs2[$i]['idintervento']));
                    for ($j = 0; $j < sizeof($rs3); ++$j) {
                        // Leggo l'id della riga in fattura di questo articolo
                        $rs4 = $dbo->fetchArray('SELECT id FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idarticolo='.prepare($rs3[$j]['idarticolo']));
                        for ($x = 0; $x < sizeof($rs4); ++$x) {
                            rimuovi_articolo_dafattura($rs3[$j]['idarticolo'], $id_record, $rs4[$x]['id']);
                        }
                    }
                }

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                }

                $_SESSION['infos'][] = _('Preventivo rimosso!');
            }
        }
        break;

    // Scollegamento contratto da documento
    case 'unlink_contratto':
        if (isset($post['idriga'])) {
            $idriga = post('idriga');

            // Lettura contratti collegati
            $query = 'SELECT iddocumento, idcontratto FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idcontratto IS NOT NULL AND NOT idcontratto=0';
            $rsp = $dbo->fetchArray($query);
            $id_record = $rsp[0]['iddocumento'];
            $idcontratto = $rsp[0]['idcontratto'];

            $query = 'DELETE FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idcontratto='.prepare($idcontratto);

            if ($dbo->query($query)) {
                // Se ci sono dei preventivi collegati li rimetto nello stato "In attesa di pagamento"
                for ($i = 0; $i < sizeof($rsp); ++$i) {
                    $dbo->query("UPDATE co_contratti SET idstato=(SELECT id FROM co_staticontratti WHERE descrizione='In lavorazione') WHERE id=".prepare($rsp[$i]['idcontratto']));

                    // Aggiorno anche lo stato degli interventi collegati ai contratti
                    $dbo->query("UPDATE in_interventi SET idstatointervento=(SELECT idstatointervento FROM in_statiintervento WHERE descrizione='Completato') WHERE id IN (SELECT idintervento FROM co_righe_contratti WHERE idcontratto=".prepare($rsp[$i]['idcontratto']).')');
                }

                /*
                    Rimuovo tutti gli articoli dalla fattura collegati agli interventi che sono collegati a questo contratto
                */
                $rs2 = $dbo->fetchArray('SELECT idintervento FROM co_righe_contratti WHERE idcontratto='.prepare($idcontratto)." AND NOT idcontratto=''");
                for ($i = 0; $i < sizeof($rs2); ++$i) {
                    // Leggo gli articoli usati in questo intervento
                    $rs3 = $dbo->fetchArray('SELECT idarticolo FROM mg_articoli_interventi WHERE idintervento='.prepare($rs2[$i]['idintervento']));
                    for ($j = 0; $j < sizeof($rs3); ++$j) {
                        // Leggo l'id della riga in fattura di questo articolo
                        $rs4 = $dbo->fetchArray('SELECT id FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idarticolo='.prepare($rs3[$j]['idarticolo']));
                        for ($x = 0; $x < sizeof($rs4); ++$x) {
                            rimuovi_articolo_dafattura($rs3[$j]['idarticolo'], $id_record, $rs4[$x]['id']);
                        }
                    }
                }

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                }

                $_SESSION['infos'][] = _('Contratto rimosso!');
            }
        }
        break;

    // Scollegamento riga generica da documento
    case 'unlink_riga':
        if (isset($post['idriga'])) {
            $idriga = post('idriga');

            // Se la riga è stata creata da un ordine, devo riportare la quantità evasa nella tabella degli ordini
            // al valore di prima, riaggiungendo la quantità che sto togliendo
            $rs = $dbo->fetchArray('SELECT qta, descrizione, idarticolo, idordine, idiva FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND id='.prepare($idriga));

            // Rimpiazzo la quantità negli ordini
            $dbo->query('UPDATE or_righe_ordini SET qta_evasa=qta_evasa-'.$rs[0]['qta'].' WHERE descrizione='.prepare($rs[0]['descrizione']).' AND idarticolo='.prepare($rs[0]['idarticolo']).' AND idordine='.prepare($rs[0]['idordine']).' AND idiva='.prepare($rs[0]['idiva']));

            // Se la riga è stata creata da un ddt, devo riportare la quantità evasa nella tabella dei ddt
            // al valore di prima, riaggiungendo la quantità che sto togliendo
            $rs = $dbo->fetchArray('SELECT qta, descrizione, idarticolo, idddt, idiva FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND id='.prepare($idriga));

            // Rimpiazzo la quantità nei ddt
            $dbo->query('UPDATE dt_righe_ddt SET qta_evasa=qta_evasa-'.$rs[0]['qta'].' WHERE descrizione='.prepare($rs[0]['descrizione']).' AND idarticolo='.prepare($rs[0]['idarticolo']).' AND idddt='.prepare($rs[0]['idddt']).' AND idiva='.prepare($rs[0]['idiva']));

            $query = 'DELETE FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND id='.prepare($idriga);

            if ($dbo->query($query)) {
                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_fattura($id_record);
                } else {
                    ricalcola_costiagg_fattura($id_record, 0, 0, 0);
                }

                $_SESSION['infos'][] = _('Riga rimossa!');
            }
        }
        break;

    case 'add_serial':
        $idgruppo = $post['idgruppo'];
        $serial = $post['serial'];

        $q = 'SELECT * FROM co_righe_documenti WHERE iddocumento='.prepare($id_record).' AND idgruppo='.prepare($idgruppo).' ORDER BY id';
        $rs = $dbo->fetchArray($q);

        foreach ($rs as $i => $r) {
            $dbo->query('UPDATE co_righe_documenti SET serial='.prepare($serial[$i]).' WHERE id='.prepare($r['id']));
        }

        break;

    case 'update_position':
        $start = filter('start');
        $end = filter('end');
        $id = filter('id');

        if ($start > $end) {
            $dbo->query('UPDATE `co_righe_documenti` SET `order`=`order` + 1 WHERE `order`>='.prepare($end).' AND `order`<'.prepare($start).' AND `iddocumento`='.prepare($id_record));
            $dbo->query('UPDATE `co_righe_documenti` SET `order`='.prepare($end).' WHERE id='.prepare($id));
        } elseif ($end != $start) {
            $dbo->query('UPDATE `co_righe_documenti` SET `order`=`order` - 1 WHERE `order`>'.prepare($start).' AND `order`<='.prepare($end).' AND `iddocumento`='.prepare($id_record));
            $dbo->query('UPDATE `co_righe_documenti` SET `order`='.prepare($end).' WHERE id='.prepare($id));
        }

        break;
}

if (post('op') !== null) {
    $rs_sconto = $dbo->fetchArray('SELECT sconto_globale, tipo_sconto_globale FROM co_documenti WHERE id='.prepare($id_record));

    // Aggiorno l'eventuale sconto gestendolo con le righe in fattura
    if ($rs_sconto[0]['tipo_sconto_globale'] == 'PRC' && !empty($rs_sconto[0]['sconto_globale'])) {
        // Se lo sconto c'è già lo elimino e lo ricalcolo
        $dbo->query("DELETE FROM co_righe_documenti WHERE descrizione LIKE '%SCONTO %' AND iddocumento=".prepare($id_record));

        $subtotale = get_imponibile_fattura($id_record);
        $subtotale = -$subtotale / 100 * $rs_sconto[0]['sconto_globale'];

        // Calcolo anche l'iva da scontare
        $rsi = $dbo->fetchArray('SELECT descrizione, percentuale FROM co_iva WHERE id='.prepare(get_var('Iva predefinita')));
        $iva = $subtotale / 100 * $rsi[0]['percentuale'];

        $descrizione = 'SCONTO '.Translator::numberToLocale($rs_sconto[0]['sconto_globale']).'%';

        $dbo->query('INSERT INTO co_righe_documenti(iddocumento, descrizione, idiva, desc_iva, iva, subtotale, sconto, qta, idgruppo, `order`) VALUES( '.prepare($id_record).', '.prepare($descrizione).', '.prepare($idiva).', '.prepare($rsi[0]['descrizione']).', '.prepare($iva).', '.prepare($subtotale).', 0, 1, (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM co_righe_documenti AS t WHERE iddocumento='.prepare($id_record).'))');
    }
}
