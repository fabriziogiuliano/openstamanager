<?php

include_once __DIR__.'/../../core.php';

// Necessaria per la funzione add_movimento_magazzino
include_once $docroot.'/modules/articoli/modutil.php';

$module = Modules::getModule($id_module);

if ($module['name'] == 'Ddt di vendita') {
    $dir = 'entrata';
} else {
    $dir = 'uscita';
}

switch (post('op')) {
    case 'add':
        $idanagrafica = post('idanagrafica');
        $data = $post['data'];
        $dir = $post['dir'];
        $idtipoddt = post('idtipoddt');

        if (isset($post['idanagrafica'])) {
            $numero = get_new_numeroddt($data);
            $numero_esterno = ($dir == 'entrata') ? get_new_numerosecondarioddt($data) : '';

            $campo = ($dir == 'entrata') ? 'idpagamento_vendite' : 'idpagamento_acquisti';

            // Tipo di pagamento predefinito dall'anagrafica
            $query = 'SELECT id FROM co_pagamenti WHERE id=(SELECT '.$campo.' AS pagamento FROM an_anagrafiche WHERE idanagrafica='.prepare($idanagrafica).')';
            $rs = $dbo->fetchArray($query);
            $idpagamento = $rs[0]['id'];

            // Se il ddt è un ddt cliente e non è stato associato un pagamento predefinito al cliente leggo il pagamento dalle impostazioni
            if ($dir == 'entrata' && $idpagamento == '') {
                $idpagamento = get_var('Tipo di pagamento predefinito');
            }

            $query = 'INSERT INTO dt_ddt(numero, numero_esterno, idanagrafica, idtipoddt, idpagamento, data, idstatoddt) VALUES ('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($idanagrafica).', '.prepare($idtipoddt).', '.prepare($idpagamento).', '.prepare($data).", (SELECT `id` FROM `dt_statiddt` WHERE `descrizione`='Bozza'))";
            $dbo->query($query);

            $id_record = $dbo->lastInsertedID();

            $_SESSION['infos'][] = str_replace(['_TYPE_', '_NUM_'], [$dir, $numero], _('Aggiunto DDT in _TYPE_ numero _NUM_!'));
        }
        break;

    case 'update':
        if (!empty($id_record)) {
            $numero_esterno = post('numero_esterno');
            $data = post('data');
            $idanagrafica = post('idanagrafica');
            $note = post('note');
            $idstatoddt = post('idstatoddt');
            $idstatoddt = post('idstatoddt');
            $idcausalet = post('idcausalet');
            $idspedizione = post('idspedizione');
            $idporto = post('idporto');
            $idvettore = post('idvettore');
            $idaspettobeni = post('idaspettobeni');
            $idpagamento = post('idpagamento');
            $idconto = post('idconto');
            $idanagrafica = post('idanagrafica');
            $idsede = post('idsede');
            $totale_imponibile = get_imponibile_ddt($id_record);
            $n_colli = post('n_colli');
            $totale_ddt = get_totale_ddt($id_record);

            if ($dir == 'uscita') {
                $idrivalsainps = post('idrivalsainps');
                $idritenutaacconto = post('idritenutaacconto');
                $bollo = post('bollo');
            } else {
                $idrivalsainps = 0;
                $idritenutaacconto = 0;
                $bollo = 0;
            }

            $tipo_sconto = $post['tipo_sconto_generico'];
            $sconto = $post['sconto_generico'];

            // Leggo la descrizione del pagamento
            $query = 'SELECT descrizione FROM co_pagamenti WHERE id='.prepare($idpagamento);
            $rs = $dbo->fetchArray($query);
            $pagamento = $rs[0]['descrizione'];

            // Query di aggiornamento
            $query = 'UPDATE dt_ddt SET idstatoddt='.prepare($idstatoddt).','.
                ' data='.prepare($data).','.
                ' idpagamento='.prepare($idpagamento).','.
                ' numero_esterno='.prepare($numero_esterno).','.
                ' note='.prepare($note).','.
                ' idconto='.prepare($idconto).','.
                ' idanagrafica='.prepare($idanagrafica).','.
                ' idsede='.prepare($idsede).','.
                ' idcausalet='.prepare($idcausalet).','.
                ' idspedizione='.prepare($idspedizione).','.
                ' idporto='.prepare($idporto).','.
                ' idvettore='.prepare($idvettore).','.
                ' idaspettobeni='.prepare($idaspettobeni).','.
                ' idrivalsainps='.prepare($idrivalsainps).','.
                ' idritenutaacconto='.prepare($idritenutaacconto).','.
                ' tipo_sconto_globale='.prepare($tipo_sconto).','.
                ' sconto_globale='.prepare($sconto).','.
                ' bollo=0, rivalsainps=0, ritenutaacconto=0, n_colli='.prepare($n_colli).' WHERE id='.prepare($id_record);

            if ($dbo->query($query)) {
                $dbo->query("DELETE FROM dt_righe_ddt WHERE descrizione LIKE '%SCONTO%' AND idddt=".prepare($id_record));

                // Sconto unitario, quello percentuale viene gestito a fondo pagina
                if ($tipo_sconto == 'UNT' && $sconto > 0) {
                    $subtotale = -$sconto;

                    // Calcolo anche l'iva da scontare
                    $rsi = $dbo->fetchArray('SELECT descrizione, percentuale FROM co_iva WHERE id='.prepare(get_var('Iva predefinita')));
                    $iva = $subtotale / 100 * $rsi[0]['percentuale'];

                    $dbo->query('INSERT INTO dt_righe_ddt(idddt, descrizione, idiva, desc_iva, iva, subtotale, sconto, qta, idgruppo, `order`) VALUES( '.prepare($id_record).", 'SCONTO', ".prepare($idiva).', '.prepare($rsi[0]['descrizione']).', '.prepare($iva).', '.prepare($subtotale).', 0, 1, (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'))');
                }

                $query = 'SELECT descrizione FROM dt_statiddt WHERE id='.prepare($idstatoddt);
                $rs = $dbo->fetchArray($query);

                // Ricalcolo inps, ritenuta e bollo (se l'ddt non è stato evaso)
                if ($dir == 'entrata') {
                    if ($rs[0]['descrizione'] != 'Pagato') {
                        ricalcola_costiagg_ddt($id_record);
                    }
                } else {
                    if ($rs[0]['descrizione'] != 'Pagato') {
                        ricalcola_costiagg_ddt($id_record, $idrivalsainps, $idritenutaacconto, $bollo);
                    }
                }

                $_SESSION['infos'][] = _('Ddt modificato correttamente!');
            }
        }
        break;

    case 'addarticolo':
        if (isset($post['idarticolo'])) {
            $dir = post('dir');

            $idarticolo = post('idarticolo');
            $descrizione = post('descrizione');
            $idiva = post('idiva');

            $qta = $post['qta'];
            $prezzo = $post['prezzo'];

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            // Calcolo idgruppo per questo inserimento
            $ridgruppo = $dbo->fetchArray('SELECT IFNULL(MAX(idgruppo) + 1, 0) AS idgruppo FROM dt_righe_ddt WHERE idddt = '.prepare($id_record));
            $idgruppo = $ridgruppo[0]['idgruppo'];

            add_articolo_inddt($id_record, $idarticolo, $descrizione, $idiva, $qta, $prezzo * $qta, $sconto, $sconto_unitario, $tipo_sconto, $lotto, $serial, $altro, $idgruppo);

            // Ricalcolo inps, ritenuta e bollo
            ricalcola_costiagg_ddt($id_record);

            $_SESSION['infos'][] = _('Articolo aggiunto!');
        }
        break;

    case 'addriga':
        // Selezione costi da intervento
        $descrizione = post('descrizione');
        $idiva = post('idiva');
        $um = post('um');

        $prezzo = $post['prezzo'];
        $qta = $post['qta'];

        // Calcolo dello sconto
        $sconto_unitario = $post['sconto'];
        $tipo_sconto = $post['tipo_sconto'];
        $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
        $sconto = $sconto * $qta;

        $subtot = $prezzo * $qta;

        // Calcolo iva
        $query = 'SELECT descrizione, percentuale, indetraibile FROM co_iva WHERE id='.prepare($idiva);
        $rs = $dbo->fetchArray($query);
        $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
        $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

        $query = 'INSERT INTO dt_righe_ddt(idddt, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idiva).', '.prepare($rs[0]['descrizione']).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($sconto_unitario).', '.prepare($tipo_sconto).', '.prepare($um).', '.prepare($qta).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'))';

        if ($dbo->query($query)) {
            $_SESSION['infos'][] = _('Riga aggiunta!');

            // Ricalcolo inps, ritenuta e bollo
            if ($dir == 'entrata') {
                ricalcola_costiagg_ddt($id_record);
            } else {
                ricalcola_costiagg_ddt($id_record);
            }
        }
        break;

    // Creazione ddt da ordine
    case 'ddt_da_ordine':
        $totale_ordine = 0.00;
        $data = $post['data'];
        $idanagrafica = post('idanagrafica');
        $idpagamento = post('idpagamento');
        $idconto = post('idconto');
        $idordine = post('idordine');
        $numero = get_new_numeroddt($data);

        if ($dir == 'entrata') {
            $numero_esterno = get_new_numerosecondarioddt($data);
        } else {
            $numero_esterno = '';
        }

        // Creazione nuovo ddt
        $dbo->query('INSERT INTO dt_ddt( numero, numero_esterno, data, idanagrafica, idtipoddt, idstatoddt, idpagamento, idconto) VALUES('.prepare($numero).', '.prepare($numero_esterno).', '.prepare($data).', '.prepare($idanagrafica).', (SELECT id FROM dt_tipiddt WHERE dir='.prepare($dir)."), (SELECT id FROM dt_statiddt WHERE descrizione='Bozza'), ".prepare($idpagamento).', '.prepare($idconto).')');
        $id_record = $dbo->lastInsertedID();

        // Lettura di tutte le righe della tabella in arrivo
        for ($i = 0; $i < sizeof($post['qta_da_evadere']); ++$i) {
            // Processo solo le righe da evadere
            if ($post['evadere'][$i] == 'on') {
                $idrigaordine = post('idrigaordine')[$i];
                $idarticolo = post('idarticolo')[$i];
                $descrizione = post('descrizione')[$i];

                $qta = $post['qta_da_evadere'][$i];
                $um = post('um')[$i];

                $subtot = $post['subtot'][$i] * $qta;
                $sconto = $post['sconto'][$i];
                $sconto = $sconto * $qta;

                $idiva = post('idiva')[$i];
                $iva = $post['iva'][$i] * $qta;

                // Calcolo l'iva indetraibile
                $q = 'SELECT descrizione, indetraibile FROM co_iva WHERE id='.prepare($idiva);
                $rs = $dbo->fetchArray($q);
                $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];

                // Lettura lotto, serial, altro dalla riga dell'ordine
                $q = 'SELECT lotto, serial, altro FROM or_righe_ordini WHERE id='.prepare($idrigaordine);
                $rs = $dbo->fetchArray($q);

                // Inserisco la riga in ddt
                $dbo->query('INSERT INTO dt_righe_ddt(idddt, idordine, idarticolo, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, um, qta, lotto, serial, altro, idgruppo, `order`) VALUES('.prepare($id_record).', '.prepare($idordine).', '.prepare($idarticolo).', '.prepare($idiva).', '.prepare($rs[0]['descrizione']).', '.prepare($iva).', '.prepare($iva_indetraibile).', '.prepare($descrizione).', '.prepare($subtot).', '.prepare($sconto).', '.prepare($um).', '.prepare($qta).', '.prepare($rs[0]['lotto']).', '.prepare($rs[0]['serial']).', '.prepare($rs[0]['altro']).', (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'))');

                // Scalo la quantità dall'ordine
                $dbo->query('UPDATE or_righe_ordini SET qta_evasa = qta_evasa+'.$qta.' WHERE id='.prepare($idrigaordine));

                // Movimento il magazzino
                // vendita
                if (!empty($idarticolo)) {
                    if ($dir == 'entrata') {
                        add_movimento_magazzino($idarticolo, -$qta, ['idddt' => $id_record]);
                    }

                    // acquisto
                    else {
                        add_movimento_magazzino($idarticolo, $qta, ['idddt' => $id_record]);
                    }
                }
            }
        }

        ricalcola_costiagg_ddt($id_record);
            $_SESSION['infos'][] = _('Creato un nuovo ddt!');
        break;

    // Scollegamento articolo da ddt
    case 'unlink_articolo':
        $idriga = post('idriga');
        $idarticolo = post('idarticolo');

        if ($id_record != '' && $idarticolo != '') {
            $res = rimuovi_articolo_daddt($idarticolo, $id_record, $idriga);

            if (!$res) {
                $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                return;
            }

            // Ricalcolo inps, ritenuta e bollo
            if ($post['dir'] == 'entrata') {
                ricalcola_costiagg_ddt($id_record);
            } else {
                ricalcola_costiagg_ddt($id_record, 0, 0, 0);
            }

            $_SESSION['infos'][] = _('Articolo rimosso!');
        }
        break;

    // Scollegamento riga generica da ddt
    case 'unlink_riga':
        $idriga = post('idriga');

        if ($id_record != '' && $idriga != '') {
            // Se la riga è stata creata da un ordine, devo riportare la quantità evasa nella tabella degli ordini
            // al valore di prima, riaggiungendo la quantità che sto togliendo
            $rs = $dbo->fetchArray('SELECT qta, descrizione, idarticolo, idordine, idiva FROM dt_righe_ddt WHERE idddt='.prepare($id_record).' AND id='.prepare($idriga));

            // Rimpiazzo la quantità negli ordini
            $dbo->query('UPDATE or_righe_ordini SET qta_evasa=qta_evasa-'.$rs[0]['qta'].' WHERE descrizione='.prepare($rs[0]['descrizione']).' AND idarticolo='.prepare($rs[0]['idarticolo']).' AND idordine='.prepare($rs[0]['idordine']).' AND idiva='.prepare($rs[0]['idiva']));

            // Eliminazione delle righe dal ddt
            $query = 'DELETE FROM dt_righe_ddt WHERE idddt='.prepare($id_record).' AND id='.prepare($idriga);

            if ($dbo->query($query)) {
                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_ddt($id_record);
                } else {
                    ricalcola_costiagg_ddt($id_record, 0, 0, 0);
                }

                $_SESSION['infos'][] = _('Riga rimossa!');
            }
        }
        break;

    // Modifica riga
    case 'editriga':
        if (isset($post['idriga'])) {
            // Selezione costi da intervento
            $idriga = post('idriga');
            $descrizione = post('descrizione');

            $prezzo = $post['prezzo'];
            $qta = $post['qta'];

            // Calcolo dello sconto
            $sconto_unitario = $post['sconto'];
            $tipo_sconto = $post['tipo_sconto'];
            $sconto = ($tipo_sconto == 'PRC') ? ($prezzo * $sconto_unitario) / 100 : $sconto_unitario;
            $sconto = $sconto * $qta;

            $idiva = post('idiva');
            $um = post('um');

            $subtot = $prezzo * $qta;

            // Lettura idarticolo dalla riga ddt
            $rs = $dbo->fetchArray('SELECT idgruppo, idddt, abilita_serial, idarticolo, idordine, qta FROM dt_righe_ddt WHERE id='.prepare($idriga));
            $idarticolo = $rs[0]['idarticolo'];
            $idordine = $rs[0]['idordine'];
            $old_qta = $rs[0]['qta'];
            $idgruppo = $rs[0]['idgruppo'];
            $idddt = $rs[0]['idddt'];
            $abilita_serial = $rs[0]['abilita_serial'];

            // Calcolo iva
            $query = 'SELECT * FROM co_iva WHERE id='.prepare($idiva);
            $rs = $dbo->fetchArray($query);
            $iva = ($subtot - $sconto) / 100 * $rs[0]['percentuale'];
            $iva_indetraibile = $iva / 100 * $rs[0]['indetraibile'];
            $desc_iva = $rs[0]['descrizione'];

            // Modifica riga generica sul ddt
            $query = 'UPDATE dt_righe_ddt SET idiva='.prepare($idiva).', desc_iva='.prepare($desc_iva).', iva='.prepare($iva).', iva_indetraibile='.prepare($iva_indetraibile).', descrizione='.prepare($descrizione).', subtotale='.prepare($subtot).', sconto='.prepare($sconto).', sconto_unitario='.prepare($sconto_unitario).', tipo_sconto='.prepare($tipo_sconto).', um='.prepare($um).' WHERE idgruppo='.prepare($idgruppo).' AND idddt='.prepare($idddt);
            if ($dbo->query($query)) {
                // Modifica della quantità
                $dbo->query('UPDATE dt_righe_ddt SET qta='.prepare($qta).' WHERE idgruppo='.prepare($idgruppo));

                // Modifica per gestire i serial
                if (!empty($idarticolo)) {
                    $new_qta = $qta - $old_qta;
                    $new_qta = ($old_qta < $qta) ? $new_qta : -$new_qta;

                    if (!empty($abilita_serial)) {
                        if ($old_qta < $qta) {
                            for ($i = 0; $i < $new_qta; ++$i) {
                                $dbo->query('INSERT INTO dt_righe_ddt(idddt, idarticolo, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idgruppo, `order`) SELECT idddt, idarticolo, idiva, desc_iva, iva, iva_indetraibile, descrizione, subtotale, sconto, sconto_unitario, tipo_sconto, um, qta, idgruppo, `order` FROM dt_righe_ddt WHERE id='.prepare($idriga));
                            }
                        } else {
                            if ($dir == 'uscita') {
                                if ($new_qta > $dbo->fetchArray("SELECT COUNT(*) AS rimovibili FROM dt_righe_ddt WHERE serial NOT IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') AND idgruppo=".prepare($idgruppo).' AND idddt='.prepare($idddt))[0]['rimovibili']) {
                                    $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                                    return;
                                } else {
                                    $deletes = $dbo->fetchArray('SELECT id FROM dt_righe_ddt AS t WHERE idgruppo = '.prepare($idgruppo).' AND idddt='.prepare($idddt)." AND serial NOT IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') ORDER BY serial ASC LIMIT ".$new_qta);
                                }
                            } else {
                                $deletes = $dbo->fetchArray('SELECT id FROM dt_righe_ddt AS t WHERE idgruppo = '.prepare($idgruppo).' AND idddt='.prepare($idddt).' ORDER BY serial ASC LIMIT '.$new_qta);
                            }

                            foreach ((array) $deletes as $delete) {
                                $dbo->query('DELETE FROM dt_righe_ddt WHERE id = '.prepare($delete['id']));
                            }
                        }
                    }

                    // Controlli aggiuntivi sulle quantità evase degli ordini
                    if (!empty($idordine) && $qta > 0) {
                        $rs = $dbo->fetchArray('SELECT qta_evasa, qta FROM or_righe_ordini WHERE idordine='.prepare($idordine).' AND idarticolo='.prepare($idarticolo));

                        $qta_ordine = $qta;
                        if ($qta > $rs[0]['qta_evasa']) {
                            $qta_ordine = ($qta > $rs[0]['qta']) ? $rs[0]['qta'] : $qta;
                        }

                        $dbo->query('UPDATE or_righe_ordini SET qta_evasa = '.prepare($qta_ordine).' WHERE idordine='.prepare($idordine).' AND idarticolo='.prepare($idarticolo));
                    }

                    $new_qta = ($old_qta < $qta) ? $new_qta : -$new_qta;

                    $new_qta = ($dir == 'entrata') ? -$new_qta : $new_qta;
                    add_movimento_magazzino($idarticolo, $new_qta, ['idddt' => $id_record]);
                }

                $_SESSION['infos'][] = _('Riga modificata!');

                // Ricalcolo inps, ritenuta e bollo
                if ($dir == 'entrata') {
                    ricalcola_costiagg_ddt($id_record);
                } else {
                    ricalcola_costiagg_ddt($id_record);
                }
            }
        }
        break;

    // eliminazione ddt
    case 'delete':
        if ($dir == 'uscita') {
            $non_rimovibili = $dbo->fetchArray("SELECT COUNT(*) AS non_rimovibili FROM dt_righe_ddt WHERE serial IN (SELECT serial FROM vw_serials WHERE dir = 'entrata') AND idddt=".prepare($id_record))[0]['non_rimovibili'];
            if ($non_rimovibili != 0) {
                $_SESSION['errors'][] = _('Alcuni serial number sono già stati utilizzati!');

                return;
            }
        }

        // Se ci sono degli articoli collegati
        $query = 'SELECT id, idarticolo FROM dt_righe_ddt WHERE idddt='.prepare($id_record);
        $rs = $dbo->fetchArray($query);

        for ($i = 0; $i < sizeof($rs); ++$i) {
            rimuovi_articolo_daddt($rs[$i]['idarticolo'], $id_record, $rs[$i]['id']);
        }

        // Se delle righe sono state create da un ordine, devo riportare la quantità evasa nella tabella degli ordini
        // al valore di prima, riaggiungendo la quantità che sto togliendo
        $rs = $dbo->fetchArray('SELECT qta, descrizione, idarticolo, idordine, idiva FROM dt_righe_ddt WHERE idddt='.prepare($id_record));

        // Rimpiazzo la quantità negli ordini
        for ($i = 0; $i < sizeof($rs); ++$i) {
            $dbo->query('UPDATE or_righe_ordini SET qta_evasa=qta_evasa-'.$rs[$i]['qta'].' WHERE descrizione='.prepare($rs[$i]['descrizione']).' AND idarticolo='.prepare($rs[$i]['idarticolo']).' AND idordine='.prepare($rs[$i]['idordine']).' AND idiva='.prepare($rs[$i]['idiva']));
        }

        $dbo->query('DELETE FROM dt_ddt WHERE id='.prepare($id_record));
        $dbo->query('DELETE FROM dt_righe_ddt WHERE idddt='.prepare($id_record));
        $dbo->query('DELETE FROM mg_movimenti WHERE idddt='.prepare($id_record));

        $_SESSION['infos'][] = _('Ddt eliminato!');

        break;

    case 'add_serial':
        $idgruppo = $post['idgruppo'];
        $serial = $post['serial'];

        $q = 'SELECT * FROM dt_righe_ddt WHERE idddt='.prepare($id_record).' AND idgruppo='.prepare($idgruppo).' ORDER BY id';
        $rs = $dbo->fetchArray($q);

        foreach ($rs as $i => $r) {
            $dbo->query('UPDATE dt_righe_ddt SET serial='.prepare($serial[$i]).' WHERE id='.prepare($r['id']));
        }

        break;

    case 'update_position':
        $start = filter('start');
        $end = filter('end');
        $id = filter('id');

        if ($start > $end) {
            $dbo->query('UPDATE `dt_righe_ddt` SET `order`=`order` + 1 WHERE `order`>='.prepare($end).' AND `order`<'.prepare($start).' AND `idddt`='.prepare($id_record));
            $dbo->query('UPDATE `dt_righe_ddt` SET `order`='.prepare($end).' WHERE id='.prepare($id));
        } elseif ($end != $start) {
            $dbo->query('UPDATE `dt_righe_ddt` SET `order`=`order` - 1 WHERE `order`>'.prepare($start).' AND `order`<='.prepare($end).' AND `idddt`='.prepare($id_record));
            $dbo->query('UPDATE `dt_righe_ddt` SET `order`='.prepare($end).' WHERE id='.prepare($id));
        }

        break;
}

