<?php

include_once __DIR__.'/../../core.php';

$upload_dir = $docroot.'/files/'.Modules::getModule('Articoli')['directory'];

switch (post('op')) {
    case 'update':
        $codice = post('codice');
        $descrizione = post('descrizione');
        $um = post('um');
        $categoria = post('categoria');
        $subcategoria = post('subcategoria');
        $qta = post('qta');
        $threshold_qta = post('threshold_qta');

        $abilita_serial = post('abilita_serial');

        $prezzo_vendita = post('prezzo_vendita');
        $prezzo_acquisto = post('prezzo_acquisto');

        $idiva_vendita = post('idiva_vendita');
        $gg_garanzia = post('gg_garanzia');
        $componente_filename = post('componente_filename');

        $attivo = post('attivo');

        $note = post('note');

        $query = 'UPDATE mg_articoli SET '.
            ' codice='.prepare($codice).','.
            ' descrizione='.prepare($descrizione).','.
            ' um='.prepare($um).','.
            ' id_categoria='.prepare($categoria).','.
            ' id_sottocategoria='.prepare($subcategoria).','.
            ' abilita_serial='.prepare($abilita_serial).','.
            ' threshold_qta='.prepare($threshold_qta).','.
            ' prezzo_vendita='.prepare($prezzo_vendita).','.
            ' prezzo_acquisto='.prepare($prezzo_acquisto).','.
            ' idiva_vendita='.prepare($idiva_vendita).','.
            ' gg_garanzia='.prepare($gg_garanzia).','.
            ' componente_filename='.prepare($componente_filename).','.
            ' attivo='.prepare($attivo).', '.
            ' note='.prepare($note).
            ' WHERE id='.prepare($id_record);
        $dbo->query($query);

        // Leggo la quantità attuale per capire se l'ho modificata
        $rs = $dbo->fetchArray('SELECT qta FROM mg_articoli WHERE id='.prepare($id_record));
        $old_qta = $rs[0]['qta'];
        $movimento = $qta - $old_qta;

        if ($movimento != 0) {
            add_movimento_magazzino($id_record, $movimento);
        }

        /*
            Salvataggio info componente (campo `contenuto`)
        */
        $componente = post('componente_filename');
        if (!empty($componente)) {
            $contenuto = \Util\Ini::write(file_get_contents($docroot.'/files/my_impianti/'.$componente), $post);

            $dbo->query('UPDATE mg_articoli SET contenuto='.prepare($contenuto).' WHERE id='.prepare($id_record));
        }

        // Upload file
        if (!empty($_FILES) && !empty($_FILES['immagine01']['name'])) {
            $tmp = $_FILES['immagine01']['tmp_name'];

            $filename = basename($_FILES['immagine01']['name']);
            $filename = unique_filename($filename, $upload_dir);

            if (create_thumbnails($tmp, $filename, $upload_dir)) {
                $dbo->query('UPDATE mg_articoli SET immagine01='.prepare($filename).' WHERE id='.prepare($id_record));
            } else {
                $_SESSION['warnings'][] = str_replace('_DIR_', $upload_dir, _('Errore durante il caricamento del file in _DIR_!'));
            }
        }

        // Eliminazione file
        if (post('delete_immagine01') !== null) {
            $filename = post('immagine01');
            $f = pathinfo($filename);

            unlink($upload_dir.'/'.$f['filename'].'.'.$f['extension']);
            unlink($upload_dir.'/'.$f['filename'].'_thumb100.'.$f['extension']);
            unlink($upload_dir.'/'.$f['filename'].'_thumb250.'.$f['extension']);

            $dbo->query("UPDATE mg_articoli SET immagine01 = '' WHERE id=".prepare($id_record));
        }

        $_SESSION['infos'][] = _('Informazioni salvate correttamente!');
        break;

    // Aggiunta articolo
    case 'add':
        $codice = post('codice');
        $descrizione = post('descrizione');
        $categoria = post('categoria');
        $subcategoria = post('subcategoria');

        // Inserisco l'articolo solo se non esiste un altro articolo con stesso codice
        if ($dbo->fetchNum('SELECT * FROM mg_articoli WHERE codice='.prepare($codice)) == 0) {
            $query = 'INSERT INTO mg_articoli(codice, descrizione, id_categoria, id_sottocategoria, attivo) VALUES ('.prepare($codice).', '.prepare($descrizione).', '.prepare($categoria).', '.prepare($subcategoria).', 1)';
            $dbo->query($query);
            $_SESSION['infos'][] = _('Aggiunto un nuovo articolo!');

            $query = 'SELECT * FROM mg_articoli WHERE codice='.prepare($codice);
            $rs = $dbo->fetchArray($query);
            $id_record = $rs[0]['id'];
        } else {
            $_SESSION['errors'][] = _('Esiste già un articolo con questo codice!');
        }
        break;

    // Aggiunta prodotto
    case 'addprodotto':
        // Per i 3 campi (lotto, serial, altro) leggo i numeri di partenza e arrivo e creo le combinazioni scelte

        // Lotto
        $lotto__start = post('lotto_start');
        $lotto__end = post('lotto_end');
        preg_match("/(.*?)([\d]*$)/", $lotto__start, $m);
        $lotto_start = intval($m[2]);
        preg_match("/(.*?)([\d]*$)/", $lotto__end, $m);
        $lotto_end = intval($m[2]);
        $n_lotti = abs($lotto_end - $lotto_start) + 1;
        $lotto_prefix = str_replace($lotto_end, '', $lotto__end);
        $lotto_pad_length = strlen($lotto__end) - strlen($lotto_prefix);

        // Serial
        $serial__start = post('serial_start');
        $serial__end = post('serial_end');
        preg_match("/(.*?)([\d]*$)/", $serial__start, $m);
        $serial_start = intval($m[2]);
        preg_match("/(.*?)([\d]*$)/", $serial__end, $m);
        $serial_end = intval($m[2]);
        $n_serial = abs($serial_end - $serial_start) + 1;
        $serial_prefix = str_replace($serial_end, '', $serial__end);
        $serial_pad_length = strlen($serial__end) - strlen($serial_prefix);

        // Altro
        $altro__start = post('altro_start');
        $altro__end = post('altro_end');
        preg_match("/(.*?)([\d]*$)/", $altro__start, $m);
        $altro_start = intval($m[2]);
        preg_match("/(.*?)([\d]*$)/", $altro__end, $m);
        $altro_end = intval($m[2]);
        $n_altro = abs($altro_end - $altro_start) + 1;
        $altro_prefix = str_replace($altro_end, '', $altro__end);
        $altro_pad_length = strlen($altro__end) - strlen($altro_prefix);

        $n_prodotti = $n_lotti * $n_serial * $n_altro;

        // Creo la query per le combinazioni prodotto con ogni combinazione
        $query = 'INSERT INTO mg_prodotti(idarticolo, lotto, serial, altro, data) VALUES';

        // Contatore prodotti da inserire
        $c = 0;

        // Combinazione con "Lotto"
        for ($l = 0; $l < $n_lotti; ++$l) {
            // Combinazione con "Serial"
            for ($s = 0; $s < $n_serial; ++$s) {
                // Combinazione con "Altro"
                for ($a = 0; $a < $n_altro; ++$a) {
                    $insert = '('.prepare($id_record).', |lotto|, |serial|, |altro|, NOW())';

                    $this_lotto = ($lotto__start != '') ? $lotto_prefix.(str_pad($lotto_start + $l, $lotto_pad_length, '0', STR_PAD_LEFT)) : '';
                    $insert = str_replace('|lotto|', prepare($this_lotto), $insert);

                    $this_serial = ($serial__start != '') ? $serial_prefix.(str_pad($serial_start + $s, $serial_pad_length, '0', STR_PAD_LEFT)) : '';
                    $insert = str_replace('|serial|', prepare($this_serial), $insert);

                    $this_altro = ($altro__start != '') ? $altro_prefix.(str_pad($altro_start + $a, $altro_pad_length, '0', STR_PAD_LEFT)) : '';
                    $insert = str_replace('|altro|', prepare($this_altro), $insert);

                    // Verifico che questa combinazione non esista già
                    $np = $dbo->fetchNum('SELECT id FROM mg_prodotti WHERE idarticolo='.prepare($id_record).' AND lotto='.prepare($this_lotto).' AND serial='.prepare($this_serial).' AND altro='.prepare($this_altro));
                    if ($np == 0) {
                        $query .= $insert.', ';
                        ++$c;
                    }
                }
            }
        }
        $query .= '.';

        // Rimuovo "), ."
        $query = str_replace('), .', ')', $query);

        // Eseguo l'inserimento!!!
        if ($c > 0) {
            if ($dbo->query($query)) {
                // Movimento il magazzino se l'ho specificato nelle impostazioni
                if (get_var("Movimenta il magazzino durante l'inserimento o eliminazione dei lotti/serial number")) {
                    add_movimento_magazzino($id_record, $n_prodotti, [], str_replace(['_SERIAL_INIZIO_', '_SERIAL_FINE_'], [$serial__start, $serial__end], _('Carico magazzino con serial da _SERIAL_INIZIO_ a _SERIAL_FINE_')));
                }

                $_SESSION['infos'][] = str_replace('_NUM_', $n_prodotti, _('Aggiunti _NUM_ prodotti!'));
            } else {
                $_SESSION['errors'][] = _("Errore durante l'inserimento!");
            }
        }
        break;

    case 'delprodotto':
        $idprodotto = post('idprodotto');

        // Leggo info prodotto per descrizione mg_movimenti
        $rs = $dbo->fetchArray('SELECT lotto, serial, altro FROM mg_prodotti WHERE id='.prepare($idprodotto));

        $query = 'DELETE FROM mg_prodotti WHERE id='.prepare($idprodotto);
        if ($dbo->query($query)) {
            // Movimento il magazzino se l'ho specificato nelle impostazioni
            if (get_var("Movimenta il magazzino durante l'inserimento o eliminazione dei lotti/serial number")) {
                add_movimento_magazzino($id_record, -1, [], str_replace(['_LOTTO_', '_SERIAL_', '_ALTRO_'], [$rs[0]['lotto'], $rs[0]['serial'], $rs[0]['altro']], _('Eliminazione dal magazzino del prodotto con serial _SERIAL_')));
            }

            $_SESSION['infos'][] = _('Prodotto rimosso!');
        }
        break;

    case 'delete':
        $dbo->query('DELETE FROM mg_articoli WHERE id='.prepare($id_record));
        $dbo->query('DELETE FROM mg_movimenti WHERE idarticolo='.prepare($id_record));
        $dbo->query('DELETE FROM mg_prodotti WHERE idarticolo='.prepare($id_record));
        $dbo->query('DELETE FROM mg_articoli_automezzi WHERE idarticolo='.prepare($id_record));

        $_SESSION['infos'][] = _('Articolo eliminato!');
        break;
}
