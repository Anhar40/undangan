<?php

namespace App\Support;

/**
 * Daftar bank yang dipakai oleh editor amplop dan renderer undangan.
 *
 * Logo dirender sebagai badge inisial berwarna_uri bank. Ini sengaja dibuat
 * lokal (tanpa hotlink logo eksternal) agar undangan tetap tampil di hosting
 * bersama dan tidak bergantung pada aset pihak ketiga.
 */
final class IndonesianBanks
{
    /** @return array<int, array{code:string,name:string,full_name:string,initials:string,color:string,group:string}> */
    public static function all(): array
    {
        return [
            // Bank umum konvensional
            ['code' => 'bca', 'name' => 'BCA', 'full_name' => 'Bank Central Asia', 'initials' => 'BCA', 'color' => '#0B4EA2', 'group' => 'Bank Umum'],
            ['code' => 'mandiri', 'name' => 'Mandiri', 'full_name' => 'Bank Mandiri', 'initials' => 'MDR', 'color' => '#F2A900', 'group' => 'Bank Umum'],
            ['code' => 'bni', 'name' => 'BNI', 'full_name' => 'Bank Negara Indonesia', 'initials' => 'BNI', 'color' => '#E84C1F', 'group' => 'Bank Umum'],
            ['code' => 'bri', 'name' => 'BRI', 'full_name' => 'Bank Rakyat Indonesia', 'initials' => 'BRI', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'btn', 'name' => 'BTN', 'full_name' => 'Bank Tabungan Negara', 'initials' => 'BTN', 'color' => '#0B6EAF', 'group' => 'Bank Umum'],
            ['code' => 'cimb-niaga', 'name' => 'CIMB Niaga', 'full_name' => 'Bank CIMB Niaga', 'initials' => 'CIMB', 'color' => '#E4002B', 'group' => 'Bank Umum'],
            ['code' => 'danamon', 'name' => 'Danamon', 'full_name' => 'Bank Danamon Indonesia', 'initials' => 'DAN', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'permata', 'name' => 'Permata', 'full_name' => 'Bank Permata', 'initials' => 'PMT', 'color' => '#00AEEF', 'group' => 'Bank Umum'],
            ['code' => 'ocbc', 'name' => 'OCBC', 'full_name' => 'Bank OCBC Indonesia', 'initials' => 'OCBC', 'color' => '#E4002B', 'group' => 'Bank Umum'],
            ['code' => 'uob', 'name' => 'UOB Indonesia', 'full_name' => 'Bank United Overseas Bank Indonesia', 'initials' => 'UOB', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'maybank', 'name' => 'Maybank Indonesia', 'full_name' => 'Bank Maybank Indonesia', 'initials' => 'MB', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'panin', 'name' => 'Panin Bank', 'full_name' => 'Bank Pan Indonesia', 'initials' => 'PN', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'mega', 'name' => 'Bank Mega', 'full_name' => 'Bank Mega', 'initials' => 'MEGA', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'sinarmas', 'name' => 'Bank Sinarmas', 'full_name' => 'Bank Sinarmas', 'initials' => 'SM', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'dbs', 'name' => 'DBS Indonesia', 'full_name' => 'Bank DBS Indonesia', 'initials' => 'DBS', 'color' => '#E4002B', 'group' => 'Bank Umum'],
            ['code' => 'commonwealth', 'name' => 'Bank Commonwealth', 'full_name' => 'Bank Commonwealth', 'initials' => 'CBA', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'smbci', 'name' => 'Bank SMBC Indonesia', 'full_name' => 'Bank SMBC Indonesia', 'initials' => 'SMBC', 'color' => '#0057A8', 'group' => 'Bank Umum'],
            ['code' => 'kb-bank', 'name' => 'KB Bank', 'full_name' => 'KB Bank Indonesia', 'initials' => 'KB', 'color' => '#E30613', 'group' => 'Bank Umum'],
            ['code' => 'neo-commerce', 'name' => 'Bank Neo Commerce', 'full_name' => 'Bank Neo Commerce', 'initials' => 'BNC', 'color' => '#F26A21', 'group' => 'Bank Umum'],
            ['code' => 'jago', 'name' => 'Bank Jago', 'full_name' => 'Bank Jago', 'initials' => 'JAGO', 'color' => '#00B8B8', 'group' => 'Bank Umum'],
            ['code' => 'seabank', 'name' => 'SeaBank', 'full_name' => 'Bank Sea Bank Indonesia', 'initials' => 'SB', 'color' => '#E4002B', 'group' => 'Bank Umum'],
            ['code' => 'blu', 'name' => 'blu by BCA Digital', 'full_name' => 'Bank BCA Digital', 'initials' => 'blu', 'color' => '#0057FF', 'group' => 'Bank Umum'],
            ['code' => 'hsbc', 'name' => 'HSBC Indonesia', 'full_name' => 'Bank HSBC Indonesia', 'initials' => 'HSBC', 'color' => '#DB0011', 'group' => 'Bank Umum'],
            ['code' => 'standard-chartered', 'name' => 'Standard Chartered', 'full_name' => 'Bank Standard Chartered Indonesia', 'initials' => 'SC', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'citibank', 'name' => 'Citibank Indonesia', 'full_name' => 'Bank Citibank Indonesia', 'initials' => 'CITI', 'color' => '#003B70', 'group' => 'Bank Umum'],
            ['code' => 'icbc', 'name' => 'ICBC Indonesia', 'full_name' => 'Bank ICBC Indonesia', 'initials' => 'ICBC', 'color' => '#E60012', 'group' => 'Bank Umum'],
            ['code' => 'bank-of-china', 'name' => 'Bank of China', 'full_name' => 'Bank of China Indonesia', 'initials' => 'BOC', 'color' => '#E60012', 'group' => 'Bank Umum'],
            ['code' => 'mufg', 'name' => 'Bank MUFG Indonesia', 'full_name' => 'Bank MUFG Indonesia', 'initials' => 'MUFG', 'color' => '#E60012', 'group' => 'Bank Umum'],
            ['code' => 'mizuho', 'name' => 'Bank Mizuho Indonesia', 'full_name' => 'Bank Mizuho Indonesia', 'initials' => 'MIZ', 'color' => '#E60012', 'group' => 'Bank Umum'],
            ['code' => 'resona', 'name' => 'Bank Resona Perdania', 'full_name' => 'Bank Resona Perdania', 'initials' => 'RES', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'bank-of-india', 'name' => 'Bank of India Indonesia', 'full_name' => 'Bank of India Indonesia', 'initials' => 'BOI', 'color' => '#E4002B', 'group' => 'Bank Umum'],
            ['code' => 'shinhan', 'name' => 'Bank Shinhan Indonesia', 'full_name' => 'Bank Shinhan Indonesia', 'initials' => 'SHIN', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'woori', 'name' => 'Bank Woori Saudara', 'full_name' => 'Bank Woori Saudara Indonesia', 'initials' => 'WOORI', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'keb-hana', 'name' => 'Bank KEB Hana Indonesia', 'full_name' => 'Bank KEB Hana Indonesia', 'initials' => 'HANA', 'color' => '#00857C', 'group' => 'Bank Umum'],
            ['code' => 'bnp-paribas', 'name' => 'Bank BNP Paribas Indonesia', 'full_name' => 'Bank BNP Paribas Indonesia', 'initials' => 'BNP', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'anz', 'name' => 'Bank ANZ Indonesia', 'full_name' => 'Bank Australia and New Zealand Indonesia', 'initials' => 'ANZ', 'color' => '#00529C', 'group' => 'Bank Umum'],
            ['code' => 'daiwa', 'name' => 'Bank Daiwa Perdania', 'full_name' => 'Bank Daiwa Perdania', 'initials' => 'DAI', 'color' => '#E30613', 'group' => 'Bank Umum'],

            // Bank syariah
            ['code' => 'bsi', 'name' => 'BSI', 'full_name' => 'Bank Syariah Indonesia', 'initials' => 'BSI', 'color' => '#00A39E', 'group' => 'Bank Syariah'],
            ['code' => 'muamalat', 'name' => 'Bank Muamalat', 'full_name' => 'Bank Muamalat Indonesia', 'initials' => 'MUAM', 'color' => '#008C4F', 'group' => 'Bank Syariah'],
            ['code' => 'aladin-syariah', 'name' => 'Bank Aladin Syariah', 'full_name' => 'Bank Aladin Syariah', 'initials' => 'ALD', 'color' => '#00A39E', 'group' => 'Bank Syariah'],
            ['code' => 'btpn-syariah', 'name' => 'Bank BTPN Syariah', 'full_name' => 'Bank BTPN Syariah', 'initials' => 'BTPNS', 'color' => '#008C4F', 'group' => 'Bank Syariah'],

            // Bank Pembangunan Daerah
            ['code' => 'bank-aceh-syariah', 'name' => 'Bank Aceh Syariah', 'full_name' => 'Bank Aceh Syariah', 'initials' => 'ACEH', 'color' => '#008C4F', 'group' => 'BPD'],
            ['code' => 'bank-sumut', 'name' => 'Bank Sumut', 'full_name' => 'Bank Pembangunan Daerah Sumatera Utara', 'initials' => 'SUMUT', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-nagari', 'name' => 'Bank Nagari', 'full_name' => 'Bank Pembangunan Daerah Sumatera Barat', 'initials' => 'NGR', 'color' => '#008C4F', 'group' => 'BPD'],
            ['code' => 'bank-riau-kepri', 'name' => 'Bank Riau Kepri', 'full_name' => 'Bank Pembangunan Daerah Riau dan Kepri', 'initials' => 'RK', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-jambi', 'name' => 'Bank Jambi', 'full_name' => 'Bank Pembangunan Daerah Jambi', 'initials' => 'JMB', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-bengkulu', 'name' => 'Bank Bengkulu', 'full_name' => 'Bank Pembangunan Daerah Bengkulu', 'initials' => 'BGL', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sri-bima', 'name' => 'Bank Sri Bima', 'full_name' => 'Bank Pembangunan Daerah Nusa Tenggara Barat', 'initials' => 'SRI', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sumsel-babel', 'name' => 'Bank Sumsel Babel', 'full_name' => 'Bank Pembangunan Daerah Sumatera Selatan dan Bangka Belitung', 'initials' => 'SSB', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-lampung', 'name' => 'Bank Lampung', 'full_name' => 'Bank Pembangunan Daerah Lampung', 'initials' => 'LPG', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-banten', 'name' => 'Bank Banten', 'full_name' => 'Bank Pembangunan Daerah Banten', 'initials' => 'BTN', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-jabar-banten', 'name' => 'Bank Jabar Banten', 'full_name' => 'Bank Pembangunan Daerah Jawa Barat dan Banten', 'initials' => 'BJB', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-dki', 'name' => 'Bank DKI', 'full_name' => 'Bank Pembangunan Daerah DKI Jakarta', 'initials' => 'DKI', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-diy', 'name' => 'Bank DIY', 'full_name' => 'Bank Pembangunan Daerah Istimewa Yogyakarta', 'initials' => 'DIY', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-jateng', 'name' => 'Bank Jateng', 'full_name' => 'Bank Pembangunan Daerah Jawa Tengah', 'initials' => 'JTG', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-jatim', 'name' => 'Bank Jatim', 'full_name' => 'Bank Pembangunan Daerah Jawa Timur', 'initials' => 'JTM', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-bali', 'name' => 'Bank BPD Bali', 'full_name' => 'Bank Pembangunan Daerah Bali', 'initials' => 'BALI', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-kalbar', 'name' => 'Bank Kalbar', 'full_name' => 'Bank Pembangunan Daerah Kalimantan Barat', 'initials' => 'KLB', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-kalteng', 'name' => 'Bank Kalteng', 'full_name' => 'Bank Pembangunan Daerah Kalimantan Tengah', 'initials' => 'KLT', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-kalsel', 'name' => 'Bank Kalsel', 'full_name' => 'Bank Pembangunan Daerah Kalimantan Selatan', 'initials' => 'KLS', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-kaltim', 'name' => 'Bank Kaltimtara', 'full_name' => 'Bank Pembangunan Daerah Kalimantan Timur dan Utara', 'initials' => 'KTI', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sulselbar', 'name' => 'Bank Sulselbar', 'full_name' => 'Bank Pembangunan Daerah Sulawesi Selatan dan Barat', 'initials' => 'SLB', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sulutgo', 'name' => 'Bank SulutGo', 'full_name' => 'Bank Pembangunan Daerah Sulawesi Utara dan Gorontalo', 'initials' => 'SUL', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sulteng', 'name' => 'Bank Sulteng', 'full_name' => 'Bank Pembangunan Daerah Sulawesi Tengah', 'initials' => 'STG', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-sultra', 'name' => 'Bank Sultra', 'full_name' => 'Bank Pembangunan Daerah Sulawesi Tenggara', 'initials' => 'STR', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-gorontalo', 'name' => 'Bank Gorontalo', 'full_name' => 'Bank Pembangunan Daerah Gorontalo', 'initials' => 'GTO', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-maluku-malut', 'name' => 'Bank Maluku Malut', 'full_name' => 'Bank Pembangunan Daerah Maluku dan Maluku Utara', 'initials' => 'MLK', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-papua', 'name' => 'Bank Papua', 'full_name' => 'Bank Pembangunan Daerah Papua', 'initials' => 'PP', 'color' => '#00529C', 'group' => 'BPD'],
            ['code' => 'bank-ntb-syariah', 'name' => 'Bank NTB Syariah', 'full_name' => 'Bank Pembangunan Daerah Nusa Tenggara Barat Syariah', 'initials' => 'NTB', 'color' => '#008C4F', 'group' => 'BPD'],
            ['code' => 'bank-ntt', 'name' => 'Bank NTT', 'full_name' => 'Bank Pembangunan Daerah Nusa Tenggara Timur', 'initials' => 'NTT', 'color' => '#00529C', 'group' => 'BPD'],
        ];
    }

    /** @return array<int, array{code:string,name:string,full_name:string,initials:string,color:string,group:string}> */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::all() as $bank) {
            $groups[$bank['group']][] = $bank;
        }

        return $groups;
    }

