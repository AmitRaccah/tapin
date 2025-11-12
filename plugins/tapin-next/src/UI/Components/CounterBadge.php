<?php

declare(strict_types=1);

namespace Tapin\Events\UI\Components;

final class CounterBadge
{
    /**
     * @param array<string, string> $opts
     */
    public static function render(int $count, array $opts = []): string
    {
        if ($count <= 0) {
            return '';
        }

        $extraClasses = trim((string) ($opts['class'] ?? ''));
        $classes = trim('tapin-counter-badge tapin-counter-badge--danger' . ($extraClasses !== '' ? ' ' . $extraClasses : ''));

        return sprintf(
            '<span class="%s" aria-hidden="true">%s</span>',
            esc_attr($classes),
            esc_html((string) $count)
        );
    }
}
