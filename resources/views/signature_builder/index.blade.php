@extends('layouts.app')
@section('title','Signature Builder | ISO Admin')
@section('page_title','Signature Builder')
@section('content')
<style>
    .sigb-grid{display:grid;grid-template-columns:340px minmax(0,1fr);gap:16px;align-items:start}
    .sigb-grid .form-row{margin-bottom:12px}
    .sigb-buttons{display:grid;gap:10px;margin-top:6px}
    .sigb-msg{min-height:18px;font-size:13px;font-weight:800;color:var(--brand2);margin-top:10px}
    .sigb-logo-summary{margin-top:14px;padding:11px 12px;border-radius:14px;background:rgba(18,163,116,.1);border:1px solid rgba(18,163,116,.28);font-size:13px;line-height:1.5;color:#cfeee0}
    .sigb-frame{background:#fff;border-radius:var(--radius);padding:25px;overflow:auto;box-shadow:var(--shadow)}
    .sigb-frame-title{font:bold 10px Arial,Helvetica,sans-serif;letter-spacing:1.6px;color:#80928b;margin:0 0 22px}
    .sigb-holder{display:block;width:640px;max-width:none;height:420px;border:0;background:#fff}
    @media(max-width:1080px){.sigb-grid{grid-template-columns:1fr}}
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
                <option value="">— Type details manually —</option>
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

        <div class="sigb-buttons">
            <button class="btn primary full" id="copyBtn" type="button">Copy formatted signature</button>
            <button class="btn full" id="downloadBtn" type="button">Download personalised HTML</button>
        </div>
        <div id="sigbMsg" class="sigb-msg" aria-live="polite"></div>

        <div id="sigbLogoSummary" class="sigb-logo-summary" aria-live="polite">Loading brand logos…</div>
        <p class="muted small">Brand logos are managed centrally under <a href="{{ route('signature_builder.logos') }}" style="color:var(--brand2);font-weight:800">Manage Logos</a>. Some email clients ask the recipient to allow remote images before logos display.</p>
    </div>

    <div class="sigb-frame">
        <div class="sigb-frame-title">LIVE EMAIL SIGNATURE PREVIEW</div>
        <iframe class="sigb-holder" id="signature" title="Email signature preview" scrolling="no"></iframe>
    </div>
</div>

<script>
(function () {
    'use strict';
    var employees = @json($employees);
    var logos = @json($logos);
    var $ = function (id) { return document.getElementById(id); };
    var LOGO_BOX_W = 130, LOGO_BOX_H = 42, PER_ROW = 4;
    var logoState = {};

    function esc(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function safeUrl(value, fallback) {
        try {
            var parsed = new URL(String(value || '').trim());
            return ['http:', 'https:'].indexOf(parsed.protocol) !== -1 ? parsed.href : fallback;
        } catch (e) {
            return fallback;
        }
    }

    function values() {
        var name = $('personName').value.trim();
        var title = $('personTitle').value.trim();
        var mobile = $('personMobile').value.trim();
        var email = $('personEmail').value.trim();
        return {
            name: name || '[FULL NAME]',
            title: title || '[JOB TITLE]',
            mobile: mobile || '[MOBILE NUMBER]',
            mobileHref: 'tel:' + (mobile.replace(/[^+0-9]/g, '') || '+27000000000'),
            email: email || '[YOUR EMAIL]',
            emailHref: 'mailto:' + (/^[^\s@<>"']+@[^\s@<>"']+$/.test(email) ? email : 'info@iiso.co.za')
        };
    }

    function logoCell(logo, isLastInRow) {
        var border = 'border-bottom:1px solid #E7ECEA;' + (isLastInRow ? '' : 'border-right:1px solid #E7ECEA;');
        var open = '<td valign="middle" align="center" width="25%" style="width:25%;height:66px;padding:9px 6px 8px;' + border + 'vertical-align:middle;">';
        if (!logo) {
            return open + '&nbsp;</td>';
        }
        var state = logoState[logo.id] || {};
        var inner;
        if (state.ok) {
            inner = '<img alt="' + esc(logo.name) + ' logo" width="' + state.w + '" height="' + state.h + '" src="' + esc(logo.src) + '" style="display:block;margin:0 auto;width:' + state.w + 'px;height:' + state.h + 'px;max-width:100%;border:0;outline:none;text-decoration:none;" />';
        } else {
            inner = '<span style="display:block;font-family:Arial,Helvetica,sans-serif;font-size:12px;line-height:16px;font-weight:bold;color:#3a5052;">' + esc(logo.name) + '</span>';
        }
        return open + '<a href="' + esc(safeUrl(logo.link, 'https://iiso.co.za/')) + '" target="_blank" style="text-decoration:none;color:#354a4d;">' + inner + '</a></td>';
    }

    function brandRows() {
        if (!logos.length) {
            return '';
        }
        var rows = '';
        for (var i = 0; i < logos.length; i += PER_ROW) {
            rows += '<tr>';
            for (var c = 0; c < PER_ROW; c++) {
                rows += logoCell(logos[i + c] || null, c === PER_ROW - 1);
            }
            rows += '</tr>';
        }
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr>'
            + '<td style="color:#138B70;font-family:Arial,Helvetica,sans-serif;font-size:9px;line-height:13px;font-weight:800;letter-spacing:1.6px;padding:0 0 8px 1px;">OUR BRANDS &amp; TECHNOLOGY PARTNERS</td>'
            + '</tr></table>'
            + '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;table-layout:fixed;border-collapse:collapse;background-color:#FFFFFF;border:1px solid #E7ECEA;">' + rows + '</table>';
    }

    function signatureHtml() {
        var v = values();
        var label = function (text) {
            return '<span style="font-size:10px;display:inline-block;min-width:46px;color:#748785;font-weight:700;">' + text + '</span>&nbsp; ';
        };
        return ''
            + '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="640" style="width:640px;max-width:640px;border-collapse:collapse;border-spacing:0;background-color:#FFFFFF;font-family:Arial,Helvetica,sans-serif;color:#162B31;">'
            + '<tr><td colspan="2" height="5" bgcolor="#138B70" style="height:5px;font-size:0;line-height:5px;background-color:#138B70;">&nbsp;</td></tr>'
            + '<tr>'
            + '<td width="235" valign="top" style="width:235px;padding:22px 16px 18px 23px;border-bottom:1px solid #E0EAE6;">'
            + '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">'
            + '<tr><td style="padding:0;"><a href="https://iiso.co.za/" style="text-decoration:none;color:#162B31;">'
            + '<span style="display:block;font-size:46px;line-height:49px;letter-spacing:-3px;font-weight:900;color:#162B31;font-family:Arial,Helvetica,sans-serif;">ISO<span style="color:#138B70;">.</span></span>'
            + '<span style="display:block;margin-top:1px;letter-spacing:1.35px;font-size:10px;line-height:15px;font-weight:800;color:#162B31;">RELIABILITY PARTNERS</span>'
            + '</a></td></tr>'
            + '<tr><td style="padding-top:15px;font-size:10px;line-height:15px;letter-spacing:0.15px;color:#677C7A;">CLEANER OIL <span style="color:#138B70;font-weight:bold;">&#8226;</span> BETTER LUBRICATION<br>LONGER EQUIPMENT LIFE</td></tr>'
            + '</table></td>'
            + '<td valign="top" style="padding:20px 14px 16px 22px;border-left:1px solid #E0EAE6;border-bottom:1px solid #E0EAE6;">'
            + '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;">'
            + '<tr><td style="font-size:19px;line-height:23px;letter-spacing:-0.2px;color:#162B31;font-weight:800;">' + esc(v.name) + '</td></tr>'
            + '<tr><td style="padding-top:2px;padding-bottom:10px;font-size:11px;line-height:16px;font-weight:700;color:#138B70;">' + esc(v.title) + ' &nbsp;|&nbsp; ISO-Reliability Partners</td></tr>'
            + '<tr><td style="font-size:12px;line-height:20px;color:#344D50;">'
            + label('Mobile') + '<a href="' + esc(v.mobileHref) + '" style="color:#344D50;text-decoration:none;">' + esc(v.mobile) + '</a><br>'
            + label('Office') + '<a href="tel:+27104496414" style="color:#344D50;text-decoration:none;">+27 10 449 6414</a><br>'
            + label('Email') + '<a href="' + esc(v.emailHref) + '" style="color:#344D50;text-decoration:none;">' + esc(v.email) + '</a><br>'
            + label('Web') + '<a href="https://iiso.co.za/" style="color:#138B70;text-decoration:none;font-weight:700;">www.iiso.co.za</a>'
            + '</td></tr></table></td>'
            + '</tr>'
            + '<tr><td colspan="2" style="background-color:#F5F8F7;padding:11px 15px 0px 15px;">'
            + brandRows()
            + '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;"><tr><td style="padding:9px 0 10px 1px;font-size:10px;line-height:15px;color:#80918D;">Filtration&nbsp; &bull; &nbsp;Industrial Lubrication&nbsp; &bull; &nbsp;Oil Analysis&nbsp; &bull; &nbsp;Condition Monitoring</td></tr></table>'
            + '</td></tr>'
            + '<tr><td colspan="2" style="padding:9px 16px 0 16px;font-family:Arial,Helvetica,sans-serif;color:#9AA4A1;font-size:9px;line-height:13px;">'
            + '<span style="color:#8D9995;font-weight:bold;">ISO-Reliability Partners (Pty) Ltd</span> &nbsp;|&nbsp; MotorCity C7-C8, Germiston, 1402, South Africa'
            + '</td></tr>'
            + '</table>';
    }

    function previewDocument() {
        return $('signature').contentDocument;
    }

    function render() {
        var doc = previewDocument();
        doc.open();
        doc.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#fff">' + signatureHtml() + '</body></html>');
        doc.close();
        $('signature').style.height = Math.max(200, doc.documentElement.scrollHeight) + 'px';
        $('sigbMsg').textContent = '';
    }

    function updateLogoSummary() {
        var withImage = logos.filter(function (l) { return l.src; }).length;
        var showing = logos.filter(function (l) { return logoState[l.id] && logoState[l.id].ok; }).length;
        var pending = logos.filter(function (l) { return l.src && !logoState[l.id]; }).length;
        var text = logos.length + ' active brand logo' + (logos.length === 1 ? '' : 's') + ' · ' + showing + ' of ' + withImage + ' images displaying';
        if (pending) {
            text += ' (loading…)';
        } else if (showing < withImage) {
            text += '. Logos that failed to load show their brand name as text.';
        }
        if (withImage < logos.length) {
            text += ' ' + (logos.length - withImage) + ' brand' + (logos.length - withImage === 1 ? ' has' : 's have') + ' no image yet.';
        }
        $('sigbLogoSummary').textContent = text;
    }

    function loadLogos() {
        logos.forEach(function (logo) {
            if (!logo.src) {
                return;
            }
            var img = new Image();
            img.onload = function () {
                var ratio = Math.min(LOGO_BOX_W / img.naturalWidth, LOGO_BOX_H / img.naturalHeight, 1);
                logoState[logo.id] = {
                    ok: img.naturalWidth > 0,
                    w: Math.max(1, Math.round(img.naturalWidth * ratio)),
                    h: Math.max(1, Math.round(img.naturalHeight * ratio))
                };
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

    ['personName', 'personTitle', 'personMobile', 'personEmail'].forEach(function (id) {
        $(id).addEventListener('input', render);
    });

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
        var markup = signatureHtml();
        var ok = false;
        if (navigator.clipboard && window.ClipboardItem) {
            try {
                await navigator.clipboard.write([new ClipboardItem({
                    'text/html': new Blob([markup], { type: 'text/html' }),
                    'text/plain': new Blob([previewDocument().body.innerText], { type: 'text/plain' })
                })]);
                ok = true;
            } catch (e) {}
        }
        if (!ok) {
            var doc = previewDocument();
            var range = doc.createRange();
            range.selectNodeContents(doc.body);
            var selection = doc.getSelection();
            selection.removeAllRanges();
            selection.addRange(range);
            try { ok = doc.execCommand('copy'); } catch (e) {}
            selection.removeAllRanges();
        }
        $('sigbMsg').textContent = ok
            ? 'Formatted signature copied. Paste it into your email signature settings.'
            : 'Your browser blocked automatic copying; select the preview and copy it manually.';
    });

    $('downloadBtn').addEventListener('click', function () {
        render();
        var html = '<!DOCTYPE html>\n<html lang="en"><head><meta charset="utf-8"><title>IISO Email Signature</title></head><body style="margin:0;padding:0;background:#fff">' + signatureHtml() + '</body></html>';
        var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'IISO_Email_Signature_' + (($('personName').value.trim() || 'Employee').replace(/[^a-zA-Z0-9_-]/g, '_')) + '.html';
        document.body.appendChild(a);
        a.click();
        a.remove();
        URL.revokeObjectURL(url);
        $('sigbMsg').textContent = 'Personalised HTML signature downloaded.';
    });

    render();
    loadLogos();
})();
</script>
@endsection
