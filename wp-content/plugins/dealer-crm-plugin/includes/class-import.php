<?php
defined('ABSPATH') || exit;

class DealerCRM_Import {

    public static function import_xlsx($file_path) {
        if (!file_exists($file_path)) {
            return ['success' => false, 'message' => 'Bestand niet gevonden.'];
        }

        $rows = self::read_xlsx($file_path);
        if (!$rows) {
            return ['success' => false, 'message' => 'Kon het bestand niet lezen.'];
        }

        global $wpdb;
        $p = $wpdb->prefix . 'crm_';

        $headers = array_map('trim', $rows[0]);
        $col_map = [];
        $mapping = [
            'Naam' => 'name', 'Straat' => 'street', 'Postcode' => 'postcode',
            'Plaats' => 'city', 'Telefoon' => 'phone', 'E-mail' => 'email',
            'Website' => 'website', 'Merken' => 'brands',
        ];
        foreach ($headers as $i => $header) {
            if (isset($mapping[$header])) {
                $col_map[$mapping[$header]] = $i;
            }
        }

        if (!isset($col_map['name'])) {
            return ['success' => false, 'message' => 'Kolom "Naam" niet gevonden.'];
        }

        $brand_cache = [];
        $imported = 0;
        $skipped = 0;

        $wpdb->query('START TRANSACTION');

        for ($r = 1; $r < count($rows); $r++) {
            $row = $rows[$r];
            $name = trim($row[$col_map['name']] ?? '');
            if (!$name) { $skipped++; continue; }

            $dealer_data = ['name' => $name, 'status' => 'actief'];
            foreach (['street', 'postcode', 'city', 'phone', 'email', 'website'] as $field) {
                if (isset($col_map[$field])) {
                    $dealer_data[$field] = trim($row[$col_map[$field]] ?? '');
                }
            }

            $wpdb->insert($p . 'dealers', $dealer_data);
            $dealer_id = $wpdb->insert_id;

            if ($dealer_id && isset($col_map['brands'])) {
                $brands_str = trim($row[$col_map['brands']] ?? '');
                if ($brands_str) {
                    $brands = array_map('trim', explode(',', $brands_str));
                    foreach ($brands as $brand_name) {
                        if (!$brand_name) continue;
                        if (!isset($brand_cache[$brand_name])) {
                            $existing = $wpdb->get_var($wpdb->prepare(
                                "SELECT id FROM {$p}brands WHERE name = %s", $brand_name
                            ));
                            if ($existing) {
                                $brand_cache[$brand_name] = $existing;
                            } else {
                                $wpdb->insert($p . 'brands', ['name' => $brand_name]);
                                $brand_cache[$brand_name] = $wpdb->insert_id;
                            }
                        }
                        $wpdb->replace($p . 'dealer_brand', [
                            'dealer_id' => $dealer_id,
                            'brand_id' => $brand_cache[$brand_name],
                        ]);
                    }
                }
            }
            $imported++;
        }

        $wpdb->query('COMMIT');

        return [
            'success' => true,
            'message' => sprintf('%d dealers geïmporteerd, %d overgeslagen.', $imported, $skipped),
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }

    private static function read_xlsx($file_path) {
        $zip = new ZipArchive();
        if ($zip->open($file_path) !== true) return false;

        $shared_strings = [];
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml) {
            $sst = new SimpleXMLElement($xml);
            foreach ($sst->si as $si) {
                $text = '';
                if ($si->t) {
                    $text = (string) $si->t;
                } elseif ($si->r) {
                    foreach ($si->r as $run) {
                        $text .= (string) $run->t;
                    }
                }
                $shared_strings[] = $text;
            }
        }

        $sheet_xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheet_xml) { $zip->close(); return false; }

        $sheet = new SimpleXMLElement($sheet_xml);
        $rows = [];

        foreach ($sheet->sheetData->row as $row) {
            $row_data = [];
            foreach ($row->c as $cell) {
                $col_index = self::col_to_index((string) $cell['r']);
                while (count($row_data) < $col_index) {
                    $row_data[] = '';
                }
                $value = '';
                if (isset($cell->v)) {
                    $value = (string) $cell->v;
                    if ((string) $cell['t'] === 's') {
                        $value = $shared_strings[(int) $value] ?? '';
                    }
                } elseif (isset($cell->is)) {
                    $value = (string) $cell->is->t;
                }
                $row_data[] = $value;
            }
            $rows[] = $row_data;
        }

        $zip->close();
        return $rows;
    }

    private static function col_to_index($cell_ref) {
        preg_match('/^([A-Z]+)/', $cell_ref, $matches);
        $col = $matches[1];
        $index = 0;
        for ($i = 0; $i < strlen($col); $i++) {
            $index = $index * 26 + (ord($col[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }
}
