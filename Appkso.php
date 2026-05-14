<?php

namespace App\Http\Controllers\appkso;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Appkso extends Controller
{
    public function index(Request $request)
    {
        // 1. Ambil List Project SO untuk Dropdown
        $list_kso = DB::connection('mysql_second')
            ->table('ms_kso')
            ->select('so_name', 'def_counter')
            ->orderBy('recid', 'desc')
            ->get();

        $selected_so = $request->so_name ?? ($list_kso->first()->so_name ?? null);

        // 2. Olah Summary (mysql_second)
        $query_summary = DB::connection('mysql_second')->table('cntso');
        if ($selected_so) {
            $query_summary->where('so_name', $selected_so);
        }

        $summary_raw = (clone $query_summary)
            ->select(
                DB::raw('COUNT(recid) as total_scan'),
                DB::raw('SUM(QtyStk) as total_pcs'),
                DB::raw('COUNT(DISTINCT ItemCode) as unique_items'),
                DB::raw('COUNT(CASE WHEN status = "APPROVE" THEN 1 END) as approved')
            )->first();

        $summary = [
            'total_scan'   => $summary_raw->total_scan ?? 0,
            'total_pcs'    => $summary_raw->total_pcs ?? 0,
            'unique_items' => $summary_raw->unique_items ?? 0,
            'approved'     => $summary_raw->approved ?? 0,
        ];

        // 3. TARIK DATA AKTIVITAS (Tabel Kiri)
        $db_bcm = 'bcmcfgv1';
        $all_activities_raw = DB::connection('mysql_second')
            ->table('cntso as c')
            ->leftJoin($db_bcm . '.oprbld as o', 'c.opr', '=', 'o.oprcode')
            ->select('c.ydate_shift', 'c.opr', 'o.oprname', 'c.NoDoc', 'c.ItemCode', 'c.QtyStk', 'c.status', 'c.txndate')
            ->when($selected_so, function ($q) use ($selected_so) {
                return $q->where('c.so_name', $selected_so);
            })
            ->orderBy('c.recid', 'desc')
            ->get();

        // 4. MAPPING DATA UNTUK SISI KANAN & DESKRIPSI (Cross-Server Mapping)
        $master_map = DB::connection('mysql')
            ->table('master_items')
            ->select('item_code_desc', 'description', 'pattern')
            ->get()
            ->keyBy('item_code_desc');

        $activities = $all_activities_raw->map(function ($item) use ($master_map) {
            $master = $master_map->get($item->ItemCode);
            $item->description = $master->description ?? 'N/A di Master';
            $item->pattern = $master->pattern ?? 'UNIDENTIFIED';
            return $item;
        });

        $pic_nokso_map = $all_activities_raw
            ->groupBy('opr')
            ->map(function ($items) {
                return $items->pluck('NoDoc')->unique()->values();
            });

        $pic_list = $all_activities_raw
            ->pluck('opr')
            ->unique()
            ->values();

        // Grouping Sisi Kanan: Resume OE vs OK
        $resume_oe = $activities->filter(fn($i) => str_contains(strtoupper($i->pattern), 'OE'))
            ->groupBy('pattern')->map(fn($items, $pattern) => [
                'pattern' => $pattern,
                'total_qty' => $items->sum('QtyStk'),
                'total_sku' => $items->unique('ItemCode')->count(),
            ])->sortByDesc('total_qty')->values();

        $resume_ok = $activities->filter(fn($i) => str_contains(strtoupper($i->pattern), 'OK'))
            ->groupBy('pattern')->map(fn($items, $pattern) => [
                'pattern' => $pattern,
                'total_qty' => $items->sum('QtyStk'),
                'total_sku' => $items->unique('ItemCode')->count(),
            ])->sortByDesc('total_qty')->values();

        // 5. Productivity (Top 10 Operator) - INI YANG TADI HILANG
        $productivity = DB::connection('mysql_second')
            ->table('cntso')
            ->select('opr', DB::raw('COUNT(recid) as total_scan'))
            ->when($selected_so, function ($q) use ($selected_so) {
                return $q->where('so_name', $selected_so);
            })
            ->groupBy('opr')
            ->orderBy('total_scan', 'desc')
            ->limit(10)
            ->get();

        // Kirim semua variabel ke view
        return view('appkso.appkso', compact(
            'summary',
            'activities',
            'list_kso',
            'selected_so',
            'productivity',
            'resume_oe',
            'resume_ok',
            'pic_nokso_map',
            'pic_list'
        ));
    }
    public function generatePrintPreview(Request $request)
    {
        try {
            $pic = trim($request->pic_name);
            $docFrom = $request->doc_from;
            $docTo = $request->doc_to;

            // Tambahkan JOIN ke table oprbld supaya dapet nama asli operator (oprname)
            $db_bcm = 'bcmcfgv1'; // sesuaikan nama db bcm lu

            $rows = DB::connection('mysql_second')
                ->table('cntso as c')
                ->leftJoin($db_bcm . '.oprbld as o', 'c.opr', '=', 'o.oprcode') // JOIN DISINI
                ->select(
                    'c.NoDoc as nokso',
                    'c.ItemCode as item',
                    'c.QtyStk',
                    'o.oprname' // TARIK KOLOM NAMA DISINI
                )
                ->where(DB::raw('TRIM(c.opr)'), $pic)
                ->whereBetween('c.NoDoc', [$docFrom, $docTo])
                ->orderBy('c.NoDoc', 'asc')
                ->get();

            if ($rows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "Data tidak ditemukan untuk PIC: $pic"
                ]);
            }

