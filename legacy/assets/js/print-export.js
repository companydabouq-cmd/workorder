/**
 * تنزيل صفحات الطباعة كملف Excel (.xlsx) أو Word (.doc) — بلا مكتبات خارجية.
 *
 *   exportExcel('table.items')  جدول البنود (كل أجزائه الموزّعة على الصفحات) في ورقة Excel واحدة
 *   exportWord()                المستند كاملاً كما يظهر (الترويسة والشعار والجدول والتواقيع)
 *
 * لون رأس الجدول الأخضر نفسه في الطباعة وفي الملفين.
 */
(function () {
  'use strict';

  var HEAD_GREEN = '1E9E4A';   // اللون الخضيري لرأس الجدول (يطابق --head-green في صفحات الطباعة)

  function fileBase() {
    var t = (document.title || 'document').replace(/[\\/:*?"<>|]+/g, '-').trim();
    return t || 'document';
  }

  function download(blob, name) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = name;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }

  function cellText(el) {
    return (el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim();
  }

  /* ================= Excel ================= */

  // صفوف الجدول: الرأس من أول جدول، والبنود من كل الأجزاء (الجدول مقسّم على صفحات الطباعة)، والذيل من آخرها
  function collectRows(selector) {
    var tables = Array.prototype.slice.call(document.querySelectorAll(selector))
      .filter(function (t) { return !t.closest('.toolbar'); });
    if (!tables.length) return null;
    var rows = [];
    function take(trs, kind) {
      Array.prototype.forEach.call(trs, function (tr) {
        if (tr.querySelector('.rpt-head-cell')) return;      // الترويسة داخل رأس الجدول ليست صفاً من البيانات
        if (tr.offsetParent === null && getComputedStyle(tr).display === 'none') return;
        rows.push({ kind: kind, cells: Array.prototype.slice.call(tr.children) });
      });
    }
    var first = tables[0], last = tables[tables.length - 1];
    if (first.tHead) take(first.tHead.rows, 'head');
    tables.forEach(function (t) {
      Array.prototype.forEach.call(t.tBodies, function (b) { take(b.rows, 'body'); });
    });
    if (last.tFoot) take(last.tFoot.rows, 'foot');
    return rows;
  }

  function colName(i) {
    var s = '';
    for (i++; i > 0; i = Math.floor((i - 1) / 26)) s = String.fromCharCode(65 + (i - 1) % 26) + s;
    return s;
  }

  function xmlEsc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;')
      .replace(/[\u0000-\u0008\u000B\u000C\u000E-\u001F]/g, '');
  }

  // رقم مكتوب بالشكل المعروض (1,234.500) → قيمة رقمية؛ غير ذلك نص
  function asNumber(s) {
    var t = s.replace(/,/g, '');
    return /^-?\d+(\.\d+)?$/.test(t) && t.length < 16 ? t : null;
  }

  function buildSheet(rows) {
    var grid = [], merges = [], maxCol = 0, widths = [];
    rows.forEach(function (row, r) {
      grid[r] = grid[r] || [];
      var c = 0;
      row.cells.forEach(function (cell) {
        while (grid[r][c]) c++;
        var cs = Math.max(1, cell.colSpan || 1), rs = Math.max(1, cell.rowSpan || 1);
        var text = cellText(cell);
        for (var dr = 0; dr < rs; dr++) {
          grid[r + dr] = grid[r + dr] || [];
          for (var dc = 0; dc < cs; dc++) grid[r + dr][c + dc] = (dr || dc) ? { pad: true, kind: row.kind } : { text: text, kind: row.kind };
        }
        if (cs > 1 || rs > 1) merges.push(colName(c) + (r + 1) + ':' + colName(c + cs - 1) + (r + rs));
        if (cs === 1) widths[c] = Math.max(widths[c] || 0, Math.min(60, text.length));
        c += cs;
        maxCol = Math.max(maxCol, c);
      });
    });

    var xml = '';
    grid.forEach(function (cells, r) {
      xml += '<row r="' + (r + 1) + '">';
      for (var c = 0; c < maxCol; c++) {
        var v = cells[c] || { pad: true, kind: 'body' };
        var style = v.kind === 'head' ? 1 : (v.kind === 'foot' ? 3 : 2);
        var ref = colName(c) + (r + 1);
        if (v.pad || v.text === '') { xml += '<c r="' + ref + '" s="' + style + '"/>'; continue; }
        var n = v.kind === 'head' ? null : asNumber(v.text);
        xml += n !== null
          ? '<c r="' + ref + '" s="' + (style === 3 ? 4 : 5) + '"><v>' + n + '</v></c>'
          : '<c r="' + ref + '" s="' + style + '" t="inlineStr"><is><t xml:space="preserve">' + xmlEsc(v.text) + '</t></is></c>';
      }
      xml += '</row>';
    });

    var cols = '<cols>';
    for (var i = 0; i < maxCol; i++) {
      var w = Math.max(8, Math.min(60, (widths[i] || 8) * 1.1 + 2));
      cols += '<col min="' + (i + 1) + '" max="' + (i + 1) + '" width="' + w.toFixed(1) + '" customWidth="1"/>';
    }
    cols += '</cols>';

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
      '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" ' +
      'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">' +
      '<sheetViews><sheetView workbookViewId="0" rightToLeft="1">' +
      '<pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' +
      cols + '<sheetData>' + xml + '</sheetData>' +
      (merges.length ? '<mergeCells count="' + merges.length + '">' + merges.map(function (m) { return '<mergeCell ref="' + m + '"/>'; }).join('') + '</mergeCells>' : '') +
      '<pageSetup orientation="landscape" paperSize="9"/>' +
      '</worksheet>';
  }

  // الأنماط: 0 افتراضي، 1 رأس أخضر، 2 خلية نص، 3 ذيل (إجمالي)، 4 رقم في الذيل، 5 رقم
  var STYLES = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>' +
    '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' +
    '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.000"/></numFmts>' +
    '<fonts count="3"><font><sz val="11"/><name val="Arial"/></font>' +
    '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Arial"/></font>' +
    '<font><b/><sz val="11"/><name val="Arial"/></font></fonts>' +
    '<fills count="4"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>' +
    '<fill><patternFill patternType="solid"><fgColor rgb="FF' + HEAD_GREEN + '"/><bgColor indexed="64"/></patternFill></fill>' +
    '<fill><patternFill patternType="solid"><fgColor rgb="FFF6F0E0"/><bgColor indexed="64"/></patternFill></fill></fills>' +
    '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' +
    '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border></borders>' +
    '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>' +
    '<cellXfs count="6">' +
    '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>' +
    '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>' +
    '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1" readingOrder="2"/></xf>' +
    '<xf numFmtId="0" fontId="2" fillId="3" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1" readingOrder="2"/></xf>' +
    '<xf numFmtId="164" fontId="2" fillId="3" borderId="1" xfId="0" applyNumberFormat="1" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' +
    '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center"/></xf>' +
    '</cellXfs></styleSheet>';

  // ملف ZIP بلا ضغط (store) — يكفي لـ xlsx ويفتحه Excel مباشرة
  var CRC_TABLE = (function () {
    var t = [], c;
    for (var n = 0; n < 256; n++) {
      c = n;
      for (var k = 0; k < 8; k++) c = (c & 1) ? (0xEDB88320 ^ (c >>> 1)) : (c >>> 1);
      t[n] = c >>> 0;
    }
    return t;
  })();
  function crc32(bytes) {
    var c = 0xFFFFFFFF;
    for (var i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 0xFF] ^ (c >>> 8);
    return (c ^ 0xFFFFFFFF) >>> 0;
  }
  function zip(files) {
    var enc = new TextEncoder(), parts = [], central = [], offset = 0;
    function u16(v) { return [v & 0xFF, (v >>> 8) & 0xFF]; }
    function u32(v) { return [v & 0xFF, (v >>> 8) & 0xFF, (v >>> 16) & 0xFF, (v >>> 24) & 0xFF]; }
    files.forEach(function (f) {
      var name = enc.encode(f.name), data = enc.encode(f.data), crc = crc32(data);
      var common = [].concat(u16(20), u16(0x0800), u16(0), u16(0), u16(0x21), u32(crc), u32(data.length), u32(data.length), u16(name.length), u16(0));
      var local = new Uint8Array([].concat(u32(0x04034b50), common));
      parts.push(local, name, data);
      central.push(new Uint8Array([].concat(u32(0x02014b50), u16(20), common, u16(0), u16(0), u16(0), u32(0), u32(offset))), name);
      offset += local.length + name.length + data.length;
    });
    var cdSize = central.reduce(function (s, p) { return s + p.length; }, 0);
    var end = new Uint8Array([].concat(u32(0x06054b50), u16(0), u16(0), u16(files.length), u16(files.length), u32(cdSize), u32(offset), u16(0)));
    return new Blob(parts.concat(central, [end]), { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
  }

  window.exportExcel = function (selector) {
    var rows = collectRows(selector);
    if (!rows || !rows.length) { alert('لا يوجد جدول لتصديره.'); return; }
    var sheetName = 'البنود';
    download(zip([
      { name: '[Content_Types].xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>' },
      { name: '_rels/.rels', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>' },
      { name: 'xl/workbook.xml', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView rightToLeft="1"/></bookViews><sheets><sheet name="' + sheetName + '" sheetId="1" r:id="rId1"/></sheets></workbook>' },
      { name: 'xl/_rels/workbook.xml.rels', data: '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>' },
      { name: 'xl/styles.xml', data: STYLES },
      { name: 'xl/worksheets/sheet1.xml', data: buildSheet(rows) }
    ]), fileBase() + '.xlsx');
  };

  /* ================= Word ================= */

  // الصور تُرسم على canvas وتُضمَّن داخل الملف (Word لا يجلب صور الموقع)
  function imageToPng(img) {
    return new Promise(function (resolve) {
      function draw() {
        try {
          var w = img.naturalWidth || img.width || 120, h = img.naturalHeight || img.height || 120;
          var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
          cv.getContext('2d').drawImage(img, 0, 0, w, h);
          resolve(cv.toDataURL('image/png').split(',')[1]);
        } catch (e) { resolve(null); }
      }
      if (img.complete) {                       // انتهى تحميلها (نجح أو فشل)
        if (img.naturalWidth) draw(); else resolve(null);
        return;
      }
      img.addEventListener('load', draw, { once: true });
      img.addEventListener('error', function () { resolve(null); }, { once: true });
      setTimeout(function () { resolve(null); }, 5000);   // صورة لا تنتهي: لا يتوقف التصدير
    });
  }

  function wrapBase64(b64) { return b64.replace(/.{1,76}/g, '$&\r\n'); }

  window.exportWord = function () {
    var landscape = !!document.querySelector('.lp');
    var clone = document.body.cloneNode(true);
    Array.prototype.forEach.call(clone.querySelectorAll('.toolbar, script, .no-export'), function (el) { el.remove(); });

    var srcImgs = Array.prototype.slice.call(document.body.querySelectorAll('img'));
    var dstImgs = Array.prototype.slice.call(clone.querySelectorAll('img'));

    Promise.all(srcImgs.map(imageToPng)).then(function (pngs) {
      var images = [];
      dstImgs.forEach(function (img, i) {
        var w = srcImgs[i].getBoundingClientRect().width, h = srcImgs[i].getBoundingClientRect().height;
        if (!pngs[i]) { img.remove(); return; }
        var loc = 'image' + (i + 1) + '.png';
        img.setAttribute('src', loc);
        if (w) img.setAttribute('width', Math.round(w));
        if (h) img.setAttribute('height', Math.round(h));
        images.push({ loc: loc, data: pngs[i] });
      });

      var css = Array.prototype.map.call(document.querySelectorAll('style'), function (s) { return s.textContent; }).join('\n');
      var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40" dir="rtl">' +
        '<head><meta charset="utf-8"><title>' + xmlEsc(document.title) + '</title>' +
        '<!--[if gte mso 9]><xml><w:WordDocument><w:View>Print</w:View><w:Zoom>100</w:Zoom></w:WordDocument></xml><![endif]-->' +
        '<style>' + css + '\n' +
        'body{background:#fff !important;direction:rtl}' +
        '@page WordSection1{size:' + (landscape ? '29.7cm 21cm;mso-page-orientation:landscape' : '21cm 29.7cm') + ';margin:1.2cm}' +
        'div.WordSection1{page:WordSection1}' +
        '.page,.lp,.pp{margin:0 !important;box-shadow:none !important;width:auto !important;height:auto !important;min-height:0 !important}' +
        '</style></head><body><div class="WordSection1">' + clone.innerHTML + '</div></body></html>';

      // ملف MHTML: الصفحة والصور في ملف واحد، يفتحه Word بامتداد .doc
      var B = '----=_NextPart_print_export';
      var out = 'MIME-Version: 1.0\r\nContent-Type: multipart/related; boundary="' + B + '"; type="text/html"\r\n\r\n' +
        '--' + B + '\r\nContent-Type: text/html; charset="utf-8"\r\nContent-Transfer-Encoding: base64\r\nContent-Location: file:///C:/document.htm\r\n\r\n' +
        wrapBase64(btoa(unescape(encodeURIComponent(html)))) + '\r\n';
      images.forEach(function (im) {
        out += '--' + B + '\r\nContent-Type: image/png\r\nContent-Transfer-Encoding: base64\r\nContent-Location: file:///C:/' + im.loc + '\r\n\r\n' +
          wrapBase64(im.data) + '\r\n';
      });
      out += '--' + B + '--\r\n';
      download(new Blob([out], { type: 'application/msword' }), fileBase() + '.doc');
    });
  };
})();
