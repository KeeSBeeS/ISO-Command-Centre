@extends('layouts.app')
@section('title','Signature Builder | ISO Admin')
@section('page_title','Signature Builder')
@section('content')
<style>
    .sigb-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start}
    .sigb-grid .form-row{margin-bottom:12px}
    .sigb-check{margin:4px 0 14px;cursor:pointer;font-size:13px;font-weight:700;line-height:1.45;color:#dff8d6}
    .sigb-check input:focus-visible{outline:2px solid var(--brand2);outline-offset:2px}
    .sigb-buttons{display:grid;gap:10px;margin-top:6px}
    .sigb-msg{min-height:18px;font-size:13px;font-weight:800;line-height:1.45;color:var(--brand2);margin-top:10px}
    .sigb-msg:empty{min-height:0;margin-top:0}
    .sigb-msg.warn{color:var(--warn)}
    .sigb-status{display:grid;gap:8px;margin-top:12px}
    .sigb-stat{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:14px;background:rgba(18,163,116,.1);border:1px solid rgba(18,163,116,.28);font-size:13px;line-height:1.5;color:#cfeee0}
    .sigb-stat::before{content:"";flex:none;width:7px;height:7px;margin-top:7px;border-radius:50%;background:var(--brand2);box-shadow:0 0 0 4px rgba(139,220,101,.1)}
    .sigb-stat.warn{background:rgba(245,185,76,.1);border-color:rgba(245,185,76,.35);color:#ffe4ad}
    .sigb-stat.warn::before{background:var(--warn);box-shadow:0 0 0 4px rgba(245,185,76,.12)}
    .sigb-stat strong{color:#fff;font-weight:850}
    .sigb-nowrap{white-space:nowrap}
    .sigb-stat a,.sigb-warnings a{color:var(--brand2);font-weight:800}
    .sigb-warnings{margin:0;padding:10px 12px 10px 30px;border-radius:14px;background:rgba(245,185,76,.07);border:1px solid rgba(245,185,76,.3);font-size:12.5px;line-height:1.5;color:#ffe4ad}
    .sigb-warnings li+li{margin-top:4px}
    .sigb-frame{position:relative;background:#fff;border-radius:var(--radius);padding:25px;overflow:auto;box-shadow:var(--shadow)}
    .sigb-frame-title{font:bold 10px Arial,Helvetica,sans-serif;letter-spacing:1.6px;color:#5E716E;margin:0 0 22px}
    .sigb-fit{overflow:hidden}
    .sigb-holder{display:block;width:640px;max-width:none;height:340px;border:0;background:#fff;transform-origin:0 0}
    .page-head > .actions{flex:none}
    .page-head > .actions .btn{white-space:nowrap}
    /* The signature is 640px wide. Below 1400px the form column narrows and the frame padding shrinks, so the
       preview stays 1:1 down to about 1366px and is scaled to fit (fitPreview, at least ~0.86) down to 1240px.
       Narrower: one column with the preview first. It is never cut off. */
    @media(max-width:1400px){.sigb-grid{grid-template-columns:300px minmax(0,1fr)}.sigb-frame{padding:16px}.sigb-frame-title{margin-bottom:16px}}
    @media(max-width:1239px){.sigb-grid{grid-template-columns:1fr}.sigb-frame{order:-1}}
    @media(max-width:560px){.sigb-frame{padding:12px}.sigb-frame-title{margin-bottom:12px}}
</style>

<div class="page-head">
    <div class="page-head-main">
        <div class="page-head-icon">✉️</div>
        <div>
            <h2>ISO-Reliability Partners Email Signature Builder</h2>
            <p>Select an employee or type their details, then copy the formatted signature or download it as HTML.</p>
        </div>
    </div>
    <div class="actions">
        <a class="btn" href="{{ route('signature_builder.logos') }}">🏷️ Manage Logos</a>
    </div>
</div>

<div class="sigb-grid">
    <div class="card">
        <h2>Employee details</h2>

        <div class="form-row">
            <label for="sigbEmployee">Load from employee (optional)</label>
            <select id="sigbEmployee">
                <option value="">— Choose an employee —</option>
                @foreach($employees as $employee)
                    <option value="{{ $employee['id'] }}">{{ $employee['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-row">
            <label for="personName">Full name</label>
            <input id="personName" placeholder="e.g. Jane Smith" autocomplete="off">
        </div>
        <div class="form-row">
            <label for="personTitle">Job title</label>
            <input id="personTitle" placeholder="e.g. Technical Sales Engineer" autocomplete="off">
        </div>
        <div class="form-row">
            <label for="personMobile">Mobile</label>
            <input id="personMobile" placeholder="e.g. +27 82 000 0000" autocomplete="off">
        </div>
        <div class="form-row">
            <label for="personEmail">Email address</label>
            <input id="personEmail" placeholder="e.g. jane@iiso.co.za" autocomplete="off">
        </div>

        <label class="check sigb-check" for="sigbNoBg">
            <input type="checkbox" id="sigbNoBg">
            <span>Preview without background image (how Gmail / Outlook editors show it)</span>
        </label>

        <div class="sigb-buttons">
            <button class="btn primary full" id="copyBtn" type="button">Copy formatted signature</button>
            <button class="btn full" id="downloadBtn" type="button">Download personalised HTML</button>
        </div>
        <div id="sigbMsg" class="sigb-msg" aria-live="polite"></div>

        <div class="sigb-status">
            <div id="sigbSize" class="sigb-stat" aria-live="polite"><span>Measuring the signature size…</span></div>
            @if($companyLogo)
                <div id="sigbCompany" class="sigb-stat"><span>Company logo uploaded · loading…</span></div>
            @else
                <div id="sigbCompany" class="sigb-stat warn"><span>No company logo uploaded — upload it under <a href="{{ route('signature_builder.logos') }}">Manage Logos</a>. Until then the signature shows <span class="sigb-nowrap">“ISO-Reliability Partners”</span> as text.</span></div>
            @endif
            <div id="sigbLogoSummary" class="sigb-stat" aria-live="polite"><span>Loading brand logos…</span></div>
            <ul id="sigbWarnings" class="sigb-warnings" aria-live="polite" hidden></ul>
        </div>
        <p class="muted small">Brand logos are managed centrally under <a href="{{ route('signature_builder.logos') }}" style="color:var(--brand2);font-weight:800">Manage Logos</a>. Some email clients ask the recipient to allow remote images before logos display.</p>
    </div>

    <div class="sigb-frame">
        <div class="sigb-frame-title" id="sigbFrameTitle">LIVE EMAIL SIGNATURE PREVIEW</div>
        <div class="sigb-fit" id="sigbFit"><iframe class="sigb-holder" id="signature" title="Email signature preview" scrolling="no"></iframe></div>
    </div>
</div>

<script>
(function () {
    'use strict';
    var employees = @json($employees);
    var logos = @json($logos);
    var companyLogo = @json($companyLogo);
    var background = @json($background);
    var manageLogosUrl = @json(route('signature_builder.logos'));
@verbatim
    var $ = function (id) { return document.getElementById(id); };
    var FALLBACK_LINK = 'https://iiso.co.za/';
    var COMPANY_BOX_W = 200, COMPANY_BOX_H = 64;
    var LOGO_BOX_W = 130, LOGO_BOX_H = 42, PER_ROW = 4;
    var TEXT_W = 328;                                 // name + title column (x 248 to 576)
    var SPAN_W = 276;                                 // full-width contact value of a stacked CONTACT_ROW
    var EMAIL_NORMAL_MAX = 124, MOBILE_NORMAL_MAX = 92;   // wider -> stacked CONTACT_ROWs
    var TILE_TEXT_W = 136;                            // longest unbroken word in a text partner tile
    var NAME_SIZES = [23, 20, 18];
    var GMAIL_LIMIT = 10000;
    var GMAIL_SAFE = 9700;                            // warn from here: an editor's own paste rewrite adds ~100 characters
    var EMAIL_RE = /^[A-Za-z0-9.!#$%&'*+\/=?^_`{|}~-]+@[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?(?:\.[A-Za-z0-9](?:[A-Za-z0-9-]{0,61}[A-Za-z0-9])?)+$/;
    var ZW = '\u200B', BREAK_AFTER = '.-_/,';
    var logoState = {};
    var companyState = { status: companyLogo ? 'loading' : 'none' };

    // ---- Signature design "C - Bold brand banner": template + snippets ------------------------
    // From the design's template.html / cells.html (contact rows composed per value). Tokens are {{NAME}} style and are filled
    // by put() with a function replacer, so "$&" or "{{X}}" typed into a field is inserted literally.
    var TEMPLATE = [
        '<table role="presentation" cellpadding="0" cellspacing="0" width="640" bgcolor="#FFFFFF" style="width:640px">',
        '<tr><td width="640" bgcolor="#0D4B4A" background="{{BG_URL}}" style="background-image:url(\'{{BG_URL}}\');background-repeat:no-repeat;background-position:left top;background-size:640px 132px">{{VML_OPEN}}<table cellpadding="0" cellspacing="0" width="640"><tr>',
        '<td width="223" valign="top" style="padding:16px 8px 9px 17px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:13px;font-weight:bold;letter-spacing:0.8px;color:#A9EDCB;mso-line-height-rule:exactly">CLEANER OIL <span style="color:#8BDC65">&bull;</span> BETTER LUBRICATION<br>LONGER EQUIPMENT LIFE</td>',
        '<td width="392" rowspan="2" valign="top" style="padding:16px 0 10px"><table cellpadding="0" cellspacing="0" width="392">',
        '<tr><td colspan="4" style="font-family:Arial,Helvetica,sans-serif;font-size:{{NAME_SIZE}}px;line-height:{{NAME_LH}}px;font-weight:bold;color:#FFFFFF;mso-line-height-rule:exactly">{{NAME}}</td></tr>',
        '{{TITLE_ROW}}',
        '<tr><td colspan="5" style="padding:9px 0 10px"><table cellpadding="0" cellspacing="0" width="392"><tr><td width="12" bgcolor="#F2B444" style="font-size:2px;line-height:2px;mso-line-height-rule:exactly">&nbsp;</td><td width="28" bgcolor="#8BDC65" style="font-size:2px;line-height:2px;mso-line-height-rule:exactly">&nbsp;</td><td width="352" bgcolor="#2A8C76" style="font-size:2px;line-height:2px;mso-line-height-rule:exactly">&nbsp;</td></tr></table></td></tr>',
        '{{CONTACT_ROWS}}',
        '</table></td>',
        '</tr><tr><td width="216" valign="bottom" style="padding:0 16px;font-size:1px;line-height:1px;mso-line-height-rule:exactly"><table cellpadding="0" cellspacing="0" width="216"><tr><td width="216" height="78" align="center" bgcolor="#FFFFFF" style="border-top:3px solid #8BDC65;border-radius:10px 10px 0 0;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:20px;font-weight:bold;mso-line-height-rule:at-least"><a href="https://iiso.co.za/" style="display:block;text-decoration:none;color:#162B31">{{COMPANY_LOGO}}</a></td></tr></table></td>',
        '</tr></table>{{VML_CLOSE}}</td></tr>',
        '{{BRANDS_SECTION}}',
        '<tr><td bgcolor="#FFFFFF" style="padding:6px 16px 0"><table cellpadding="0" cellspacing="0" width="608"><tr><td width="40" bgcolor="#F2B444" style="font-size:3px;line-height:3px;mso-line-height-rule:exactly">&nbsp;</td><td width="80" bgcolor="#8BDC65" style="font-size:3px;line-height:3px;mso-line-height-rule:exactly">&nbsp;</td><td width="120" bgcolor="#12A374" style="font-size:3px;line-height:3px;mso-line-height-rule:exactly">&nbsp;</td><td width="368" bgcolor="#138B70" style="font-size:3px;line-height:3px;mso-line-height-rule:exactly">&nbsp;</td></tr></table></td></tr>',
        '<tr><td bgcolor="#FFFFFF" style="padding:9px 16px 2px 17px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:15px;color:#162B31;mso-line-height-rule:exactly">Filtration <span style="color:#138B70">&bull;</span> Industrial Lubrication <span style="color:#138B70">&bull;</span> Oil Analysis <span style="color:#138B70">&bull;</span> Condition Monitoring<br><span style="color:#5E716E"><b>ISO-Reliability Partners (Pty) Ltd</b> &nbsp;|&nbsp; MotorCity C7-C8, Germiston, 1402, South Africa</span></td></tr>',
        '</table>'
    ].join('\n');

    var LABEL_STYLE = 'font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:19px;font-weight:bold;letter-spacing:1px;color:#8BDC65';
    var VALUE_STYLE = 'font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:19px';
    var SNIP = {
        TITLE_ROW: '<tr><td colspan="4" style="padding-top:3px;font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:14px;font-weight:bold;letter-spacing:1.3px;color:#A9EDCB;mso-line-height-rule:exactly">{{TITLE}}</td></tr>',
        VML_OPEN: '<!--[if gte mso 9]><v:rect xmlns:v="urn:schemas-microsoft-com:vml" fill="true" stroke="false" style="width:480pt;height:99pt"><v:fill type="frame" src="{{BG_URL}}" color="#0D4B4A" aspect="atleast"/><v:textbox style="mso-fit-shape-to-text:true" inset="0,0,0,0"><![endif]--><div>',
        VML_CLOSE: '</div><!--[if gte mso 9]></v:textbox></v:rect><![endif]-->',
        CONTACT_ROWS_NORMAL: [
            '<tr><td width="52" style="' + LABEL_STYLE + '">MOBILE</td><td width="104" style="' + VALUE_STYLE + '">{{MOBILE_LINK}}</td><td width="46" style="' + LABEL_STYLE + '">EMAIL</td><td width="126" style="' + VALUE_STYLE + '">{{EMAIL_LINK}}</td><td width="64"></td></tr>',
            '<tr><td style="' + LABEL_STYLE + '">OFFICE</td><td style="' + VALUE_STYLE + '"><a href="tel:+27104496414" style="color:#FFFFFF;text-decoration:none">+27&nbsp;10&nbsp;449&nbsp;6414</a></td><td style="' + LABEL_STYLE + '">WEB</td><td style="' + VALUE_STYLE + '"><a href="https://iiso.co.za/" style="color:#FFFFFF;text-decoration:none;font-weight:bold">www.iiso.co.za</a></td></tr>'
        ].join('\n'),
        // stacked rows (a long value, or a missing mobile / email): one full-width value row per contact, then
        // OFFICE + WEB. The first row of the table carries the column widths ({{LABEL_W}} and the 64px {{GAP}}).
        CONTACT_ROW: '<tr><td{{LABEL_W}} valign="top" style="' + LABEL_STYLE + '">{{LABEL}}</td><td colspan="3" style="word-break:break-word;' + VALUE_STYLE + '">{{LINK}}</td>{{GAP}}</tr>',
        OFFICE_ROW: '<tr><td{{LABEL_W}} style="' + LABEL_STYLE + '">OFFICE</td><td width="104" style="' + VALUE_STYLE + '"><a href="tel:+27104496414" style="color:#FFFFFF;text-decoration:none">+27&nbsp;10&nbsp;449&nbsp;6414</a></td><td width="46" style="' + LABEL_STYLE + '">WEB</td><td width="126" style="' + VALUE_STYLE + '"><a href="https://iiso.co.za/" style="color:#FFFFFF;text-decoration:none;font-weight:bold">www.iiso.co.za</a></td>{{GAP}}</tr>',
        MOBILE_LINK: '<a href="{{MOBILE_HREF}}" style="color:#FFFFFF;text-decoration:none">{{MOBILE}}</a>',
        MOBILE_TEXT: '<span style="color:#FFFFFF">{{MOBILE}}</span>',
        EMAIL_LINK: '<a href="{{EMAIL_HREF}}" style="color:#FFFFFF;text-decoration:none">{{EMAIL}}</a>',
        EMAIL_TEXT: '<span style="color:#FFFFFF">{{EMAIL}}</span>',
        COMPANY_LOGO_IMG: '<img src="{{COMPANY_LOGO_SRC}}" alt="ISO-Reliability Partners" width="{{W}}" height="{{H}}" style="display:block;border:0;font-size:13px;line-height:16px">',
        COMPANY_LOGO_TEXT: 'ISO-Reliability Partners',
        BRANDS_SECTION: [
            '<tr><td bgcolor="#FFFFFF" style="padding:14px 16px 0"><table cellpadding="0" cellspacing="0" width="608"><tr><td width="276" bgcolor="#FFFFFF" style="font-family:Arial,Helvetica,sans-serif;font-size:10px;line-height:12px;font-weight:bold;letter-spacing:1.6px;white-space:nowrap;color:#0F7B63;mso-line-height-rule:exactly">OUR BRANDS &amp; TECHNOLOGY PARTNERS</td><td width="332" bgcolor="#FFFFFF"><table cellpadding="0" cellspacing="0" width="332"><tr><td bgcolor="#E1E9E6" style="font-size:1px;line-height:1px;mso-line-height-rule:exactly">&nbsp;</td></tr></table></td></tr></table></td></tr>',
            '<tr><td bgcolor="#FFFFFF" style="padding:0 8px"><table cellpadding="0" cellspacing="8" width="624" style="font-family:Arial,Helvetica,sans-serif;font-size:11px;font-weight:bold">{{BRAND_ROWS}}</table></td></tr>'
        ].join('\n'),
        ROW: '<tr>{{ROW_CELLS}}</tr>',
        CELL_IMG: '<td width="144" height="48" align="center" bgcolor="#FFFFFF" style="border:1px solid #E0E8E5"><a href="{{LOGO_LINK}}" style="color:#2E4A4D"><img src="{{LOGO_SRC}}" alt="{{LOGO_NAME}}" width="{{W}}" height="{{H}}" style="display:block;border:0"></a></td>',
        CELL_TEXT: '<td width="144" height="48" align="center" bgcolor="#FFFFFF" style="border:1px solid #E0E8E5;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:15px;font-weight:bold;color:#2E4A4D;mso-line-height-rule:exactly"><a href="{{LOGO_LINK}}" style="color:#2E4A4D;text-decoration:none">{{LOGO_NAME}}</a></td>',
        CELL_EMPTY: '<td width="146"></td>',
        NO_BRANDS: '<tr><td height="8" bgcolor="#FFFFFF" style="font-size:8px;line-height:8px;mso-line-height-rule:exactly">&nbsp;</td></tr>'
    };

    // ---- Text metrics: Arial/Helvetica advance widths (1/1000 em) for characters 32..126 ------
    // Liberation Sans is metric-identical. Used for the long-value rules, so the layout decision does
    // not depend on the fonts installed on the computer running the builder.
    var AFM_R = [278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,556,556,333,500,278,556,500,722,500,500,500,334,260,334,584];
    var AFM_B = [278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,611,611,389,556,333,611,556,778,556,556,500,389,280,389,584];
    var AFM_EXTRA = { '\u2022': 350, '\u00A0': 278, '\u2013': 556, '\u2014': 1000, '\u2019': 222 };

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
            .replace(/\{/g, '&#123;').replace(/\}/g, '&#125;');
    }

    // escape, then turn the soft-break markers added by softBreak() into &#8203;
    function escZW(value) {
        return esc(value).split(ZW).join('&#8203;');
    }

    function norm(value) {
        return String(value == null ? '' : value).replace(/\s+/g, ' ').trim();
    }

    function safeUrl(value, fallback) {
        try {
            var parsed = new URL(String(value || '').trim());
            return ['http:', 'https:'].indexOf(parsed.protocol) !== -1 ? parsed.href : fallback;
        } catch (e) {
            return fallback;
        }
    }

    // image / background URL: http(s) only, with the characters that could end an attribute or a CSS url('...') encoded
    function safeSrc(value) {
        var url = safeUrl(value, null);
        return url ? url.replace(/[ "'()<>\\{}]/g, function (c) { return '%' + c.charCodeAt(0).toString(16).toUpperCase(); }) : null;
    }

    function put(str, map) {
        return str.replace(/\{\{([A-Z_]+)\}\}/g, function (match, key) {
            return Object.prototype.hasOwnProperty.call(map, key) ? String(map[key]) : match;
        });
    }

    function fitBox(width, height, maxW, maxH) {
        var ratio = Math.min(maxW / width, maxH / height, 1);
        return { w: Math.max(1, Math.round(width * ratio)), h: Math.max(1, Math.round(height * ratio)) };
    }

    function chars(str) {
        return String(str).match(/[\uD800-\uDBFF][\uDC00-\uDFFF]|[\s\S]/g) || [];
    }

    function textWidth(str, px, bold, tracking) {
        var table = bold ? AFM_B : AFM_R;
        var plain = String(str);
        var list = chars(plain.normalize ? plain.normalize('NFD').replace(/[\u0300-\u036f]/g, '') : plain);
        var width = 0;
        for (var i = 0; i < list.length; i++) {
            var code = list[i].charCodeAt(0);
            width += (code >= 32 && code <= 126) ? table[code - 32] : (AFM_EXTRA[list[i]] || (bold ? 640 : 600));
        }
        return width * px / 1000 + list.length * (tracking || 0);
    }

    // Split every run wider than maxW with zero-width spaces: before "@" or after . - _ / , where possible,
    // otherwise between two characters. Runs that already fit are returned untouched.
    function softBreak(raw, maxW, px, bold, tracking) {
        return String(raw).split(/( +)/).map(function (word) {
            var ch = chars(word);
            var width = function (from, to) { return textWidth(ch.slice(from, to).join(''), px, bold, tracking); };
            if (width(0, ch.length) <= maxW) {
                return word;
            }
            var parts = [];
            var start = 0;
            while (width(start, ch.length) > maxW) {
                var fit = start + 1, nice = -1;
                for (var i = start + 1; i < ch.length; i++) {
                    if (width(start, i) > maxW) {
                        break;
                    }
                    fit = i;
                    if (ch[i] === '@' || BREAK_AFTER.indexOf(ch[i - 1]) !== -1) {
                        nice = i;
                    }
                }
                var cut = nice > start ? nice : fit;
                parts.push(ch.slice(start, cut).join(''));
                start = cut;
            }
            parts.push(ch.slice(start).join(''));
            return parts.join(ZW);
        }).join('');
    }

    // tel: target of a typed number: no extension, "00" -> "+", no "(0)" trunk prefix after a country code,
    // and the South African national format (0xx xxx xxxx, +27 0xx ...) as +27xx..., e.g. "+27 (0)82 555 0123" -> +27825550123
    function telNumber(number) {
        var digits = String(number)
            .replace(/\s*(ext\.?|x)\s*\d.*$/i, '')
            .replace(/^\s*00(?=\s*[1-9])/, '+')
            .replace(/\(\s*0\s*\)/g, '')
            .replace(/[^+0-9]/g, '')
            .replace(/(?!^)\+/g, '');
        return digits.replace(/^\+270(?=\d{9}$)/, '+27').replace(/^0(?=\d{9}$)/, '+27');
    }

    // Mobile: &nbsp; only inside each number, so a number never splits; several numbers may wrap after a separator.
    function mobileValue(raw, warnings) {
        var numbers = [], separators = [];
        norm(raw).split(/\s*([\/,;|])\s*/).forEach(function (part, i) {
            if (i % 2) {
                if (numbers.length) {
                    separators[numbers.length - 1] = part;
                }
            } else if (part) {
                numbers.push(part);
            }
        });
        var html = '', plain = '';
        numbers.forEach(function (number, i) {
            if (textWidth(number, 12) <= SPAN_W) {
                html += esc(number).replace(/ /g, '&nbsp;');
            } else {
                html += escZW(softBreak(number, SPAN_W, 12));
                warnings.push('The mobile number “' + number + '” is too long for one line, so it wraps inside the number.');
            }
            plain += number;
            if (i < numbers.length - 1) {
                var sep = separators[i] || '/', tight = sep === ',' || sep === ';';
                html += (tight ? '' : '&nbsp;') + esc(sep) + ' ';
                plain += (tight ? '' : ' ') + sep + ' ';
            }
        });
        var width = textWidth(plain, 12);
        if (width > SPAN_W && numbers.length > 1) {
            warnings.push('The mobile numbers do not fit on one line, so they wrap after a separator.');
        }
        var tel = telNumber(numbers[0] || '');
        return { html: html, width: width, href: 'tel:' + tel, linked: tel.replace(/\D/g, '').length >= 6 };
    }

    // The four inputs, whitespace-normalised. An empty value is left out of the signature; only while the name
    // is empty does the preview show a sample with [PLACEHOLDERS], and Copy / Download refuse to run.
    function values() {
        return {
            name: norm($('personName').value),
            title: norm($('personTitle').value),
            mobile: norm($('personMobile').value),
            email: norm($('personEmail').value)
        };
    }

    function missingFields(v) {
        return [['title', 'a job title'], ['mobile', 'a mobile number'], ['email', 'an email address']]
            .filter(function (f) { return !v[f[0]]; })
            .map(function (f) { return f[1]; });
    }

    function listText(items) {
        return items.length < 2 ? items.join('') : items.slice(0, -1).join(', ') + ' and ' + items[items.length - 1];
    }

    // NORMAL: mobile and email side by side. Otherwise one full-width row per contact that has a value.
    function contactRows(mobile, email, stacked) {
        if (mobile && email && !stacked) {
            return put(SNIP.CONTACT_ROWS_NORMAL, { MOBILE_LINK: mobile, EMAIL_LINK: email });
        }
        var items = [];
        if (mobile) {
            items.push(['MOBILE', mobile]);
        }
        if (email) {
            items.push(['EMAIL', email]);
        }
        var first = { LABEL_W: ' width="52"', GAP: '<td width="64"></td>' }, next = { LABEL_W: '', GAP: '' };
        var rows = items.map(function (item, i) {
            return put(SNIP.CONTACT_ROW, { LABEL_W: (i ? next : first).LABEL_W, GAP: (i ? next : first).GAP, LABEL: item[0], LINK: item[1] });
        });
        rows.push(put(SNIP.OFFICE_ROW, items.length ? next : first));
        return rows.join('\n');
    }

    // Characters a signature editor such as Gmail's stores: the markup parsed and serialised again (it adds
    // <tbody> to every table and decodes entities such as &bull;). Gmail's 10,000 limit applies to that.
    function editorChars(html) {
        var t = document.createElement('template');
        if (!('content' in t)) {
            return html.length;
        }
        t.innerHTML = html;
        return t.innerHTML.length;
    }

    function brandCell(logo, warnings) {
        if (!logo) {
            return SNIP.CELL_EMPTY;
        }
        var link = esc(safeUrl(logo.link, FALLBACK_LINK));
        var name = norm(logo.name);
        var state = logoState[logo.id] || {};
        var src = safeSrc(logo.src);
        if (src && state.ok) {
            return put(SNIP.CELL_IMG, { LOGO_LINK: link, LOGO_SRC: esc(src), LOGO_NAME: esc(name), W: state.w, H: state.h });
        }
        var broken = softBreak(name, TILE_TEXT_W, 12, true);
        if (broken !== name) {
            warnings.push('The partner name “' + name + '” has a word too wide for its tile; soft line breaks were added.');
        }
        return put(SNIP.CELL_TEXT, { LOGO_LINK: link, LOGO_NAME: escZW(broken) });
    }

    function brandsSection(warnings) {
        if (!logos.length) {
            return SNIP.NO_BRANDS;
        }
        var rows = '';
        for (var i = 0; i < logos.length; i += PER_ROW) {
            var cells = '';
            for (var c = 0; c < PER_ROW; c++) {
                cells += brandCell(logos[i + c] || null, warnings);
            }
            rows += put(SNIP.ROW, { ROW_CELLS: cells });
        }
        return put(SNIP.BRANDS_SECTION, { BRAND_ROWS: rows });
    }

    function companyLogoMarkup() {
        var src = safeSrc(companyLogo);
        if (src && companyState.status === 'ok') {
            return put(SNIP.COMPANY_LOGO_IMG, { COMPANY_LOGO_SRC: esc(src), W: companyState.w, H: companyState.h });
        }
        return SNIP.COMPANY_LOGO_TEXT;
    }

    // variant 'lean' = clipboard copy for Gmail / Outlook signature editors (no comments, no VML)
    // variant 'full' = downloadable .htm for Outlook desktop (VML background)
    // withoutBackground = bgcolor-only fallback, the way the signature editors show it
    function buildSignature(variant, withoutBackground) {
        var v = values();
        var warnings = [];
        var sample = !v.name;
        var name = v.name || '[FULL NAME]';
        var title = (v.title || (sample ? '[JOB TITLE]' : '')).toUpperCase();
        var mobileRaw = v.mobile || (sample ? '[MOBILE]' : '');
        var email = v.email || (sample ? '[YOUR EMAIL]' : '');
        var bg = withoutBackground ? null : safeSrc(background);
        var full = variant === 'full' && !!bg;

        var nameSize = NAME_SIZES[NAME_SIZES.length - 1];
        for (var i = 0; i < NAME_SIZES.length; i++) {
            if (textWidth(name, NAME_SIZES[i], true) <= TEXT_W) {
                nameSize = NAME_SIZES[i];
                break;
            }
        }
        var nameWraps = textWidth(name, nameSize, true) > TEXT_W;
        if (nameWraps) {
            warnings.push('The name is too long for one line even at the smallest size, so it wraps onto a second line.');
        }
        var nameBroken = softBreak(name, TEXT_W, nameSize, true);
        if (nameBroken !== name) {
            warnings.push('A word in the name is wider than the text column; soft line breaks were added.');
        }
        if (textWidth(title, 10, true, 1.3) > 2 * TEXT_W) {
            warnings.push('The job title runs to more than two lines; consider shortening it.');
        }
        var titleBroken = softBreak(title, TEXT_W, 10, true, 1.3);
        if (titleBroken !== title) {
            warnings.push('A word in the job title is wider than the text column; soft line breaks were added.');
        }
        var mobile = mobileValue(mobileRaw, warnings);
        var emailWidth = textWidth(email, 12);
        if (emailWidth > SPAN_W) {
            warnings.push('The email address is too long for one line, so it wraps at a dot, dash or the @.');
        }
        var emailLinked = EMAIL_RE.test(email);
        if (!sample) {
            if (!v.title) {
                warnings.push('No job title entered, so the title line is left out of the signature.');
            }
            if (!v.mobile) {
                warnings.push('No mobile number entered, so the MOBILE line is left out of the signature.');
            } else if (!mobile.linked) {
                warnings.push('The mobile number “' + v.mobile + '” has too few digits to dial, so it is shown without a phone link.');
            }
            if (!v.email) {
                warnings.push('No email address entered, so the EMAIL line is left out of the signature.');
            } else if (!emailLinked) {
                warnings.push('The email address “' + v.email + '” does not look valid, so it is shown without a link. Check it for typos.');
            }
        }
        var longContacts = emailWidth > EMAIL_NORMAL_MAX || mobile.width > MOBILE_NORMAL_MAX;

        // 1. structural tokens
        var html = put(TEMPLATE, {
            VML_OPEN: full ? SNIP.VML_OPEN : '',
            VML_CLOSE: full ? SNIP.VML_CLOSE : '',
            TITLE_ROW: title ? SNIP.TITLE_ROW : '',
            CONTACT_ROWS: contactRows(
                mobileRaw ? (mobile.linked ? SNIP.MOBILE_LINK : SNIP.MOBILE_TEXT) : '',
                email ? (emailLinked ? SNIP.EMAIL_LINK : SNIP.EMAIL_TEXT) : '',
                longContacts
            ),
            BRANDS_SECTION: brandsSection(warnings),
            COMPANY_LOGO: companyLogoMarkup()
        });
        // 2. no background: drop the two image declarations (the bgcolor fallback stays)
        if (!bg) {
            html = html.split(' background="{{BG_URL}}"').join('').split('background-image:url(\'{{BG_URL}}\');').join('');
        }
        // 3. values
        html = put(html, {
            BG_URL: bg ? esc(bg) : '',
            NAME_SIZE: nameSize,
            NAME_LH: nameWraps ? 22 : 27,
            NAME: escZW(nameBroken),
            TITLE: escZW(titleBroken),
            MOBILE_HREF: mobile.href,
            MOBILE: mobile.html,
            EMAIL_HREF: 'mailto:' + esc(email.replace(/[%?#]/g, encodeURIComponent)),
            EMAIL: escZW(emailWidth > SPAN_W ? softBreak(email, SPAN_W, 12) : email)
        }).replace(/\n/g, '');
        var left = html.match(/\{\{[A-Z_]+\}\}/);
        if (left) {
            throw new Error('Unfilled signature token ' + left[0]);
        }
        return { html: html, size: editorChars(html), warnings: warnings, sample: sample, missing: sample ? [] : missingFields(v) };
    }

    function signatureHtml(variant, withoutBackground) {
        return buildSignature(variant, withoutBackground).html;
    }

    function signaturePlainText() {
        var v = values();
        var contacts = [];
        if (v.mobile) {
            contacts.push('Mobile: ' + v.mobile);
        }
        if (v.email) {
            contacts.push('Email: ' + v.email);
        }
        var lines = [v.name, (v.title ? v.title + ' | ' : '') + 'ISO-Reliability Partners'];
        if (contacts.length) {
            lines.push(contacts.join(' | '));
        }
        lines.push('Office: +27 10 449 6414 | Web: www.iiso.co.za');
        if (logos.length) {
            lines.push('Our brands & technology partners: ' + logos.map(function (l) { return l.name; }).join(', '));
        }
        lines.push('Filtration • Industrial Lubrication • Oil Analysis • Condition Monitoring');
        lines.push('ISO-Reliability Partners (Pty) Ltd | MotorCity C7-C8, Germiston, 1402, South Africa');
        return lines.join('\n');
    }

    function outlookDocument(markup) {
        return '<!DOCTYPE html>\n'
            + '<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">'
            + '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>IISO Email Signature</title>'
            + '<!--[if gte mso 9]><xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml><![endif]-->'
            + '</head><body style="margin:0;padding:0;background:#FFFFFF">\n' + markup + '\n</body></html>\n';
    }

    function previewDocument() {
        return $('signature').contentDocument;
    }

    var previewHeight = 150, fitWidth = -1;

    function writePreview(markup) {
        var doc = previewDocument();
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#fff">' + markup + '</body></html>');
        doc.close();
        previewHeight = Math.max(150, doc.body.offsetHeight);
        $('signature').style.height = previewHeight + 'px';
        fitWidth = -1;
        fitPreview();
    }

    // Narrower than 640px (tablets, phones): scale the whole signature down instead of cutting it off.
    function fitPreview() {
        var box = $('sigbFit');
        var avail = box.clientWidth;
        if (avail === fitWidth) {
            return;
        }
        fitWidth = avail;
        var scale = avail > 0 && avail < 640 ? avail / 640 : 1;
        $('signature').style.transform = scale < 1 ? 'scale(' + scale + ')' : '';
        box.style.height = Math.ceil(previewHeight * scale) + 'px';
    }

    function fmt(n) {
        return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function setStat(el, state, html) {
        el.className = 'sigb-stat' + (state ? ' ' + state : '');
        el.innerHTML = '<span>' + html + '</span>';
    }

    var COMPANY_NAME_HTML = '<span class="sigb-nowrap">“ISO-Reliability Partners”</span>';

    function manageLink() {
        return '<a href="' + esc(manageLogosUrl) + '">Manage Logos</a>';
    }

    // count = editorChars() of the copied markup
    function updateSizeStatus(count) {
        var over = count > GMAIL_LIMIT, near = !over && count > GMAIL_SAFE;
        var html = 'Signature size: <strong>' + fmt(count) + ' / ' + fmt(GMAIL_LIMIT) + ' characters</strong> (Gmail limit)';
        if (over) {
            html += ' — too long for Gmail, which will refuse it. Hide a partner logo under ' + manageLink() + ' (about 300 characters each), or shorten long brand names or external image links.';
        } else if (near) {
            html += ' — close to the limit. Gmail’s editor adds about 100 characters when you paste, so Gmail may refuse it. To be safe, hide a partner logo under ' + manageLink() + '.';
        }
        setStat($('sigbSize'), over || near ? 'warn' : '', html);
    }

    function updateWarnings(warnings) {
        var list = $('sigbWarnings');
        list.innerHTML = warnings.map(function (w) { return '<li>' + esc(w) + '</li>'; }).join('');
        list.hidden = !warnings.length;
    }

    function updateCompanyStatus() {
        var el = $('sigbCompany');
        if (companyState.status === 'ok') {
            setStat(el, '', 'Company logo uploaded · shown at <strong class="sigb-nowrap">' + companyState.w + ' × ' + companyState.h + ' px</strong> on the white tab.');
        } else if (companyState.status === 'loading') {
            setStat(el, '', 'Company logo uploaded · loading…');
        } else if (companyState.status === 'failed') {
            setStat(el, 'warn', 'The uploaded company logo could not be loaded, so the signature shows ' + COMPANY_NAME_HTML + ' as text. Check it under ' + manageLink() + '.');
        } else {
            setStat(el, 'warn', 'No company logo uploaded — upload it under ' + manageLink() + '. Until then the signature shows ' + COMPANY_NAME_HTML + ' as text.');
        }
    }

    function updateLogoSummary() {
        if (!logos.length) {
            setStat($('sigbLogoSummary'), '', 'No active brand logos, so the “Our Brands &amp; Technology Partners” section is left out of the signature.');
            return;
        }
        var withImage = logos.filter(function (l) { return l.src; }).length;
        var showing = logos.filter(function (l) { return logoState[l.id] && logoState[l.id].ok; }).length;
        var pending = logos.filter(function (l) { return l.src && !logoState[l.id]; }).length;
        var failed = logos.filter(function (l) { return l.src && logoState[l.id] && !logoState[l.id].ok; }).map(function (l) { return norm(l.name); });
        var noImage = logos.length - withImage;
        var text = logos.length + ' active brand logo' + (logos.length === 1 ? '' : 's');
        if (withImage) {
            text += ' · ' + showing + ' of ' + withImage + ' image' + (withImage === 1 ? '' : 's') + ' displaying' + (pending ? ' (loading…).' : '.');
        } else {
            text += '.';
        }
        if (noImage) {
            text += ' ' + (noImage === logos.length ? 'None has an image yet, so they show as text tiles.'
                : noImage === 1 ? '1 brand has no image yet and shows as a text tile.'
                : noImage + ' brands have no image yet and show as text tiles.');
        }
        var html = esc(text);
        if (failed.length) {
            html += ' <strong>' + esc(listText(failed)) + '</strong> could not be loaded, so ' + (failed.length === 1 ? 'it shows' : 'they show')
                + ' as text in the signature. Check ' + (failed.length === 1 ? 'it' : 'them') + ' under ' + manageLink() + '.';
        }
        setStat($('sigbLogoSummary'), failed.length ? 'warn' : '', html);
    }

    function render() {
        var withoutBackground = $('sigbNoBg').checked;
        var lean = buildSignature('lean', false);
        writePreview(withoutBackground ? signatureHtml('lean', true) : lean.html);
        $('sigbFrameTitle').textContent = (lean.sample ? 'SAMPLE · ' : '') + (withoutBackground
            ? 'LIVE PREVIEW · WITHOUT BACKGROUND IMAGE (GMAIL / OUTLOOK EDITOR FALLBACK)'
            : 'LIVE EMAIL SIGNATURE PREVIEW');
        updateSizeStatus(lean.size);
        updateWarnings(lean.warnings);
        $('sigbMsg').textContent = '';
    }

    function loadCompanyLogo() {
        if (!safeSrc(companyLogo)) {
            companyState = { status: 'none' };
            updateCompanyStatus();
            return;
        }
        var img = new Image();
        img.onload = function () {
            if (img.naturalWidth > 0 && img.naturalHeight > 0) {
                var box = fitBox(img.naturalWidth, img.naturalHeight, COMPANY_BOX_W, COMPANY_BOX_H);
                companyState = { status: 'ok', w: box.w, h: box.h };
            } else {
                companyState = { status: 'failed' };
            }
            render();
            updateCompanyStatus();
        };
        img.onerror = function () {
            companyState = { status: 'failed' };
            render();
            updateCompanyStatus();
        };
        img.src = companyLogo;
        updateCompanyStatus();
    }

    function loadLogos() {
        logos.forEach(function (logo) {
            if (!logo.src) {
                return;
            }
            var img = new Image();
            img.onload = function () {
                var ok = img.naturalWidth > 0 && img.naturalHeight > 0;
                var box = ok ? fitBox(img.naturalWidth, img.naturalHeight, LOGO_BOX_W, LOGO_BOX_H) : {};
                logoState[logo.id] = { ok: ok, w: box.w, h: box.h };
                render();
                updateLogoSummary();
            };
            img.onerror = function () {
                logoState[logo.id] = { ok: false };
                render();
                updateLogoSummary();
            };
            img.src = logo.src;
        });
        updateLogoSummary();
    }

    // Fallback 1: a copy event lets the page put the exact markup on the clipboard.
    function copyWithEvent(markup, plain) {
        var done = false;
        var listener = function (e) {
            e.clipboardData.setData('text/html', markup);
            e.clipboardData.setData('text/plain', plain);
            e.preventDefault();
            done = true;
        };
        document.addEventListener('copy', listener);
        try { document.execCommand('copy'); } catch (e) {}
        document.removeEventListener('copy', listener);
        return done;
    }

    // Fallback 2: select and copy the rendered signature (with the background, even when the preview hides it).
    function copyFromPreview(markup) {
        writePreview(markup);
        var doc = previewDocument();
        var range = doc.createRange();
        range.selectNodeContents(doc.body);
        var selection = doc.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);
        var ok = false;
        try { ok = doc.execCommand('copy'); } catch (e) {}
        selection.removeAllRanges();
        render();
        return ok;
    }

    function showMessage(text, warn) {
        $('sigbMsg').textContent = text;
        $('sigbMsg').className = 'sigb-msg' + (warn ? ' warn' : '');
    }

    var NAME_FIRST = 'Enter the full name first (or choose an employee). The preview is only a sample until then.';

    function withoutText(missing) {
        return missing.length ? ' without ' + listText(missing) + ' (left empty)' : '';
    }

    ['personName', 'personTitle', 'personMobile', 'personEmail'].forEach(function (id) {
        $(id).addEventListener('input', render);
    });
    $('sigbNoBg').addEventListener('change', render);

    $('sigbEmployee').addEventListener('change', function () {
        var id = this.value;
        var employee = employees.find(function (e) { return String(e.id) === id; });
        if (!employee) {
            return;
        }
        $('personName').value = employee.name || '';
        $('personTitle').value = employee.title || '';
        $('personMobile').value = employee.mobile || '';
        $('personEmail').value = employee.email || '';
        render();
    });

    $('copyBtn').addEventListener('click', async function () {
        render();
        var lean = buildSignature('lean', false);
        if (lean.sample) {
            showMessage(NAME_FIRST, true);
            $('personName').focus();
            return;
        }
        var plain = signaturePlainText();
        var ok = false;
        if (navigator.clipboard && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([lean.html], { type: 'text/html' }),
                    'text/plain': new Blob([plain], { type: 'text/plain' })
                })]);
                ok = true;
            } catch (e) {}
        }
        if (!ok) {
            ok = copyWithEvent(lean.html, plain);
        }
        if (!ok) {
            ok = copyFromPreview(lean.html);
        }
        if (!ok) {
            showMessage('Your browser blocked automatic copying; select the preview and copy it manually.', true);
        } else if (lean.size > GMAIL_LIMIT) {
            showMessage('Copied' + withoutText(lean.missing) + ', but the signature is ' + fmt(lean.size) + ' characters: over Gmail’s 10,000 limit, so Gmail will refuse it. Hide a partner logo and copy again.', true);
        } else if (lean.size > GMAIL_SAFE) {
            showMessage('Copied' + withoutText(lean.missing) + '. At ' + fmt(lean.size) + ' characters the signature is close to Gmail’s 10,000 limit: if Gmail refuses it, hide a partner logo under Manage Logos and copy again.', true);
        } else if (lean.missing.length) {
            showMessage('Formatted signature copied' + withoutText(lean.missing) + '. Paste it into your email signature settings.', true);
        } else {
            showMessage('Formatted signature copied. Paste it into your email signature settings.', false);
        }
    });

    $('downloadBtn').addEventListener('click', function () {
        render();
        var full = buildSignature('full', false);
        if (full.sample) {
            showMessage(NAME_FIRST, true);
            $('personName').focus();
            return;
        }
        var blob = new Blob([outlookDocument(full.html)], { type: 'text/html;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'IISO_Email_Signature_' + values().name.replace(/[^a-zA-Z0-9_-]/g, '_') + '.htm';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        showMessage('Personalised HTML signature downloaded' + withoutText(full.missing) + '. It includes the Outlook desktop background, so it also works as an Outlook .htm signature file.', full.missing.length > 0);
    });

    if (window.ResizeObserver) {
        new ResizeObserver(fitPreview).observe($('sigbFit'));
    } else {
        window.addEventListener('resize', fitPreview);
    }

    render();
    loadCompanyLogo();
    loadLogos();
})();
@endverbatim
</script>
@endsection
