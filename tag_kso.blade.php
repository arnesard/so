<x-layouts.app title="Tag KSO">

    <style>
        @font-face {
            font-family: 'Libre Barcode 39';
            src: url('/fonts/LibreBarcode39-Regular.ttf') format('truetype');
            /* font-display: swap; */
        }

        html,
        body {
            height: 100%;
            /* Penting: memastikan tinggi penuh */
            overflow: hidden;
            /* Penting: menghilangkan scrollbar utama halaman */
        }


        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
        }

        .tag-info {
            max-height: 100vh;
            overflow-y: auto;
            padding-right: 5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }


        .label {
            font-weight: bold;
            text-align: left;
            padding-left: 6px;
        }

        .value {
            text-align: left;
            padding-left: 6px;
        }


        @media print {

            .no-print,
            .running-container,
            nav {
                display: none !important;
            }

            html,
            body {
                overflow: visible !important;
                height: auto !important;
                margin: 0;
                padding: 0;
            }

            .page-break {
                page-break-after: always;
                position: relative;
            }

            .barcode {
                position: absolute;
                top: 10px;
                right: 10px;
                text-align: center;
                font-weight: normal;
                font-family: 'Libre Barcode 39', 'Times New Roman', Times, serif;
                font-size: 40px;
                line-height: 1;
            }

        }


        .running-container {
            width: 100%;
            overflow: hidden;
            height: 28px;
            position: relative;
            /* top: -20px; */
            background: #ffebcc;
            border-radius: 0px;
            border: 1px solid #ffc107;
            display: flex;
            align-items: center;
            padding: 0 10px;
        }

        .running-text {
            display: inline-block;
            white-space: nowrap;
            color: #d35400;
            font-size: 16px;
            font-weight: bold;
            animation: scrollText 10s linear infinite;
        }

        /* scroll dari kanan → kiri, sesuai panjang teks */
        @keyframes scrollText {
            0% {
                transform: translateX(300%);
            }

            /* mulai dari kanan luar container */
            100% {
                transform: translateX(-100%);
            }
        }
    </style>

    <!-- RUNNING TEXT -->
    <div class="running-container" style="top:-20px;">
        <div class="running-text fw-bold">
            Gunakan Browser MICROSOFT EDGE untuk Proses Print TAG STOCK
        </div>
    </div>

    <div class="container" id="main-content" style="top:-13px; position:relative;">

        <!-- FORM FILTER -->
        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap  no-print">

            <!-- FORM FILTER -->
            <div class="w-75" id="tagging_form_wrapper" style="height:38px;">
                <form action="{{ route('monitoring-stock.tag-kso') }}" method="GET"
                    class="d-flex gap-2 mb-3 align-items-center">
                    <!-- PIC -->
                    <select name="pic_name" id="picName" class="form-select w-25" required>
                        <option value="">Pilih PIC</option>
                        @foreach ($pics as $pic)
                            <option value="{{ $pic['value'] }}" {{ $selectedPIC == $pic['value'] ? 'selected' : '' }}>
                                {{ $pic['text'] }}
                            </option>
                        @endforeach
                    </select>

                    <select name="doc_from" id="docFrom" class="form-select w-25" required>
                        <option value="">Pilih No. Doc Awal</option>
                        @if ($selectedPIC && isset($picNoksoMap[$selectedPIC]))
                            @foreach ($picNoksoMap[$selectedPIC] as $nokso)
                                <option value="{{ $nokso }}" {{ $docFrom == $nokso ? 'selected' : '' }}>
                                    {{ $nokso }}
                                </option>
                            @endforeach
                        @endif
                    </select>

                    <select name="doc_to" id="docTo" class="form-select w-25" required>
                        <option value="">Pilih No. Doc Akhir</option>
                        @if ($selectedPIC && isset($picNoksoMap[$selectedPIC]))
                            @foreach ($picNoksoMap[$selectedPIC] as $nokso)
                                @if (!$docFrom || $nokso >= $docFrom)
                                    <option value="{{ $nokso }}" {{ $docTo == $nokso ? 'selected' : '' }}>
                                        {{ $nokso }}
                                    </option>
                                @endif
                            @endforeach
                        @endif
                    </select>

                    <button type="submit" class="btn btn-primary">PROSES</button>
                </form>
            </div>

            <!-- TOMBOL GRUP -->
            <div class="d-flex gap-2">

                <!-- TOMBOL PRINT -->
                @if ($rows && $rows->count() > 0)
                    <div>
                        <button class="btn btn-success" id="btn-print-now">
                            PRINT KSO
                        </button>
                    </div>
                @endif

                <!-- TOMBOL KEMBALI -->
                <div class="dropdown">
                    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown"
                        aria-expanded="false">
                        Menu
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="{{ url('/monitoring-stock') }}">Monitoring Stock</a></li>
                        <li><a class="dropdown-item" href="{{ url('/monitoring-stock/data-compare') }}">Dashboard Stock
                                Opname</a></li>
                        <li><a class="dropdown-item" href="{{ url('/monitoring-stock/tag-kso') }}">Kartu Stock
                                Opname</a></li>
                        <li>
                            <a class="dropdown-item" href="{{ url('/monitoring-stock/rekap-kso') }}">
                                Rekap Stock Opname
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="no-print" style="height:4px; margin:10px 0; border-bottom:2px solid #ccc;"></div>

        <!-- TAG CONTENT -->
        <div class="tag-info">
            @if ($rows && $rows->count() > 0)
                @foreach ($rows as $t)
                    <?php
                    // --- LOGIKA EKSTRAKSI DIGIT BERDASARKAN POSISI NILAI TEMPAT ---
                    $qty_str = (string) $t->qty;
                    
                    // Fungsi helper untuk mendapatkan digit berdasarkan posisi dari akhir string
                    // Index 1 = Satuan, Index 2 = Puluhan, dst.
                    $get_digit = function ($qty_str, $index_from_end) {
                        $length = strlen($qty_str);
                        $target_index = $length - $index_from_end;
                        if ($target_index >= 0) {
                            return (int) $qty_str[$target_index]; // Kembalikan digit sebagai integer
                        }
                        return null; // Jika digit tidak ada (misalnya, angka 5 digit tapi QTY hanya 3 digit)
                    };
                    
                    $puluhan_ribu_digit = $get_digit($qty_str, 5);
                    $ribuan_digit = $get_digit($qty_str, 4);
                    $ratusan_digit = $get_digit($qty_str, 3);
                    $puluhan_digit = $get_digit($qty_str, 2);
                    $satuan_digit = $get_digit($qty_str, 1);
                    
                    // Fungsi helper untuk menentukan gaya coretan (line-through)
                    $get_style = function ($digit, $value) {
                        if ($digit !== null && $digit === (int) $value) {
                            return 'display:inline-block; text-align:center; height:8px; width:8px; border-radius:50%; border: 8px solid #000; color:transparent !important; box-sizing:border-box;';
                        }
                        return '';
                    };
                    
                    ?>
                    {{-- UNTUK ARSIP TANGERANG --}}
                    <div class="page-break" style="position: relative; min-height: 100vh;">
                        <table class="mb-1" style="width:100%; border-collapse:collapse; font-size:14px;">
                            <tr>
                                <td colspan="6"
                                    style="border:none; text-align:center; font-weight:bold; font-size:20px; font-family: 'Times New Roman', Times, serif; position: relative;">
                                    KARTU STOCK OPNAME

                                    <p
                                        style="font-size:13px; margin:0; line-height:1; font-family: 'Times New Roman', Times, serif; font-weight: normal;">
                                        {{-- TANGGAL : {{ strtoupper(now()->translatedFormat('d / F / Y')) }} --}}
                                        TANGGAL : 22 / DESEMBER / 2025
                                    </p>

                                    <p
                                        style="font-size:23px; padding-top:10px; line-height:1;margin-bottom:0; font-family: 'Times New Roman', Times, serif;">
                                        {{ substr($t->item, -1) === '0' ? 'OE' : 'OK' }}
                                    </p>

                                    <!-- Barcode di kanan atas -->
                                    <div class="barcode"
                                        style="
                                            position: absolute;
                                            top: 0;
                                            right: 0;
                                            text-align: center;
                                            font-weight: normal;
                                        ">
                                        <div style="font-size:12px; font-family: 'Times New Roman', Times, serif;">
                                            KODE DOKUMEN & NO. DOC
                                        </div>
                                        <div style="font-size:40px; line-height:1; font-family: 'Libre Barcode 39';">
                                            *{{ $t->nokso }}*
                                        </div>
                                        <div
                                            style="font-size:12px; font-family: 'Times New Roman', Times, serif; position: relative; top: -15px;">
                                            {{ $t->nokso }}
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            {{-- PT GAJAH TUNGGAL Tbk, BARANG MILIK PLANT --}}
                            <tr>
                                <td colspan="4"
                                    style="
                                                    border:none;
                                                    padding-bottom:1px;
                                                    text-align:left;
                                                    text-indent:5px;
                                                    font-family: 'Times New Roman', Times, serif;
                                                ">
                                    PT GAJAH TUNGGAL Tbk
                                </td>

                                <td
                                    style="
                                                    border:none;
                                                    padding-bottom:1px;
                                                    text-align:left;
                                                    text-indent:5px;
                                                    white-space: nowrap; /* <--- ini biar ga pecah jadi 2 baris */
                                                    font-family: 'Times New Roman', Times, serif;
                                                ">
                                    BARANG MILIK PLANT
                                </td>

                                <td
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                        ">
                                    <span
                                        style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:2px;
                                            ">
                                        : <b>B</b>
                                    </span>
                                </td>

                            </tr>

                            {{-- DESCRIPTION, NO. DOCUMENT --}}
                            <tr>
                                <td colspan="4"
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            vertical-align: top;
                                            text-indent:5px;
                                            font-weight:bold; font-size:18px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    <b>{{ $t->deskripsi }}</b>
                                </td>
                                <td
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    NO. DOCUMENT
                                </td>

                                <td
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                        ">
                                    <span
                                        style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:2px;
                                            ">
                                        : <b>{{ $t->nokso }}</b>
                                </td>
                                </span>
                                </td>

                            </tr>

                            {{-- NO INDEX --}}
                            <tr>
                                <td colspan="4"
                                    style="
                                            border:none;
                                            font-size:50px;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Libre Barcode 39', cursive;
                                            ">
                                    *{{ $t->item }}*
                                </td>
                                <td
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            vertical-align:top;
                                            justify-content:top;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    NO. INDEX
                                </td>
                                <td
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                            vertical-align:middle;
                                        ">
                                    <span
                                        style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:1px;      /* hapus padding bawah */
                                                position: relative;
                                                top: -20px;            /* geser span 10px ke atas */
                                            ">
                                        :
                                    </span>
                                </td>
                            </tr>

                            {{-- ITEM, LINE NUMBER --}}
                            <tr>
                                <td colspan="4"
                                    style="
                                            border:none;
                                            font-weight:bold;
                                            padding-bottom:1px;
                                            font-size:10px;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    {{ $t->item }}
                                </td>

                                <td colspan="2"
                                    style="
                                            border:none;
                                            font-weight:bold;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    LINE NUMBER
                                </td>
                            </tr>

                            {{-- BARIS 4: GRADE dan PULUHAN RIBU --}}
                            <tr>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    GRADE
                                </td>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    : <b>{{ substr($t->item, -1) === '0' ? 'OE' : 'OK' }}</b>
                                </td>
                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    PLANT : <b>B</b>
                                </td>
                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                    <div id="puluhan_ribu"
                                        style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 1) }}">1</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 2) }}">2</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 3) }}">3</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 4) }}">4</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 5) }}">5</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 6) }}">6</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 7) }}">7</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 8) }}">8</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 9) }}">9</div>
                                        <div class="" style="{{ $get_style($puluhan_ribu_digit, 0) }}">0</div>
                                    </div>
                                </td>
                            </tr>

                            {{-- BARIS 5: JENIS dan RIBUAN --}}
                            <tr>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    JENIS
                                </td>
                                <td colspan="3"
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    :
                                </td>

                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                    <div id="ribuan"
                                        style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                        <div class="" style="{{ $get_style($ribuan_digit, 1) }}">1</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 2) }}">2</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 3) }}">3</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 4) }}">4</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 5) }}">5</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 6) }}">6</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 7) }}">7</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 8) }}">8</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 9) }}">9</div>
                                        <div class="" style="{{ $get_style($ribuan_digit, 0) }}">0</div>
                                    </div>
                                </td>
                            </tr>

                            {{-- BARIS 6: UKURAN dan RATUSAN --}}
                            <tr>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    UKURAN
                                </td>
                                <td colspan="3"
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    : <b>{{ $t->deskripsi }}</b>
                                </td>

                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                    <div id="ratusan"
                                        style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                        <div class="" style="{{ $get_style($ratusan_digit, 1) }}">1</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 2) }}">2</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 3) }}">3</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 4) }}">4</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 5) }}">5</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 6) }}">6</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 7) }}">7</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 8) }}">8</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 9) }}">9</div>
                                        <div class="" style="{{ $get_style($ratusan_digit, 0) }}">0</div>
                                    </div>
                                </td>
                            </tr>

                            {{-- BARIS 7: CODE dan PULUHAN --}}
                            <tr>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    CODE
                                </td>
                                <td colspan="3"
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    : <b>{{ $t->item }}</b>
                                </td>

                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                    <div id="puluhan"
                                        style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                        <div class="" style="{{ $get_style($puluhan_digit, 1) }}">1</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 2) }}">2</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 3) }}">3</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 4) }}">4</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 5) }}">5</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 6) }}">6</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 7) }}">7</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 8) }}">8</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 9) }}">9</div>
                                        <div class="" style="{{ $get_style($puluhan_digit, 0) }}">0</div>
                                    </div>
                                </td>
                            </tr>

                            {{-- BARIS 8: JUMLAH dan SATUAN --}}
                            <tr>
                                <td
                                    style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                    JUMLAH
                                </td>
                                <td colspan="3"
                                    style="
                                        border:none;
                                        padding-bottom:1px;
                                        text-align:left;
                                        text-indent:5px;
                                        font-family: 'Times New Roman', Times, serif;
                                        white-space: nowrap;
                                    ">
                                    : <b>{{ number_format($t->qty, 0, ',', '.') }} PCS</b>
                                    <span
                                        style="
                                            font-family: 'Libre Barcode 39';
                                            font-size:30px;
                                            line-height:1;
                                            font-weight: normal;
                                            margin-left:10px;
                                            position: relative;
                                            top: 5px;
                                        ">
                                        *{{ $t->qty }}*
                                    </span>
                                </td>


                                <td colspan="2"
                                    style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                    <div id="satuan"
                                        style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                        <div class="" style="{{ $get_style($satuan_digit, 1) }}">1</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 2) }}">2</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 3) }}">3</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 4) }}">4</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 5) }}">5</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 6) }}">6</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 7) }}">7</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 8) }}">8</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 9) }}">9</div>
                                        <div class="" style="{{ $get_style($satuan_digit, 0) }}">0</div>
                                    </div>
                                </td>
                            </tr>

                            {{-- LEMBAR UNTUK GUDANG --}}
                            <tr style="border-bottom: 2px solid black;">
                                <td colspan="4"></td>
                                <td colspan="2"
                                    style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                    LEMBAR UNTUK GUDANG</td>
                            </tr>

                            {{-- DIHITUNG OLEH, DIPERIKSA OLEH  --}}
                            <tr>
                                <td colspan="2"
                                    style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                    DIHITUNG OLEH</td>
                                <td colspan="2" style="padding-top:25px "></td>
                                <td colspan="2"
                                    style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                    DIPERIKSA OLEH</td>
                            </tr>

                            {{-- SPASI TTD --}}
                            <tr>
                                <td style="width:10%;padding-bottom:30px"></td>
                                <td style="width:10%;padding-bottom:30px"></td>
                                <td style="width:20%;padding-bottom:30px"></td>
                                <td style="width:20%;padding-bottom:30px"></td>
                                <td style="width:20%;padding-bottom:30px"></td>
                                <td style="width:20%;padding-bottom:30px"></td>
                            </tr>
                            {{-- NAMA PETUGAS --}}
                            <tr>
                                <td colspan="2"
                                    style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            ">
                                    <div
                                        style="margin:2px auto; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                        <b>{{ $t->oprname }}</b>
                                    </div>

                                </td>
                                <td colspan="2" style="padding-top:15px "></td>
                                <td colspan="2"
                                    style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px; ">
                                    <div
                                        style="margin:1px auto; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                        <b>{{ $t->nama_auditor ?? '..........' }}</b>
                                    </div>
                                </td>
                            </tr>

                            {{-- IDENTITAS --}}
                            <tr>
                                <td colspan="2"
                                    style="border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                    <div
                                        style="width:80%; margin:1px auto; border-top:2px solid #000; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                        GUDANG BAN</b>
                                    </div>
                                </td>
                                <td colspan="2"></td>
                                <td colspan="2"
                                    style="border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                    <div
                                        style="width:60%; margin:1px auto; border-top:2px solid #000; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                        TEAM S.O./AUDITOR
                                    </div>
                                </td>
                            </tr>

                            {{-- GAMBAR --}}
                            <tr>
                                {{-- <td colspan="6">
                                        <img src="/images/garis_gunting.png" alt="Garis Gunting"
                                            style="width: 100%; height: 50px;" />
                                    </td> --}}
                                <td colspan="6" style="position: relative;">
                                    <img src="/images/garis_gunting.png" alt="Garis Gunting"
                                        style="width: 100%; height: 50px; position: relative; top: 14px;" />
                                </td>

                            </tr>


                        </table>
                    </div>
                    {{-- UNTUK ARSIP JAKARTA --}}
                    <table class="mb-1" style="width:100%; border-collapse:collapse; font-size:14px;">
                        <!-- Header TAG STOCK -->
                        <tr>
                            <td colspan="6"
                                style="border:none;text-align:center; font-weight:bold; font-size:20px; font-family: 'Times New Roman', Times, serif;">
                                KARTU STOCK OPNAME

                                {{-- Font di sini akan mengikuti TD, tapi kita pastikan lagi untuk P --}}
                                <p
                                    style="font-size:13px; margin:0; line-height:1; font-family: 'Times New Roman', Times, serif; font-weight: normal;">
                                    {{-- TANGGAL :
                                    {{ strtoupper(now()->translatedFormat('d / F / Y')) }} --}}
                                    TANGGAL : 22 / DESEMBER / 2025
                                </p>

                                <p
                                    style="font-size:23px; padding-top:10px; line-height:1;margin-bottom:0; font-family: 'Times New Roman', Times, serif;">
                                    {{ substr($t->item, -1) === '0' ? 'OE' : 'OK' }}
                                </p>
                            </td>
                        </tr>

                        {{-- PT GAJAH TUNGGAL Tbk, BARANG MILIK PLANT --}}
                        <tr>
                            <td colspan="4"
                                style="
                                                    border:none;
                                                    padding-bottom:1px;
                                                    text-align:left;
                                                    text-indent:5px;
                                                    font-family: 'Times New Roman', Times, serif;
                                                ">
                                PT GAJAH TUNGGAL Tbk
                            </td>

                            <td
                                style="
                                                    border:none;
                                                    padding-bottom:1px;
                                                    text-align:left;
                                                    text-indent:5px;
                                                    white-space: nowrap; /* <--- ini biar ga pecah jadi 2 baris */
                                                    font-family: 'Times New Roman', Times, serif;
                                                ">
                                BARANG MILIK PLANT
                            </td>

                            <td
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                        ">
                                <span
                                    style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:2px;
                                            ">
                                    : <b>B</b>
                                </span>
                            </td>
                        </tr>

                        {{-- DESCRIPTION, NO. DOCUMENT --}}
                        <tr>
                            <td colspan="4"
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            vertical-align: top;
                                            text-indent:5px;
                                            font-weight:bold; font-size:18px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                <b>{{ $t->deskripsi }}</b>
                            </td>
                            <td
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                NO. DOCUMENT
                            </td>
                            <td
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                        ">
                                <span
                                    style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:2px;
                                            ">
                                    : <b>{{ $t->nokso }}</b>
                            </td>
                            </span>
                            </td>
                        </tr>

                        {{-- NO INDEX --}}
                        <tr>
                            <td colspan="4"
                                style="
                                            border:none;
                                            font-size:50px;
                                            color:transparent;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Libre Barcode 39', cursive;
                                            ">
                                *{{ $t->item }}*
                            </td>
                            <td
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            vertical-align:top;
                                            justify-content:top;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                NO. INDEX
                            </td>
                            <td
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:left;
                                            white-space:nowrap;
                                            font-family:'Times New Roman', Times, serif;
                                            vertical-align:middle;
                                        ">
                                <span
                                    style="
                                                display:inline-block;
                                                width:100px;
                                                margin-left:60px;      /* geser garis ke kanan */
                                                border-bottom:1px solid #000;
                                                padding-bottom:1px;      /* hapus padding bawah */
                                                position: relative;
                                                top: -20px;            /* geser span 10px ke atas */
                                            ">
                                    :
                                </span>
                            </td>
                        </tr>

                        {{-- ITEM, LINE NUMBER --}}
                        <tr>
                            <td colspan="4"
                                style="
                                            border:none;
                                            font-weight:bold;
                                            padding-bottom:1px;
                                            font-size:10px;
                                            text-align:left;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                {{ $t->item }}
                            </td>

                            <td colspan="2"
                                style="
                                            border:none;
                                            font-weight:bold;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                LINE NUMBER
                            </td>
                        </tr>

                        {{-- BARIS 4: GRADE dan PULUHAN RIBU --}}
                        <tr>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                GRADE
                            </td>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                : <b>{{ substr($t->item, -1) === '0' ? 'OE' : 'OK' }}</b>
                            </td>
                            <td colspan="2"
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                PLANT : <b>B</b>
                            </td>
                            <td colspan="2"
                                style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                <div id="puluhan_ribu"
                                    style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 1) }}">1</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 2) }}">2</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 3) }}">3</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 4) }}">4</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 5) }}">5</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 6) }}">6</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 7) }}">7</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 8) }}">8</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 9) }}">9</div>
                                    <div class="" style="{{ $get_style($puluhan_ribu_digit, 0) }}">0</div>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS 5: JENIS dan RIBUAN --}}
                        <tr>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                JENIS
                            </td>
                            <td colspan="3"
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                :
                            </td>

                            <td colspan="2"
                                style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                <div id="ribuan"
                                    style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                    <div class="" style="{{ $get_style($ribuan_digit, 1) }}">1</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 2) }}">2</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 3) }}">3</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 4) }}">4</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 5) }}">5</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 6) }}">6</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 7) }}">7</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 8) }}">8</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 9) }}">9</div>
                                    <div class="" style="{{ $get_style($ribuan_digit, 0) }}">0</div>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS 6: UKURAN dan RATUSAN --}}
                        <tr>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                UKURAN
                            </td>
                            <td colspan="3"
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                : <b>{{ $t->deskripsi }}</b>
                            </td>

                            <td colspan="2"
                                style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                <div id="ratusan"
                                    style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                    <div class="" style="{{ $get_style($ratusan_digit, 1) }}">1</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 2) }}">2</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 3) }}">3</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 4) }}">4</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 5) }}">5</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 6) }}">6</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 7) }}">7</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 8) }}">8</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 9) }}">9</div>
                                    <div class="" style="{{ $get_style($ratusan_digit, 0) }}">0</div>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS 7: CODE dan PULUHAN --}}
                        <tr>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                CODE
                            </td>
                            <td colspan="3"
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                : <b>{{ $t->item }}</b>
                            </td>

                            <td colspan="2"
                                style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                <div id="puluhan"
                                    style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                    <div class="" style="{{ $get_style($puluhan_digit, 1) }}">1</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 2) }}">2</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 3) }}">3</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 4) }}">4</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 5) }}">5</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 6) }}">6</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 7) }}">7</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 8) }}">8</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 9) }}">9</div>
                                    <div class="" style="{{ $get_style($puluhan_digit, 0) }}">0</div>
                                </div>
                            </td>
                        </tr>

                        {{-- BARIS 8: JUMLAH dan SATUAN --}}
                        <tr>
                            <td
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                JUMLAH
                            </td>
                            <td colspan="3"
                                style="
                                                        border:none;
                                                        padding-bottom:1px;
                                                        text-align:left;
                                                        text-indent:5px;
                                                        font-family: 'Times New Roman', Times, serif;
                                                        ">
                                : <b>{{ number_format($t->qty, 0, ',', '.') }} PCS</b>
                            </td>

                            <td colspan="2"
                                style="
                                                        border:none;
                                                        font-weight:bold;
                                                        padding-bottom:1px;
                                                        text-align:center;
                                                        text-indent:5px;
                                                        ">
                                <div id="satuan"
                                    style="display:flex; justify-content:space-between; font-weight:bold; text-indent:5px;text-align:center;padding-right:25px">
                                    <div class="" style="{{ $get_style($satuan_digit, 1) }}">1</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 2) }}">2</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 3) }}">3</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 4) }}">4</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 5) }}">5</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 6) }}">6</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 7) }}">7</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 8) }}">8</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 9) }}">9</div>
                                    <div class="" style="{{ $get_style($satuan_digit, 0) }}">0</div>
                                </div>
                            </td>
                        </tr>

                        {{-- LEMBAR UNTUK GUDANG --}}
                        <tr style="border-bottom: 2px solid black;">
                            <td colspan="4"></td>
                            <td colspan="2"
                                style="
                                            border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;
                                        ">
                                LEMBAR UNTUK ARSIP</td>
                        </tr>

                        {{-- DIHITUNG OLEH, DIPERIKSA OLEH  --}}
                        <tr>
                            <td colspan="2"
                                style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                DIHITUNG OLEH</td>
                            <td colspan="2" style="padding-top:25px "></td>
                            <td colspan="2"
                                style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                DIPERIKSA OLEH</td>
                        </tr>

                        {{-- SPASI TTD --}}
                        <tr>
                            <td style="width:10%;padding-bottom:30px"></td>
                            <td style="width:10%;padding-bottom:30px"></td>
                            <td style="width:20%;padding-bottom:30px"></td>
                            <td style="width:20%;padding-bottom:30px"></td>
                            <td style="width:20%;padding-bottom:30px"></td>
                            <td style="width:20%;padding-bottom:30px"></td>
                        </tr>
                        {{-- NAMA PETUGAS --}}
                        <tr>
                            <td colspan="2"
                                style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            ">
                                <div
                                    style="margin:2px auto; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                    <b>{{ $t->oprname }}</b>
                                </div>

                            </td>
                            <td colspan="2" style="padding-top:15px "></td>
                            <td colspan="2"
                                style="padding-top:15px;border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px; ">
                                <div
                                    style="margin:1px auto; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                    <b>{{ $t->nama_auditor ?? '..........' }}</b>
                                </div>
                            </td>
                        </tr>

                        {{-- IDENTITAS --}}
                        <tr>
                            <td colspan="2"
                                style="border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                <div
                                    style="width:80%; margin:1px auto; border-top:2px solid #000; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                    GUDANG BAN</b>
                                </div>
                            </td>
                            <td colspan="2"></td>
                            <td colspan="2"
                                style="border:none;
                                            padding-bottom:1px;
                                            text-align:center;
                                            text-indent:5px;
                                            font-family: 'Times New Roman', Times, serif;">
                                <div
                                    style="width:60%; margin:1px auto; border-top:2px solid #000; text-align:center; font-family: 'Times New Roman', Times, serif;">
                                    TEAM S.O./AUDITOR
                                </div>
                            </td>
                        </tr>


                    </table>
                @endforeach
            @else
                <p>Tidak ada data untuk ditampilkan, lihat kembali no. doc yang akan diproses</p>
            @endif
        </div>
    </div>

    <!-- JAVASCRIPT -->
    <script>
        const picNoksoMap = @json($picNoksoMap);
        const picName = document.getElementById('picName');
        const docFrom = document.getElementById('docFrom');
        const docTo = document.getElementById('docTo');

        // Update DOC dropdown saat PIC berubah
        picName.addEventListener('change', function() {
            const selectedPIC = this.value;
            docFrom.innerHTML = '<option value="">Pilih No. Doc Awal</option>';
            docTo.innerHTML = '<option value="">Pilih No. Doc Akhir</option>';

            if (!selectedPIC || !picNoksoMap[selectedPIC]) return;

            picNoksoMap[selectedPIC].forEach(nokso => {
                docFrom.innerHTML += `<option value="${nokso}">${nokso}</option>`;
                docTo.innerHTML += `<option value="${nokso}">${nokso}</option>`;
            });
        });

        // DOC TO >= DOC FROM
        docFrom.addEventListener('change', function() {
            const selectedPIC = picName.value;
            const from = this.value;

            docTo.innerHTML = '<option value="">Pilih No. Doc Akhir</option>';

            if (!selectedPIC || !picNoksoMap[selectedPIC]) return;

            picNoksoMap[selectedPIC]
                .filter(nokso => nokso >= from)
                .forEach(nokso => {
                    docTo.innerHTML += `<option value="${nokso}">${nokso}</option>`;
                });
        });

        // PRINT BIASA
        document.getElementById('btn-print-now').addEventListener('click', function() {
            const tables = Array.from(document.querySelectorAll('.tag-info table'));
            const preview = window.open('', '_blank', 'width=1200,height=800');

            // Bagi tables menjadi grup 4 per halaman
            const pages = [];
            for (let i = 0; i < tables.length; i += 4) {
                pages.push(tables.slice(i, i + 4));
            }

            const pagesHTML = pages.map(pageTables => {
                return `<div class="print-page">
                    ${pageTables.map(t => `<div class="form-card">${t.outerHTML}</div>`).join('')}
                </div>`;
            }).join('');

            preview.document.write(`
                <html>
                <head>
                    <title>Print Preview</title>
                    <style>
                        @font-face {
                            font-family: 'Libre Barcode 39';
                            src: url('/fonts/LibreBarcode39-Regular.ttf') format('truetype');
                            /* font-display: swap; */
                        }

                        @page {
                            size: A4 portrait;
                            margin-top: 10mm;
                            margin-right: 5mm;
                            margin-left: 5mm;
                            margin-bottom: 0mm; /* opsional, bisa diubah atau dihapus */
                        }


                        body {
                            margin-top: 0px;
                            padding: 0;
                            font-family: Arial, sans-serif;
                        }

                        .print-page {
                            display: grid;
                            grid-template-columns: repeat(1, 1fr); /* dua kolom → kiri dan kanan */
                            grid-auto-rows: 142mm; /* tinggi setiap baris card */
                            padding: auto; /* jarak dari tepi halaman */
                            box-sizing: border-box;
                            page-break-after: always;
                        }

                        table {
                            width: 100%;
                            border-collapse: collapse;
                            height: 100%;
                        }

                        .digit {
                            display: inline-block;
                            width: 14px;
                            height: 14px;
                            line-height: 14px;
                            text-align: center;
                            border-radius: 50%;
                            border: 2px solid #000;
                            font-size: 10px;
                        }

                        .digit.active {
                            background: #000;
                            color: #000;
                        }










                    </style>
                </head>
                <body>
                    ${pagesHTML}
                </body>
                </html>
            `);

            preview.document.close();
            preview.document.fonts.ready.then(() => {
                setTimeout(() => {
                    preview.focus();
                    preview.print();
                }, 100);
            });
        });
        // Blok Ctrl+P
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                alert(
                    "Metode Fitur print Seperti Ini tidak diizinkan,\n" +
                    "Gunakan Tombol Print Preview Pada Halaman WEB.\n" + "\n" +
                    "AdiSaputra"
                );
            }
        });

        // Tangkap print dari menu browser (Edge compatible)
        window.onbeforeprint = function() {
            alert(
                "Metode Fitur print Seperti Ini tidak diizinkan,\n" +
                "Gunakan Tombol Print Preview Pada Halaman WEB.\n" + "\n" +
                "AdiSaputra"
            );

            // Solusi untuk Edge: reload halaman agar batal masuk mode print
            setTimeout(() => {
                location.reload();
            }, 10);
        };
    </script>
</x-layouts.app>
