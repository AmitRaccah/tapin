<?php
namespace Tapin\Events\Support;

final class Assets {
    private const SHARED_CSS = <<<'CSS'
:root {
  --tapin-radius-md: 12px;
  --tapin-radius-lg: 16px;
  --tapin-primary-color: #2a1a5e;
  --tapin-text-dark: #1f2937;
  --tapin-text-light: #334155;
  --tapin-border-color: #e5e7eb;
  --tapin-success-bg: #16a34a;
  --tapin-danger-bg: #ef4444;
  --tapin-warning-bg: #f59e0b;
  --tapin-info-bg: #0ea5e9;
  --tapin-ghost-bg: #f1f5f9;
  --tapin-card-shadow: 0 4px 12px rgba(2,6,23,.05);
}

.tapin-scope {
  --tapin-radius-md: 12px;
  --tapin-radius-lg: 16px;
  --tapin-primary-color: #2a1a5e;
  --tapin-text-dark: #1f2937;
  --tapin-text-light: #334155;
  --tapin-border-color: #e5e7eb;
  --tapin-success-bg: #16a34a;
  --tapin-danger-bg: #ef4444;
  --tapin-warning-bg: #f59e0b;
  --tapin-info-bg: #0ea5e9;
  --tapin-ghost-bg: #f1f5f9;
  --tapin-card-shadow: 0 4px 12px rgba(2,6,23,.05);
  direction: rtl;
  text-align: right;
}


.tapin-scope .tapin-center-container,
.tapin-center-container {
  max-width: 1100px;
  margin-inline: auto;
  direction: rtl;
  text-align: right;
}

.tapin-center-container *,
.tapin-center-container *::before,
.tapin-center-container *::after {
  box-sizing: border-box;
}

.tapin-title {
  font-size: 28px;
  font-weight: 800;
  color: var(--tapin-primary-color);
  margin: 14px 0 20px;
}

.tapin-form-grid {
  display: grid;
  gap: 16px;
}

.tapin-card {
  background: #fff;
  border: 1px solid var(--tapin-border-color);
  border-radius: var(--tapin-radius-lg);
  padding: 20px;
  box-shadow: var(--tapin-card-shadow);
  transition: opacity .3s;
}

.tapin-card--paused {
  border-inline-start: 4px solid var(--tapin-warning-bg);
  opacity: .85;
}

.tapin-card__header {
  display: flex;
  gap: 16px;
  align-items: flex-start;
  margin-bottom: 16px;
}

.tapin-card__thumb {
  width: 80px;
  height: 80px;
  object-fit: cover;
  border-radius: var(--tapin-radius-md);
  display: block;
  flex-shrink: 0;
}

.tapin-card__title {
  margin: 0 0 8px;
  font-size: 1.25rem;
}

.tapin-card__title a {
  color: inherit;
  text-decoration: none;
}

.tapin-card__title a:hover {
  color: var(--tapin-primary-color);
}

.tapin-card__meta {
  font-size: .9rem;
  color: var(--tapin-text-light);
}

.tapin-status-badge {
  font-size: 12px;
  font-weight: 700;
  margin-inline-start: 6px;
}

.tapin-status-badge--paused {
  color: var(--tapin-warning-bg);
}

.tapin-status-badge--pending {
  color: var(--tapin-info-bg);
}

.tapin-form-row {
  margin-bottom: 16px;
}

.tapin-form-row:last-child {
  margin-bottom: 0;
}

.tapin-form-row label {
  display: block;
  margin-bottom: 6px;
  font-weight: 700;
  color: var(--tapin-text-dark);
}

.tapin-form-row input[type="text"],
.tapin-form-row input[type="number"],
.tapin-form-row input[type="email"],
.tapin-form-row input[type="url"],
.tapin-form-row input[type="file"],
.tapin-form-row input[type="datetime-local"],
.tapin-form-row textarea {
  width: 100%;
  padding: 12px 14px;
  border: 1px solid var(--tapin-border-color);
  border-radius: var(--tapin-radius-md);
  background: #fff;
  transition: border-color .2s, box-shadow .2s;
}

.tapin-form-row input:focus,
.tapin-form-row textarea:focus {
  border-color: var(--tapin-primary-color);
  box-shadow: 0 0 0 3px rgba(42,26,94,0.1);
  outline: none;
}

.tapin-columns-2 {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 16px;
}

.tapin-actions {
  display: flex;
  gap: 12px;
  align-items: center;
  margin-top: 20px;
  flex-wrap: wrap;
}

.tapin-btn {
  padding: 12px 20px;
  border-radius: var(--tapin-radius-md);
  border: 0;
  cursor: pointer;
  font-weight: 600;
  transition: opacity .2s;
  font-size: 1rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  white-space: nowrap;
}

.tapin-btn:hover {
  opacity: .85;
}

.tapin-btn--primary {
  background: var(--tapin-success-bg);
  color: #fff;
}

.tapin-btn--danger {
  background: var(--tapin-danger-bg);
  color: #fff;
}

.tapin-btn--warning {
  background: var(--tapin-warning-bg);
  color: #fff;
}

.tapin-btn--ghost {
  background: var(--tapin-ghost-bg);
  color: var(--tapin-text-dark);
}

.tapin-notice {
  padding: 12px;
  border-radius: 8px;
  margin-bottom: 20px;
  direction: rtl;
  text-align: right;
}

.tapin-notice--error {
  background: #fff4f4;
  border: 1px solid #f3c2c2;
  color: #7f1d1d;
}

.tapin-notice--success {
  background: #f0fff4;
  border: 1px solid #b8e1c6;
  color: #065f46;
}

.tapin-notice--warning {
  background: #fff7ed;
  border: 1px solid #ffd7b5;
  color: #854d0e;
}

.tapin-cat-list {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 6px;
}

.tapin-cat-chip {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: 1px solid var(--tapin-border-color);
  border-radius: 999px;
  padding: 6px 12px;
  background: #fff;
  cursor: pointer;
  user-select: none;
}

.tapin-cat-chip input {
  accent-color: var(--tapin-primary-color);
}

@media (max-width: 768px) {
  .tapin-card__header {
    flex-direction: column;
  }
  .tapin-columns-2 {
    grid-template-columns: 1fr;
  }
  .tapin-actions {
    flex-direction: column;
    align-items: stretch;
  }
  .tapin-btn {
    width: 100%;
  }
}
CSS;

