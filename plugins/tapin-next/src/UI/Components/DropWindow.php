<?php

declare(strict_types=1);

namespace Tapin\Events\UI\Components;

final class DropWindow
{
    private function __construct()
    {
    }

    public static function header(string $title, string $imageUrl = '', string $permalink = '', bool $isOpen = true): string
    {
        $expanded = $isOpen ? 'true' : 'false';
        $imageUrl = trim($imageUrl);
        $permalink = trim($permalink);

        ob_start();
        ?>
        <button class="tapin-pa-event__header" type="button" data-event-toggle aria-expanded="<?php echo esc_attr($expanded); ?>">
          <div class="tapin-pa-event__summary">
            <?php if ($imageUrl !== ''): ?>
              <img class="tapin-pa-event__image" src="<?php echo esc_url($imageUrl); ?>" alt="" loading="lazy">
            <?php else: ?>
              <div class="tapin-pa-event__image" aria-hidden="true"></div>
            <?php endif; ?>
            <div class="tapin-pa-event__text">
              <h4>
                <?php if ($permalink !== ''): ?>
                  <a href="<?php echo esc_url($permalink); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html($title); ?>
                  </a>
                <?php else: ?>
                  <?php echo esc_html($title); ?>
                <?php endif; ?>
              </h4>
            </div>
          </div>
          <span class="tapin-pa-event__chevron" aria-hidden="true">&#9662;</span>
        </button>
        <?php

        return (string) ob_get_clean();
    }

    public static function openWrapper(bool $isOpen): string
    {
        return sprintf('<div class="tapin-pa-event%s">', $isOpen ? ' is-open' : '');
    }

    public static function closeWrapper(): string
    {
        return '</div>';
    }

    public static function openPanel(bool $isOpen): string
    {
        return sprintf('<div class="tapin-pa-event__panel"%s>', $isOpen ? '' : ' hidden');
    }

    public static function closePanel(): string
    {
        return '</div>';
    }
}
