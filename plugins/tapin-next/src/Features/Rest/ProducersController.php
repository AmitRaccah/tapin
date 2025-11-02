<?php

namespace Tapin\Events\Features\Rest;

use Tapin\Events\Core\Service;
use Tapin\Events\Support\Security;
use WP_Error;
use WP_REST_Request;

final class ProducersController implements Service
{
    private const MAX_PER_PAGE = 100;
    /** @var array<string,bool> */
    private array $allowedOrderBy = [
        'display_name'  => true,
        'user_nicename' => true,
        'ID'            => true,
    ];

    public function register(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route('tapin/v1', '/producers', [
                'methods'             => 'GET',
                'permission_callback' => [$this, 'authorize'],
                'callback'            => [$this, 'list'],
            ]);
        });
    }

    /**
     * Only authenticated producers/managers may access the endpoint.
     *
     * @return true|WP_Error
     */
    public function authorize()
    {
        $result = Security::producer(true);
        if (!$result->allowed) {
            return new WP_Error(
                'tapin_forbidden',
                __('אין לך הרשאה לצפות ברשימת המפיקים.', 'tapin'),
                ['status' => 403]
            );
        }

        return true;
    }

    public function list(WP_REST_Request $req)
    {
        $perRequested = (int) ($req['per_page'] ?? 50);
        $per          = min(self::MAX_PER_PAGE, max(1, $perRequested));

        $orderbyRaw = sanitize_text_field($req['orderby'] ?? 'display_name');
        $orderby    = isset($this->allowedOrderBy[$orderbyRaw]) ? $orderbyRaw : 'display_name';

        $orderRaw = strtoupper(sanitize_text_field($req['order'] ?? 'ASC'));
        $order    = in_array($orderRaw, ['ASC', 'DESC'], true) ? $orderRaw : 'ASC';

        $args = [
            'number' => $per,
            'orderby'=> $orderby,
            'order'  => $order,
            'role'   => 'producer',
            'fields' => ['ID', 'display_name', 'user_nicename'],
        ];

        $group = sanitize_text_field($req['group'] ?? '');
        if ($group !== '') {
            $slug = sanitize_title($group);
            if ($slug !== '') {
                if (taxonomy_exists('user_group')) {
                    $args['tax_query'] = [[
                        'taxonomy' => 'user_group',
                        'field'    => 'slug',
                        'terms'    => $slug,
                    ]];
                } else {
                    $args['meta_query'] = [[
                        'key'   => 'user_group',
                        'value' => $slug,
                    ]];
                }
            }
        }

        $users = get_users($args);
        $out   = [];
        foreach ($users as $user) {
            $out[] = [
                'id'   => $user->ID,
                'name' => $user->display_name,
                'slug' => $user->user_nicename,
            ];
        }

        return rest_ensure_response($out);
    }
}

