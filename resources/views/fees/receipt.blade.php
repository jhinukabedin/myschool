@php
    $slip = app(\App\Services\PaymentSlipService::class)->build($payment);
    $currency = school_setting('currency_symbol', config('school.currency', '৳'));
    $f = fn ($value) => number_format((float) $value, 2, '.', ',');
@endphp
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ school_name() }} - Payment Slip</title>
<style>
@page{size:A4;margin:0}
*{box-sizing:border-box;print-color-adjust:exact;-webkit-print-color-adjust:exact}
body{margin:0;font-family:'Liberation Sans','Noto Sans',sans-serif;color:#1e1b2e;background:#fff}
.page{width:210mm;min-height:297mm;max-height:297mm;padding:11mm 12mm 0 12mm;position:relative;overflow:hidden;display:flex;flex-direction:column}
:root{--p:#5b21b6;--pd:#4c1d95;--pl:#ede9fe;--pl2:#f5f3ff;--line:#ddd6fe}
.hdr{display:flex;align-items:flex-start;gap:14px}
.crest{width:64px;height:74px;flex:0 0 64px}
.titlebox{flex:1;border-right:2px solid var(--p);padding-right:14px}
.school{font-size:30pt;font-weight:700;color:var(--pd);letter-spacing:-.5px;line-height:1}
.meta{margin-top:7px;font-size:9.5pt;color:#222;display:flex;gap:8px;align-items:center}
.badgebox{width:52mm;text-align:right}
.badge{display:inline-block;background:var(--p);color:#fff;font-weight:700;font-size:13pt;letter-spacing:.6px;padding:6px 14px;border-radius:7px}
.subno{font-weight:700;font-size:11.5pt;color:#111;margin-top:6px}
.subdate{font-size:9pt;color:#333;margin-top:3px}
.contact{margin-top:8px;border-top:2px solid var(--p);border-bottom:1px solid var(--line);padding:6px 2px;font-size:8.8pt;display:flex;justify-content:space-between;color:#222}
.sec{margin-top:12px;border:1.5px solid var(--line);border-radius:9px;overflow:hidden;break-inside:avoid}
.sec-h{background:var(--pl);color:var(--pd);font-weight:700;font-size:11pt;padding:7px 12px;display:flex;align-items:center;gap:8px}
.sec-h .ico{background:var(--p);color:#fff;width:22px;height:22px;border-radius:6px;display:inline-flex;align-items:center;justify-content:center;font-size:10pt}
.sec-h.dark{background:var(--p);color:#fff}
.info{padding:12px 14px;display:grid;grid-template-columns:1fr 1fr;gap:5px 22px;font-size:10pt}
.info .row{display:flex;gap:6px}.info .k{width:38mm;color:#333}.info .c{width:4mm}.info .v{font-weight:700}
table{width:100%;border-collapse:collapse;font-size:10pt}
th{background:#fff;color:var(--pd);text-align:left;padding:7px 10px;border-bottom:1.5px solid var(--line);font-size:9.5pt}
td{padding:9px 10px;border-bottom:1px solid #eee7ff}
tr:last-child td{border-bottom:none}
.num{text-align:right}
.totalrow td{background:var(--pl);font-weight:700;color:var(--pd);font-size:11pt;border-top:2px solid var(--p)}
.cols{display:grid;grid-template-columns:1.25fr 1fr;gap:12px;margin-top:12px;align-items:start}
.sumrow{display:flex;padding:7px 12px;font-size:10pt;border-bottom:1px solid #eee7ff}
.sumrow .k{flex:1;color:#333}.sumrow .c{width:5mm}.sumrow .v{font-weight:400}
.sumrow.hl{background:var(--pl);font-weight:700;color:var(--pd)}
.box{border-radius:9px;padding:11px 14px;margin-bottom:10px}
.box .lab{font-size:10pt;font-weight:700}
.box .amt{font-size:16pt;font-weight:700;margin-top:2px}
.due{background:#ffe4e6}.due .lab,.due .amt{color:#be123c}
.adv{background:#dcfce7}.adv .lab,.adv .amt{color:#15803d}
.status{border:1.5px solid var(--line);border-radius:9px;padding:10px 14px;display:flex;justify-content:space-between;align-items:center;font-size:10.5pt;font-weight:700;color:var(--pd)}
.pill{background:#fef3c7;color:#b45309;border:1.5px solid #f59e0b;border-radius:20px;padding:4px 14px;font-size:10pt;letter-spacing:.5px}
.words{margin-top:11px;background:var(--pl2);border:1px solid var(--line);border-radius:9px;padding:10px 14px;font-size:10pt}
.words b{color:var(--pd)}
.bottom{display:flex;justify-content:space-between;align-items:flex-end;gap:16px;margin-top:20px}
.sign{width:52mm;text-align:center;font-size:9.5pt}
.sign .line{border-top:1.5px solid #333;margin-bottom:6px;height:30px}
.qr{width:20mm;height:20mm;border:2px solid #111;padding:3px;background:#fff}
.qr svg{width:100%;height:100%;display:block;shape-rendering:crispEdges}
.foot{margin-top:auto}
.foot-note{text-align:center;font-style:italic;font-size:8.8pt;color:#555;padding:8px 0 6px}
.wave{background:var(--pd);color:#fff;text-align:center;font-size:9.5pt;padding:9px 0 10px;margin:0 -12mm;border-radius:50% 50% 0 0/14px 14px 0 0}
.wave b{font-weight:700}.wave span{margin:0 8px;opacity:.7}

/* Screen-only controls; never printed and never part of the slip. */
.screen-bar{position:fixed;top:10px;right:12px;display:flex;gap:8px;z-index:50}
.screen-bar a,.screen-bar button{font:600 12px/1 'Inter',sans-serif;padding:9px 14px;border-radius:8px;border:1px solid #c4b5fd;background:#fff;color:#4c1d95;cursor:pointer;text-decoration:none}
.screen-bar button{background:#5b21b6;color:#fff;border-color:#5b21b6}
.screen-only{max-width:210mm;margin:18px auto 40px;padding:0 12mm;font-family:'Inter',sans-serif}
.screen-only .rev{border:1px solid #ddd6fe;border-radius:10px;padding:14px 16px;background:#faf8ff}
.screen-only h3{margin:0 0 4px;font-size:14px;color:#4c1d95}
.screen-only p{margin:0 0 12px;font-size:12px;color:#555}
.screen-only .row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
.screen-only label{display:flex;flex-direction:column;gap:4px;font-size:11px;color:#444}
.screen-only input,.screen-only select{padding:8px 10px;border:1px solid #c4b5fd;border-radius:8px;font:12px 'Inter',sans-serif;min-width:150px}
.screen-only button{padding:9px 14px;border:0;border-radius:8px;background:#b91c1c;color:#fff;font:600 12px 'Inter',sans-serif;cursor:pointer}

/* Centre the A4 sheet on screen. Print rules below still force a full-bleed page. */
@media screen{
  .page{margin:0 auto}
}

/* ===== Print lock: design must not change in print / PDF ===== */
@media print{
  html,body{width:210mm!important;height:297mm!important;margin:0!important;padding:0!important;background:#fff!important;overflow:hidden!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  *,*::before,*::after{-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important;color-adjust:exact!important}
  .page{width:210mm!important;height:297mm!important;min-height:297mm!important;max-height:297mm!important;margin:0!important;padding:11mm 12mm 0 12mm!important;box-shadow:none!important;overflow:hidden!important;position:relative!important;page-break-inside:avoid!important;break-inside:avoid!important;page-break-before:avoid!important;page-break-after:avoid!important;break-before:avoid-page!important;break-after:avoid-page!important}
  .sec,.cols,.bottom,.words,.status,.box,table,tr,td,th{break-inside:avoid!important;page-break-inside:avoid!important}
  .hdr,.contact,.foot{break-inside:avoid!important;page-break-inside:avoid!important}
  img,svg{max-width:100%!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}
  a{color:inherit!important;text-decoration:none!important}
  .screen-bar,.screen-only{display:none!important}
}
</style></head><body>

<div class="screen-bar">
    <a href="{{ $backUrl ?? route('fees.collection.show', $payment->student) }}">&larr; Back</a>
    <button type="button" onclick="window.print()">Print</button>
</div>

<div class="page">
<div class="hdr">
<svg class="crest" viewBox="0 0 64 74"><path d="M32 2 L58 9 V34 C58 55 47 66 32 72 C17 66 6 55 6 34 V9 Z" fill="#1e3a8a" stroke="#b45309" stroke-width="2.5"/><path d="M32 9 L51 14 V34 C51 49 43 58 32 63 C21 58 13 49 13 34 V14 Z" fill="#312e81"/><text x="32" y="30" text-anchor="middle" font-size="18" fill="#fff">E</text></svg>
<div class="titlebox"><div class="school">{{ school_setting('school_name', 'EduSphere School') }}</div><div class="meta"><span>&#128197; Established: {{ school_setting('school_established_year') ?: '—' }}</span><span>|</span><span>&#127970; EIIN: {{ school_setting('school_eiin') ?: '—' }}</span></div></div>
<div class="badgebox"><span class="badge">PAYMENT SLIP</span><div class="subno">{{ $slip['receipt_no'] }}</div><div class="subdate">&#128197; {{ $slip['paid_at']?->format('d M Y, h:i A') }}</div></div>
</div>
<div class="contact"><span>&#128205; {{ school_setting('school_address') }}</span><span>|</span><span>&#128222; {{ school_setting('school_phone') }}</span><span>|</span><span>&#9993; {{ school_setting('school_email') }}</span></div>

<div class="sec"><div class="sec-h"><span class="ico">&#128100;</span> STUDENT INFORMATION</div>
<div class="info">
<div class="row"><span class="k">Student Name</span><span class="c">:</span><span class="v">{{ $slip['student']['name'] }}</span></div>
<div class="row"><span class="k">Student ID</span><span class="c">:</span><span class="v">{{ $slip['student']['student_code'] }}</span></div>
<div class="row"><span class="k">Class &amp; Section</span><span class="c">:</span><span class="v">{{ $slip['student']['class_section'] }}</span></div>
<div class="row"><span class="k">Academic Session</span><span class="c">:</span><span class="v">{{ $slip['session'] }}</span></div>
<div class="row"><span class="k">Guardian Name</span><span class="c">:</span><span class="v">{{ $slip['student']['guardian'] ?: '—' }}</span></div>
<div class="row"><span class="k">Payment Method</span><span class="c">:</span><span class="v">{{ $slip['method'] }}</span></div>
</div></div>

<div class="sec"><div class="sec-h dark"><span class="ico">&#9776;</span> FEE DETAILS</div>
<table><tr><th style="width:10mm">SL</th><th>Fee Head</th><th>Period</th><th class="num">Amount ({{ $currency }})</th><th class="num">Scholarship ({{ $currency }})</th><th class="num">Payable ({{ $currency }})</th></tr>
@foreach ($slip['lines'] as $index => $line)
<tr><td>{{ $index + 1 }}</td><td>{{ $line['fee'] }}</td><td>{{ $line['period'] }}</td><td class="num">{{ $f($line['amount']) }}</td><td class="num">{{ $line['scholarship'] > 0 ? $f($line['scholarship']) : '—' }}</td><td class="num">{{ $f($line['payable']) }}</td></tr>
@endforeach
<tr class="totalrow"><td colspan="5">Total Amount</td><td class="num">{{ $currency }} {{ $f($slip['net_payable']) }}</td></tr>
</table></div>

<div class="cols">
<div class="sec" style="margin-top:0"><div class="sec-h dark"><span class="ico">&#35;</span> PAYMENT SUMMARY</div>
<div class="sumrow"><span class="k">Total Fee Amount</span><span class="c">:</span><span class="v">{{ $currency }} {{ $f($slip['total_fee']) }}</span></div>
<div class="sumrow"><span class="k">Total Scholarship / Discount</span><span class="c">:</span><span class="v">{{ $currency }} {{ $f($slip['total_scholarship']) }}</span></div>
<div class="sumrow"><span class="k">Net Payable Amount</span><span class="c">:</span><span class="v">{{ $currency }} {{ $f($slip['net_payable']) }}</span></div>
<div class="sumrow"><span class="k">Previous Outstanding Due</span><span class="c">:</span><span class="v">{{ $currency }} {{ $f($slip['previous_due']) }}</span></div>
<div class="sumrow"><span class="k">Amount Paid (This Slip)</span><span class="c">:</span><span class="v">{{ $currency }} {{ $f($slip['amount_paid']) }}</span></div>
<div class="sumrow hl"><span class="k">Total Paid To Date</span><span class="c">:</span><span>{{ $currency }} {{ $f($slip['paid_to_date']) }}</span></div>
</div>
<div>
<div class="box due"><div class="lab">Outstanding Due</div><div class="amt">{{ $currency }} {{ $f($slip['outstanding']) }}</div></div>
<div class="box adv"><div class="lab">Advance Balance</div><div class="amt">{{ $currency }} {{ $f($slip['advance']) }}</div></div>
<div class="status"><span>Payment Status</span><span class="pill">{{ $slip['status'] }}</span></div>
</div>
</div>

<div class="words"><b>Amount in Words:</b> {{ $slip['words'] }} &nbsp;|&nbsp; <b>Payment Method:</b> {{ $slip['method'] }} &nbsp;|&nbsp; <b>Slip No.:</b> {{ $slip['receipt_no'] }}</div>
<div class="words" style="margin-top:8px"><b>Note:</b> Please keep this payment slip for future reference. The outstanding due of {{ $currency }} {{ $f($slip['outstanding']) }} is payable at the school office.</div>

<div class="bottom">
<div style="font-size:9.5pt;color:#333"><div style="color:#4c1d95;font-weight:700">&#128100; Received By</div><div style="font-weight:700;font-size:11pt;margin-top:3px;color:#111">{{ $slip['received_by'] }}</div></div>
<div class="qr" id="slip-qr" data-qr="{{ $slip['qr_payload'] }}" aria-label="Receipt QR code"></div>
<div class="sign"><div class="line"></div>Authorised Signature</div>
</div>

<div class="foot"><div class="foot-note">{{ $currency }} {{ $f($slip['amount_paid']) }} received with thanks. This is a computer-generated payment slip.</div><div class="wave"><span style="margin:0">&lt;/&gt;</span> Developed by <b>Jhinuk Tech</b> <span>|</span> www.jhinuktech.com</div></div>
</div>

<script src="{{ asset('vendor/qrcode/qrcode.js') }}"></script>
<script>
    (function () {
        var host = document.getElementById('slip-qr');
        if (!host || typeof qrcode !== 'function') return;

        try {
            var qr = qrcode(0, 'M');
            qr.addData(host.getAttribute('data-qr') || '');
            qr.make();
            host.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0, scalable: true });
        } catch (error) {
            host.textContent = '';
        }
    })();
</script>

@can('fees.reverse')
    @if ($payment->status === 'completed')
        <div class="screen-only">
            <div class="rev">
                <h3>Reverse this payment</h3>
                <p>The slip stays in the ledger; the allocation is released and the balance recalculated. This is recorded in the audit log.</p>
                <form method="POST" action="{{ route('payments.reverse', $payment) }}" onsubmit="return confirm('Reverse this payment? This cannot be undone and is recorded in the audit log.')">
                    @csrf
                    <div class="row">
                        <label>Reversal type
                            <select name="type" required>
                                <option value="cancellation">Cancellation</option>
                                <option value="refund">Refund</option>
                            </select>
                        </label>
                        <label>Reason
                            <input type="text" name="reason" maxlength="255" required placeholder="Why is this payment being reversed?">
                        </label>
                        <button type="submit">Reverse payment</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan

@if (request()->boolean('print'))
<script>
    window.addEventListener('load', function () {
        window.setTimeout(function () { window.print(); }, 250);
    });
</script>
@endif
</body></html>
