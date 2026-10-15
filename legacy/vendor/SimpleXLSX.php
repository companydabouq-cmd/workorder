<?php
/**
 * SimpleXLSX - قارئ خفيف لملفات xlsx بدون مكتبات خارجية
 * مبني على ZipArchive + SimpleXML (متوفران في معظم استضافات PHP)
 * نسخة مبسطة مخصصة لهذا المشروع (قراءة أول ورقة كصفوف)
 */
class SimpleXLSX {
    private $sheetXML;
    private $sharedStrings = [];
    public $error = '';
    /** سبب آخر فشل في parse() — لأن parse() ترجع false فيضيع $error معها */
    public static $lastError = '';

    public static function parse($filename) {
        $x = new self();
        if (!$x->load($filename)) { self::$lastError = $x->error; return false; }
        return $x;
    }

    private function load($filename) {
        if (!class_exists('ZipArchive')) { $this->error = 'ZipArchive غير متوفر على الخادم'; return false; }
        $zip = new ZipArchive();
        if ($zip->open($filename) !== true) { $this->error = 'تعذّر فتح الملف (ليس xlsx صالحاً)'; return false; }

        // shared strings
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $xml = simplexml_load_string($ss);
            if ($xml) {
                foreach ($xml->si as $si) {
                    $this->sharedStrings[] = $this->siText($si);
                }
            }
        }
        // first sheet
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheet === false) {
            // ابحث عن أول ورقة
            for ($i=0; $i<$zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('#xl/worksheets/sheet\d+\.xml$#', $n)) { $sheet = $zip->getFromName($n); break; }
            }
        }
        $zip->close();
        if ($sheet === false) { $this->error = 'لا توجد ورقة بيانات'; return false; }
        $this->sheetXML = simplexml_load_string($sheet);
        if (!$this->sheetXML) { $this->error = 'تعذّر قراءة الورقة'; return false; }
        return true;
    }

    private function siText($si) {
        $t = '';
        if (isset($si->t)) $t .= (string)$si->t;
        if (isset($si->r)) foreach ($si->r as $r) $t .= (string)$r->t;
        return $t;
    }

    /** إرجاع كل الصفوف كمصفوفة ثنائية */
    public function rows() {
        $out = [];
        if (!$this->sheetXML || !isset($this->sheetXML->sheetData)) return $out;
        foreach ($this->sheetXML->sheetData->row as $row) {
            $cells = [];
            $maxCol = 0;
            $rowCells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $col = $this->colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string)$c['t'];
                $val = isset($c->v) ? (string)$c->v : '';
                if ($type === 's') {
                    $idx = (int)$val;
                    $val = $this->sharedStrings[$idx] ?? '';
                } elseif ($type === 'inlineStr' && isset($c->is)) {
                    $val = $this->siText($c->is);
                }
                $rowCells[$col] = $val;
                if ($col > $maxCol) $maxCol = $col;
            }
            for ($i=0; $i<=$maxCol; $i++) $cells[$i] = $rowCells[$i] ?? '';
            $out[] = $cells;
        }
        return $out;
    }

    private function colIndex($letters) {
        $letters = strtoupper($letters);
        $n = 0;
        for ($i=0; $i<strlen($letters); $i++) {
            $n = $n*26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }
}