            // Mapping deskripsi dari master_items (mysql)
            $itemCodes = $rows->pluck('item')->unique()->toArray();
            $master_map = DB::connection('mysql')
                ->table('master_items')
                ->select('item_code_desc', 'description')
                ->whereIn('item_code_desc', $itemCodes)
                ->get()
                ->keyBy('item_code_desc');

            // Susun Data Final
            $finalData = $rows->map(function ($row) use ($master_map) {
                $master = $master_map->get($row->item);
                return [
                    'nokso' => $row->nokso,
                    'item' => $row->item,
                    'QtyStk' => $row->QtyStk,
                    'oprname' => $row->oprname ?? 'N/A',
                    'deskripsi' => $master->description ?? 'N/A di Master'
                ];
            });

            $html = $this->buildHtmlTemplate($finalData, $request->all());

            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Line ' . $e->getLine() . ': ' . $e->getMessage()
            ], 500);
        }
    }
    // Pastikan fungsi ini ada di dalam class yang sama
    private function buildHtmlTemplate($rows, $settings)
    {

        $layoutOption = $settings['printLayout'] ?? 'both';
        $showBarcode = isset($settings['showBarcode'])
            ? filter_var($settings['showBarcode'], FILTER_VALIDATE_BOOLEAN)
            : true;

        $html_content = '';

        foreach ($rows as $t) {

            $qty_str = (string) $t['QtyStk'];

            $get_digit = function ($qty_str, $index_from_end) {
                $length = strlen($qty_str);
                $target_index = $length - $index_from_end;

                return ($target_index >= 0)
                    ? (int) $qty_str[$target_index]
                    : null;
            };

            $puluhan_ribu_digit = $get_digit($qty_str, 5);
            $ribuan_digit        = $get_digit($qty_str, 4);
            $ratusan_digit       = $get_digit($qty_str, 3);
            $puluhan_digit       = $get_digit($qty_str, 2);
            $satuan_digit        = $get_digit($qty_str, 1);

            $grade = substr($t['item'], -1) === '0' ? 'OE' : 'OK';

            $sections = [];

            if ($layoutOption == 'gudang' || $layoutOption == 'both') {

                $sections[] = [
                    'label' => 'LEMBAR UNTUK GUDANG',
                    'show_gunting' => ($layoutOption == 'both'),
                    'show_barcode' => true
                ];
            }

            if ($layoutOption == 'arsip' || $layoutOption == 'both') {

                $sections[] = [
                    'label' => 'LEMBAR UNTUK ARSIP',
                    'show_gunting' => false,
                    'show_barcode' => false
                ];
            }

            $html_content .= '
        <div class="page-break">

        <table class="kartu-table">';

            foreach ($sections as $sec) {

                $html_content .= '

            <tr>
                <td colspan="6" class="header-area">

                    <div class="title">
                        KARTU STOCK OPNAME
                    </div>

                    <div class="tanggal">
                        TANGGAL : 15 / JUNI / 2026
                    </div>

                    <div class="grade">
                        ' . $grade . '
                    </div>

                   ' . ($sec['show_barcode'] ? '
                    <div class="barcode-box">

                        <div class="barcode-title">
                            KODE DOKUMEN & NO. DOC
                        </div>

                        <div class="barcode">
                            *' . $t['nokso'] . '*
                        </div>

                        <div class="barcode-text">
                            ' . $t['nokso'] . '
                        </div>

                    </div>
                    ' : '') . '

                </td>
            </tr>

            <tr>
                <td colspan="4" class="left-text">
                    PT GAJAH TUNGGAL Tbk
                </td>

                <td class="right-label">
                    BARANG MILIK PLANT
                </td>

                <td class="right-value">
                    <span>: <b>B</b></span>
                </td>
            </tr>

            <tr>
                <td colspan="4" class="desc">
                    <b>' . $t['deskripsi'] . '</b>
                </td>

                <td class="right-label">
                    NO. DOCUMENT
                </td>

                <td class="right-value">
                    <span>: <b>' . $t['nokso'] . '</b></span>
                </td>
            </tr>

            <tr>
               <td colspan="4" class="item-barcode">
                    ' . ($sec['show_barcode']
                    ? '*' . $t['item'] . '*'
                    : '') . '
                </td>

                <td class="right-label top">
                    NO. INDEX
                </td>

                <td class="right-value">
                    <span>:</span>
                </td>
            </tr>

            <tr>
                <td colspan="4" class="item-text">
                    ' . $t['item'] . '
                </td>

                <td colspan="2" class="line-number">
                    LINE NUMBER
                </td>
            </tr>

            ' . $this->digitRow('GRADE', ': <b>' . $grade . '</b>', 'PLANT : <b>B</b>', $puluhan_ribu_digit) . '

            ' . $this->digitRow('JENIS', ':', '', $ribuan_digit) . '

            ' . $this->digitRow('UKURAN', ': <b>' . $t['deskripsi'] . '</b>', '', $ratusan_digit) . '

            ' . $this->digitRow('CODE', ': <b>' . $t['item'] . '</b>', '', $puluhan_digit) . '

            ' . $this->digitRow(
                    'JUMLAH',
                    ': <b>' . number_format($t['QtyStk'], 0, ',', '.') . ' PCS</b>' .
                        ($sec['show_barcode'] ? '<span class="qty-barcode">*' . $t['QtyStk'] . '*</span>' : ''),
                    '',
                    $satuan_digit
                ) . '

            <tr class="section-line">
                <td colspan="4"></td>

                <td colspan="2" class="section-label">
                    ' . $sec['label'] . '
                </td>
            </tr>

            <tr>
                <td colspan="2" class="ttd-title">
                    DIHITUNG OLEH
                </td>

                <td colspan="2"></td>

                <td colspan="2" class="ttd-title">
                    DIPERIKSA OLEH
                </td>
            </tr>

            <tr>
                <td colspan="2" class="ttd-space">
                    <b>' . $t['oprname'] . '</b>
                </td>

                <td colspan="2"></td>

                <td colspan="2" class="ttd-space">
                    <b>..........</b>
                </td>
            </tr>

            <tr>
                <td colspan="2" class="ttd-line">
                    <div>GUDANG BAN</div>
                </td>

                <td colspan="2"></td>

                <td colspan="2" class="ttd-line">
                    <div>TEAM S.O./AUDITOR</div>
                </td>
            </tr>

            ' . ($sec['show_gunting']
                    ? '
                 <tr>
        <td colspan="6" class="gunting">
            <img style="width:100%; height:20px;"
     src="' . asset('images/garis_gunting.png') . '">
        </td>
    </tr>
    '
                    : '') . '

            ';
            }

            $html_content .= '
        </table>
        </div>';
        }

        return '
    <html>

    <head>

    <style>

    @font-face {
        font-family: "Libre Barcode 39";
        src: url("' . asset('fonts/LibreBarcode39-Regular.ttf') . '") format("truetype");
    }

    body{
        margin:0;
        padding:0;
        background:#fff;
        font-family:"Times New Roman", serif;
    }

    .page-break{
        page-break-after:always;
        padding: 15px 0 0 0; /* Tambahan jarak dari atas sedikit biar gak terlalu mepet */
        box-sizing: border-box;
        height: 100vh; /* Memaksa agar 1 halaman ini full sampai bawah */
    }

    .kartu-table{
        width: 100%;
        height: 100%; /* Memaksa tabel melar sampai ke bawah kertas */
        border-collapse:collapse;
        table-layout:fixed;
        font-size:13px;
    }

    .header-area{
        text-align:center;
        position:relative;
    }

    .title{
        font-size:16px;
        font-weight:bold;
    }

    .tanggal{
        font-size:11px;
        margin-top:-2px;
    }

    .grade{
        font-size:18px;
        font-weight:bold;
        margin-top:5px;
        margin-bottom:5px;
    }

    .barcode-box{
        position:absolute;
        top:0;
        right:0;
        text-align:center;
    }

    .barcode-title{
        font-size:10px;
    }

    .barcode{
        font-family:"Libre Barcode 39";
        font-size:28px;
        line-height:1;
        font-weight:normal;
    }

    .barcode-text{
        font-size:13px;
        margin-top:-2px;
    }

    .left-text{
        padding-left:5px;
    }

    .desc{
        padding-left:5px;
        font-size:18px;
        font-weight:bold;
    }

    .item-barcode{
        font-family:"Libre Barcode 39";
        font-size:32px;
        padding-left:5px;
        line-height:1;
    }

    .item-text{
        padding-left:5px;
        font-size:10px;
        font-weight:bold;
    }

    .right-label{
        white-space:nowrap;
        font-size: 11px;
    }

    .right-value{
    }

    .right-value span{
        display:inline-block;
        width:100px;
        border-bottom:1px solid #000;
    }

    .line-number{
        text-align:center;
        font-weight:bold;
    }

    .digit-row td{
        vertical-align:middle;
    }

    .digit-container{
        display:flex;
        justify-content:space-between;
        padding-right:20px;
    }

   .digit{
        width:14px;
        height:14px;
        text-align:center;
        position:relative;
        font-size:12px;
        line-height:14px;
        font-weight:700;
        -webkit-text-stroke: 0.3px #000;
    }

    .dot{
        width:10px;
        height:10px;
        border:2px solid #000;
        border-radius:50%;
        background:transparent;
        position:absolute;
        left:50%;
        top:50%;
        transform:translate(-50%, -50%);
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .qty-barcode{
        font-family:"Libre Barcode 39";
        font-size:20px;
        margin-left:8px;
        position:relative;
        top:3px;
    }

    .section-line{
        border-bottom:2px solid #000;
    }

    .section-label{
        text-align:center;
        padding-top:5px;
    }

    .ttd-title{
        text-align:center;
        padding-top:10px;
    }

    .ttd-space{
        height:100px;
        vertical-align:bottom;
        text-align:center;
    }

    .ttd-line div{
        width:60%;
        margin:0 auto;
        border-top:2px solid #000;
        text-align:center;
    }

    .gunting img{
        width:100%;
        height:20px;
        margin-top:5px;
    }

    html, body {
        height: 100%;
    }

    .kartu-table {
        page-break-inside: avoid;
    }

    tr {
        page-break-inside: avoid !important;
    }

    @media print {
        @page {
            size: A4 portrait;
            margin: 0;
        }

        html, body {
            overflow: visible !important;
            height: auto !important;
            margin: 0 !important;
            padding: 0 !important;
        }

        .page-break {
            page-break-after: always;
            position: relative;
        }

        .dot {
            background:#000 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
    }
    </style>

    </head>

    <body>

    ' . $html_content . '

    </body>
    </html>';
    }

    private function digitRow($label, $info1, $info2, $activeDigit)
    {
        $html = '
    <tr class="digit-row">

        <td style="padding-left:5px;">
            ' . $label . '
        </td>

        <td colspan="3">
            ' . $info1 . ' &nbsp;&nbsp; ' . $info2 . '
        </td>

        <td colspan="2">

            <div class="digit-container">';

        foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 0] as $v) {

            $html .= '
        <div class="digit">';

            if ($activeDigit === $v) {
                $html .= '<span class="dot"></span>';
            }

            $html .= $v . '</div>';
        }

        $html .= '
            </div>

        </td>

    </tr>';

        return $html;
    }
}
