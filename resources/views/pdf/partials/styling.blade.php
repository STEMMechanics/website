@page { margin: 30px; size: A4; }

@font-face {
font-family: 'Poppins';
font-style: normal;
font-weight: 400;
src: url('{{ resource_path('fonts/Poppins-Regular.ttf') }}') format('truetype');
}

@font-face {
font-family: 'Poppins';
font-style: normal;
font-weight: 700;
src: url('{{ resource_path('fonts/Poppins-Bold.ttf') }}') format('truetype');
}

body { font-family: 'Poppins', sans-serif; color: #333; font-size: 12px; line-height: 1; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; border-spacing: 0; }
td, th { padding: 0; margin: 0; }

.page { position: relative; min-height: 1030px; }
.page + .page { page-break-before: always; }

.header { margin-bottom: 18px; }
.logo-wrap { width: 30%; text-align: left; vertical-align: top; }
.logo { width: 180px; height: auto; margin-top: 4px; object-fit: contain; }
.company { width: 30%; font-size: 10px; line-height: 0.9; }
.headline { text-align: right; width: 40%; font-size: 22px; font-weight: 700; color: #333; vertical-align: bottom; line-height: 0.9; }
.headline .underline { text-decoration: underline; }

.meta-wrap { border-top: 1px solid #d6d6d6; margin-bottom: 10px; }
.bill-to { width: 45%; vertical-align: top; font-size: 12px; line-height: 0.9; padding: 18px 14px 0; }
.summary-wrap { width: 55%; vertical-align: top; }

.summary { border-collapse: collapse; }
.summary th,
.summary td { text-align: center; vertical-align: middle; }
.summary th:last-child, .summary td:last-child { border-right: 1px solid #d9d9d9; }
.summary th { padding: 5px 3px; color: #1da1e6; font-size: 11px; font-weight: 700; border-left: 1px solid #d9d9d9; }
.summary th.pay { background: #1da1e6; color: #fff; }
.summary td { padding: 7px 3px; font-size: 11px; border-bottom: 1px solid #d9d9d9; border-left: 1px solid #d9d9d9; }
.summary td.invoice-number,
.summary td.quote-number { font-size: 18px; }
.summary td.pay { background: #1da1e6; color: #fff; font-weight: 700; font-size: 18px; white-space: nowrap; border-bottom-color: #1da1e6;}

.quote-validity { margin-top: 12px; font-size: 10px; color: #666; font-style: italic; text-align: right; }

.quote-details { margin: 6px 0 16px; font-size: 12px; text-align: center;}
.quote-title { font-size: 14px; font-weight: 700; margin-top: 18px; margin-bottom: 8px; text-align: center; color: #1da1e6; }

.po { margin-top: 18px; font-size: 12px; }

.items { margin-bottom: 10px; }
.items.items-last { margin-bottom: 360px; }
.items thead th { font-size: 11px; color: #1da1e6; border-bottom: 1px solid #666; padding: 4px 6px; text-align: left; vertical-align: bottom; }
.items thead th .excl { font-size: 9px; }
.items thead th.right,
.items tbody td.right { text-align: right; }
.items tbody td { border-bottom: 1px solid #e3e3e3; padding: 7px 6px; vertical-align: middle; }
.line-desc { font-size: 11px; }
.line-note { font-size: 9px; color: #555; line-height: 1.2; margin-top: 2px; }

.continued { text-align: right; color: #666; font-size: 10px; margin-top: 4px; }

.bottom-block { position: absolute; left: 0; right: 0; bottom: 0; }
.totals { margin: 0 0 16px; }
.totals td { border-bottom: 1px solid #d9d9d9; padding: 5px 0; }
.totals td.label { text-align: right; padding-right: 12px; color: #1da1e6; width: 84%; }
.totals td.value { text-align: right; width: 16%; }
.totals tr.subtotal-row td { border-bottom: 1px solid #666; }
.totals .total-row td { font-size: 14px; font-weight: 700; color: #1da1e6; }

.tax-note { float: left; margin-top: 2px; font-size: 9px; color: #555; }

.footer { margin-top: 12px; font-size: 10px; }
.footer td { width: 33.33%; vertical-align: top; padding: 0 3px; }
.block { margin-bottom: 10px; padding: 0 3px; }
.heading { color: #1da1e6; font-weight: 700; text-transform: uppercase; }
.bank-table { width: 100%; border-collapse: collapse; font-size: 11px; }
.bank-table th { text-align: left; padding: 2px 0; color: #1da1e6; width: 15%; font-weight: 400; }
.bank-table td { padding: 1px 0; }
.thanks { color: #1da1e6; margin-top: 10px; }