if (post('op') !== null) {
    $rs_sconto = $dbo->fetchArray('SELECT sconto_globale, tipo_sconto_globale FROM dt_ddt WHERE id='.prepare($id_record));

    // Aggiorno l'eventuale sconto gestendolo con le righe in fattura
    if ($rs_sconto[0]['tipo_sconto_globale'] == 'PRC' && !empty($rs_sconto[0]['sconto_globale'])) {
        // Se lo sconto c'è già lo elimino e lo ricalcolo
        $dbo->query("DELETE FROM dt_righe_ddt WHERE descrizione LIKE '%SCONTO %' AND idddt=".prepare($id_record));

        $subtotale = get_imponibile_ddt($id_record);
        $subtotale = -$subtotale / 100 * $rs_sconto[0]['sconto_globale'];

        // Calcolo anche l'iva da scontare
        $rsi = $dbo->fetchArray('SELECT descrizione, percentuale FROM co_iva WHERE id='.prepare(get_var('Iva predefinita')));
        $iva = $subtotale / 100 * $rsi[0]['percentuale'];

        $descrizione = 'SCONTO '.Translator::numberToLocale($rs_sconto[0]['sconto_globale']).'%';

        $dbo->query('INSERT INTO dt_righe_ddt(idddt, descrizione, idiva, desc_iva, iva, subtotale, sconto, qta, digruppo, `order`) VALUES( '.prepare($id_record).', '.prepare($descrizione).', '.prepare($idiva).', '.prepare($rsi[0]['descrizione']).', '.prepare($iva).', '.prepare($subtotale).', 0, 1, (SELECT IFNULL(MAX(`idgruppo`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'), (SELECT IFNULL(MAX(`order`) + 1, 0) FROM dt_righe_ddt AS t WHERE idddt='.prepare($id_record).'))');
    }
}
