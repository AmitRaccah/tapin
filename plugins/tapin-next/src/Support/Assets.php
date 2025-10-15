<?php
namespace Tapin\Events\Support;

final class Assets {
    public static function sharedCss(): string {
        return '
        :root{--tapin-radius-md:12px;--tapin-radius-lg:16px;--tapin-primary-color:#2a1a5e;--tapin-text-dark:#1f2937;--tapin-text-light:#334155;--tapin-border-color:#e5e7eb;--tapin-success-bg:#16a34a;--tapin-danger-bg:#ef4444;--tapin-warning-bg:#f59e0b;--tapin-info-bg:#0ea5e9;--tapin-ghost-bg:#f1f5f9;--tapin-card-shadow:0 4px 12px rgba(2,6,23,.05)}
        .tapin-center-container{max-width:1100px;margin-inline:auto;direction:rtl;text-align:right}
        .tapin-title{font-size:28px;font-weight:800;color:var(--tapin-primary-color);margin:14px 0 20px}
        .tapin-card{background:#fff;border:1px solid var(--tapin-border-color);border-radius:var(--tapin-radius-lg);padding:20px;box-shadow:var(--tapin-card-shadow)}
        .tapin-form-row{margin-bottom:16px}
        .tapin-form-row label{display:block;margin-bottom:6px;font-weight:700;color:var(--tapin-text-dark)}
        .tapin-columns-2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .tapin-actions{display:flex;gap:12px;align-items:center;margin-top:20px;flex-wrap:wrap}
        .tapin-btn{padding:12px 20px;border-radius:12px;border:0;cursor:pointer;font-weight:600}
        .tapin-btn--primary{background:var(--tapin-success-bg);color:#fff}
        .tapin-btn--danger{background:var(--tapin-danger-bg);color:#fff}
        .tapin-btn--warning{background:var(--tapin-warning-bg);color:#fff}
        .tapin-btn--ghost{background:var(--tapin-ghost-bg);color:#111827}
        @media(max-width:768px){.tapin-columns-2{grid-template-columns:1fr}.tapin-actions{flex-direction:column;align-items:stretch}.tapin-btn{width:100%}}
        ';
    }
}
