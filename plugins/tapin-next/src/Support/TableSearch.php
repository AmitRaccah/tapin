<?php
namespace Tapin\Events\Support;

final class TableSearch {
    private static bool $bootstrapped = false;

    /**
     * @param array{
     *     input_id?: string,
     *     placeholder?: string,
     *     wrapper_tag?: string,
     *     wrapper_class?: string,
     *     input_class?: string,
     *     row_selector: string,
     *     empty_selector?: string,
     *     detail_row_class?: string
     * } $args
     */
    public static function render(array $args): string {
        $defaults = [
            'input_id'         => '',
            'placeholder'      => '',
            'wrapper_tag'      => 'div',
            'wrapper_class'    => 'tapin-table-search tapin-form-row',
            'input_class'      => 'tapin-table-search__input',
            'row_selector'     => '',
            'empty_selector'   => '',
            'detail_row_class' => '',
        ];

        $config = array_merge($defaults, $args);

        if ($config['row_selector'] === '') {
            return '';
        }

        if ($config['input_id'] === '') {
            $config['input_id'] = 'tapin-search-' . wp_rand(1000, 9999);
        }

        $wrapperTag = is_string($config['wrapper_tag']) ? preg_replace('/[^a-z0-9:-]/i', '', $config['wrapper_tag']) : '';
        if (!$wrapperTag) {
            $wrapperTag = 'div';
        }

        $wrapperClass = trim((string) $config['wrapper_class']);
        $inputClass   = trim((string) $config['input_class']);

        $inputHtml = sprintf(
            '<input id="%1$s" type="text"%2$s placeholder="%3$s">',
            esc_attr($config['input_id']),
            $inputClass !== '' ? ' class="' . esc_attr($inputClass) . '"' : '',
            esc_attr((string) $config['placeholder'])
        );

        $html = sprintf(
            '<%1$s%2$s>%3$s</%1$s>',
            $wrapperTag,
            $wrapperClass !== '' ? ' class="' . esc_attr($wrapperClass) . '"' : '',
            $inputHtml
        );

        $bootstrap = self::bootstrapScript();
        $payload   = [
            'input'           => '#' . $config['input_id'],
            'rowSelector'     => $config['row_selector'],
            'emptySelector'   => $config['empty_selector'],
            'detailRowClass'  => $config['detail_row_class'],
        ];

        $script = '<script>(window.TapinTableSearch&&window.TapinTableSearch.register(' . wp_json_encode($payload) . '));</script>';

        return $html . $bootstrap . $script;
    }

    private static function bootstrapScript(): string {
        if (self::$bootstrapped) {
            return '';
        }

        self::$bootstrapped = true;

        return <<<'HTML'
<script>
(function(){
  if (window.TapinTableSearch) {
    return;
  }
  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }
  window.TapinTableSearch = {
    register: function(conf) {
      if (!conf || !conf.input || !conf.rowSelector) {
        return;
      }
      ready(function() {
        var input = document.querySelector(conf.input);
        if (!input || input.dataset.tapinSearchInit === '1') {
          return;
        }
        input.dataset.tapinSearchInit = '1';
        var detailClass = conf.detailRowClass || '';
        var emptySelector = conf.emptySelector || '';

        function filter() {
          var rows = Array.prototype.slice.call(document.querySelectorAll(conf.rowSelector));
          var query = input.value.trim().toLowerCase();
          var visible = 0;

          rows.forEach(function(row) {
            var haystack = (row.getAttribute('data-search') || row.textContent || '').toLowerCase();
            var match = !query || haystack.indexOf(query) !== -1;
            row.style.display = match ? '' : 'none';
            if (detailClass && row.nextElementSibling && row.nextElementSibling.classList.contains(detailClass)) {
              row.nextElementSibling.style.display = match ? '' : 'none';
            }
            if (match) {
              visible++;
            }
          });

          if (emptySelector) {
            var emptyEl = document.querySelector(emptySelector);
            if (emptyEl) {
              emptyEl.style.display = visible ? 'none' : '';
            }
          }
        }

        input.addEventListener('input', filter);
        filter();
      });
    }
  };
})();
</script>
HTML;
    }
}