    private const SALE_WINDOWS_CSS = <<<'CSS'
.tapin-pw {
  direction: rtl;
  text-align: right;
  margin: 10px 0 16px;
}

.tapin-pw__title {
  font-weight: 800;
  color: var(--tapin-primary-color);
  margin: 0 0 10px;
  font-size: 16px;
  text-align: center;
}

.tapin-pw__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 12px;
}

.tapin-pw-card {
  background: #fff;
  border: 1px solid var(--tapin-border-color);
  border-radius: 14px;
  padding: 12px 14px;
  box-shadow: var(--tapin-card-shadow);
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.tapin-pw-card--current {
  box-shadow: 0 0 0 2px rgba(22,163,74,.15) inset;
}

.tapin-pw-card--upcoming {
  box-shadow: 0 0 0 2px rgba(14,165,233,.12) inset;
}

.tapin-pw-card--past {
  opacity: .7;
}

.tapin-pw-card__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.tapin-pw-card__price {
  font-weight: 800;
  font-size: 1.15rem;
}

.tapin-pw-card__dates {
  font-size: .95rem;
  color: var(--tapin-text-light);
  line-height: 1.3;
}

.tapin-pw-card__badge {
  font-size: .8rem;
  font-weight: 700;
  white-space: nowrap;
}

.tapin-pw-card--current .tapin-pw-card__badge {
  color: var(--tapin-success-bg);
}

.tapin-pw-card--upcoming .tapin-pw-card__badge {
  color: var(--tapin-info-bg);
}

.tapin-pw-card--past .tapin-pw-card__badge {
  color: #94a3b8;
  text-decoration: line-through;
}

.tapin-pw__hint {
  font-size: .8rem;
  color: var(--tapin-text-light);
  margin-top: 6px;
  text-align: center;
}
CSS;

    private const REPEATER_CSS = <<<'CSS'
.tapin-sale-w {
  border: 1px solid var(--tapin-border-color);
  border-radius: 12px;
  padding: 12px;
}

.tapin-sale-w__row {
  display: grid;
  grid-template-columns: 1fr 1fr 160px 40px;
  gap: 10px;
  margin-bottom: 10px;
}

.tapin-sale-w__row:last-child {
  margin-bottom: 0;
}

.tapin-sale-w__remove {
  width: 40px;
  height: 40px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--tapin-border-color);
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
}

.tapin-sale-w__add {
  margin-top: 10px;
}

@media (max-width: 640px) {
  .tapin-sale-w__row {
    grid-template-columns: 1fr;
  }
}
CSS;

    public static function sharedCss(): string {
        return self::SHARED_CSS;
    }

    public static function saleWindowsCss(): string {
        return self::SALE_WINDOWS_CSS;
    }

    public static function repeaterCss(): string {
        return self::REPEATER_CSS;
    }

    public static function combinedCss(string ...$chunks): string {
        $chunks = array_filter(array_map('trim', $chunks));
        return implode("\n", $chunks);
    }
}
