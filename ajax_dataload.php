<?php

include_once __DIR__.'/core.php';

// Informazioni fondamentali
$start = filter('start');
$length = filter('length');
$columns = filter('columns');
$order = filter('order')[0];

$order['column'] = $order['column'] - 1;
array_shift($columns);

// Lettura parametri iniziali
if (!empty($id_plugin)) {
    $total = Plugins::getQuery($id_plugin);

    $total['query'] = Modules::replacePlaceholder($total['query'], $id_parent);
} else {
    $total = Modules::getQuery($id_module);
}

// Lettura parametri modulo
$module_query = $total['query'];
$module_query = str_replace('|period_start|', $_SESSION['period_start'], $module_query);
$module_query = str_replace('|period_end|', $_SESSION['period_end'], $module_query);
$module_query = str_replace('|select|', $total['select'], $module_query);

// Predisposizione dela risposta
$results = [];
$results['data'] = [];
$results['recordsTotal'] = 0;
$results['recordsFiltered'] = 0;
$results['summable'] = [];

if (!empty($module_query) && $module_query != 'menu' && $module_query != 'custom') {
    // Conteggio totale
    $query = 'SELECT COUNT(*) as `tot` FROM ('.$module_query.') AS `count`';
    $cont = $dbo->fetchArray($query);
    if (!empty($cont)) {
        $results['recordsTotal'] = $cont[0]['tot'];
    }

    // Filtri di ricerica
    $search_filters = [];
    for ($i = 0; $i < count($columns); ++$i) {
        if (!empty($columns[$i]['search']['value'])) {
            if (strpos($total['search_inside'][$i], '|search|') !== false) {
                $pieces = explode(',', $columns[$i]['search']['value']);
                foreach ($pieces as $piece) {
                    $piece = trim($piece);
                    $search_filters[] = str_replace('|search|', prepare('%'.$piece.'%'), $total['search_inside'][$i]);
                }
            } else {
                $search_filters[] = '`'.$total['search_inside'][$i].'` LIKE '.prepare('%'.trim($columns[$i]['search']['value'].'%'));
            }
        }
    }

    if (!empty($search_filters)) {
        $module_query = str_replace('2=2', '2=2 AND ('.implode(' AND ', $search_filters).') ', $module_query);
    }

    // Filtri derivanti dai permessi (eventuali)
    $module_query = Modules::replaceAdditionals($id_module, $module_query);

    // Ordinamento dei risultati
    if (isset($order['dir']) && isset($order['column'])) {
        $pieces = explode('ORDER', $module_query);

        $cont = count($pieces);
        if ($cont > 1) {
            unset($pieces[$cont - 1]);
        }

        $module_query = implode('ORDER', $pieces).' ORDER BY `'.$total['order_by'][$order['column']].'` '.$order['dir'];
    }

    // Calcolo di eventuali somme
    if (!empty($total['summable'])) {
        $query = str_replace_once('SELECT', 'SELECT '.implode(', ', $total['summable']).' FROM(SELECT ', $module_query).') AS `z`';
        $sums = $dbo->fetchArray($query)[0];
        if (!empty($sums)) {
            $r = [];
            foreach ($sums as $key => $sum) {
                if (strpos($key, 'sum_') !== false) {
                    $r[str_replace('sum_', '', $key)] = Translator::numberToLocale($sum);
                }
            }
            $results['summable'] = $r;
        }
    }

    // Paginazione
    if ($length > 0) {
        $module_query .= ' LIMIT '.$start.', '.$length;
    }

    // Query effettiva
    $query = str_replace_once('SELECT', 'SELECT SQL_CALC_FOUND_ROWS', $module_query);
    $rs = $dbo->fetchArray($query);

    // Conteggio dei record filtrati
    $cont = $dbo->fetchArray('SELECT FOUND_ROWS()');
    if (!empty($cont)) {
        $results['recordsFiltered'] = $cont[0]['FOUND_ROWS()'];
    }

    // Creazione della tabella
    $align = [];
    foreach ($rs as $i => $r) {
        if ($i == 0) {
            foreach ($total['fields'] as $field) {
                $value = trim($r[$field]);

                // Allineamento a destra se il valore della prima riga risulta numerica
                if (Translator::getEnglishFormatter()->isNumber($value) || Translator::getEnglishFormatter()->isNumber($value)) {
                    $align[$field] = 'text-right';
                }

                // Allineamento al centro se il valore della prima riga risulta relativo a date o icone
                elseif ((Translator::getEnglishFormatter()->isDate($value) || Translator::getEnglishFormatter()->isDate($value)) || preg_match('/^icon_(.+?)$/', $field)) {
                    $align[$field] = 'text-center';
                }
            }
        }

        $result = [];
        $result[] = '<span class="hide" data-id="'.$r['id'].'"></span>';
        foreach ($total['fields'] as $pos => $field) {
            $column = [];

            if (!empty($r['_bg_'])) {
                $column['data-background'] = $r['_bg_'];
            }

            // Allineamento
            if (!empty($align[$field])) {
                $column['class'] = $align[$field];
            }

            $value = trim($r[$field]);

            // Formattazione automatica
            if (!empty($total['format'][$pos]) && !empty($value) && !empty(Translator::getEnglishFormatter())) {
                if (Translator::getEnglishFormatter()->isNumber($value)) {
                    $value = Translator::numberToLocale($value);
                } elseif (Translator::getEnglishFormatter()->isTimestamp($value)) {
                    $value = Translator::timestampToLocale($value);
                } elseif (Translator::getEnglishFormatter()->isDate($value)) {
                    $value = Translator::dateToLocale($value);
                } elseif (Translator::getEnglishFormatter()->isTime($value)) {
                    $value = Translator::timeToLocale($value);
                }
            }

            // Icona
            if (preg_match('/^color_(.+?)$/', $field, $m)) {
                $value = $r['color_title_'.$m[1]] ?: '';

                $column['class'] = 'text-center small';
                $column['data-background'] = $r[$field];
            }

            // Icona di stampa
            elseif ($field == '_print_') {
                $print_url = $r['_print_'];

                preg_match_all('/\$(.+?)\$/', $print_url, $matches);

                for ($m = 0; $m < sizeof($matches[0]); ++$m) {
                    $print_url = str_replace($matches[0][$m], $r[$matches[1][$m]], $print_url);
                }

                $value = '<a href="'.$rootdir.'/'.$print_url.'" target="_blank"><i class="fa fa-2x fa-print"></i></a>';
            }

            // Icona
            elseif (preg_match('/^icon_(.+?)$/', trim($field), $m)) {
                $value = '<i class="'.$r[$field].'"></i> <small>'.$r['icon_title_'.$m[1]].'</small>';
            }

            // Colore del testo
            if (!empty($column['data-background'])) {
                $column['data-color'] = $column['data-color'] ?: color_inverse($column['data-background']);
            }

            // Link della colonna
            if ($field != '_print_') {
                $id_record = $r['id'];
                $hash = '';
                if (!empty($r['_link_record_'])) {
                    $id_module = $r['_link_module_'];
                    $id_record = $r['_link_record_'];
                    $hash = !empty($r['_link_hash_']) ? '#'.$r['_link_hash_'] : '';
                    unset($id_plugin);
                }

                $column['data-link'] = $rootdir.'/'.(empty($id_plugin) ? '' : 'plugin_').'editor.php?id_module='.$id_module.'&id_record='.$id_record.(empty($id_plugin) ? '' : '&id_plugin='.$id_plugin.'&id_parent='.$id_parent).$hash;

                if (!empty($id_plugin)) {
                    $column['data-type'] = 'dialog';
                }
            }

            $attributes = [];
            foreach ($column as $key => $val) {
                $val = is_array($val) ? implode(' ', $val) : $val;
                $attributes[] = $key.'="'.$val.'"';
            }

            $result[] = str_replace('|attr|', implode(' ', $attributes), '<div |attr|>'.$value.'</div>');
        }

        $results['data'][] = $result;
    }
}

echo json_encode($results);