    /** @return array{code:string,name:string,full_name:string,initials:string,color:string,group:string}|null */
    public static function find(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $needle = self::normalize($value);
        $loose = preg_replace('/[^a-z0-9]/', '', strtolower($value)) ?? '';

        foreach (self::all() as $bank) {
            if ($needle === self::normalize($bank['code'])
                || $needle === self::normalize($bank['name'])
                || $needle === self::normalize($bank['full_name'])
                || ($loose !== '' && $loose === preg_replace('/[^a-z0-9]/', '', strtolower($bank['full_name'])))) {
                return $bank;
            }
        }

        return null;
    }

    public static function badge(?string $value, string $class = 'bank-mark'): string
    {
        $bank = self::find($value);
        $name = $bank['name'] ?? ($value !== null && trim($value) !== '' ? trim($value) : 'Bank');
        $initials = $bank['initials'] ?? self::initials($name);
        $color = $bank['color'] ?? '#8A6B3D';

        return '<span class="' . e($class) . '" style="--bank-color:' . e($color) . '">'
            . e($initials) . '</span>';
    }

    private static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private static function initials(string $name): string
    {
        $clean = preg_replace('/[^A-Z0-9]/i', '', strtoupper($name)) ?: 'BANK';

        return strtoupper(substr($clean, 0, 3));
    }
}
