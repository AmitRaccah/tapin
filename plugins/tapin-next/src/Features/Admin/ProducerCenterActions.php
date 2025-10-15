<?php
namespace Tapin\Events\Features\Rest;

use Tapin\Events\Core\Service;

final class ProducersController implements Service {
    public function register(): void {
        add_action('rest_api_init', function(){
            register_rest_route('tapin/v1','/producers',[
                'methods'=>'GET',
                'permission_callback'=>'__return_true',
                'callback'=>[$this,'list']
            ]);
        });
    }

    public function list(\WP_REST_Request $req){
        $per     = max(1, (int)($req['per_page'] ?? 200));
        $orderby = sanitize_text_field($req['orderby'] ?? 'display_name');
        $order   = strtoupper(sanitize_text_field($req['order'] ?? 'ASC'));
        $args = [
            'number'=>$per, 'orderby'=>$orderby, 'order'=> in_array($order,['ASC','DESC'],true)?$order:'ASC',
            'role'=>'producer', 'fields'=>['ID','display_name','user_nicename']
        ];

        $group = sanitize_text_field($req['group'] ?? '');
        if ($group) {
            $slug = sanitize_title($group);
            if (taxonomy_exists('user_group')) {
                $args['tax_query']=[[ 'taxonomy'=>'user_group','field'=>'slug','terms'=>$slug ]];
            } else {
                $args['meta_query']=[[ 'key'=>'user_group','value'=>$slug ]];
            }
        }

        $users = get_users($args);
        $out=[];
        foreach ($users as $u){
            $out[]=[
                'id'=>$u->ID,'name'=>$u->display_name,'nicename'=>$u->user_nicename,
                'avatar'=> get_avatar_url($u->ID, ['size'=>256]),
                // עדכון ל-UM: /user/{nicename}/
                'profile'=> home_url('/user/'.$u->user_nicename.'/'),
            ];
        }
        return rest_ensure_response($out);
    }
}
