<?php

# Prevent access to categories user doesn't have access to
add_action('pre_get_posts', 'limit_frontend_categories_to_allowed');
add_action('pre_get_pages', 'limit_frontend_categories_to_allowed');

function get_allowed_categories($strict = true)
{
    global $wpdb;
    $allowed_categories = array_merge(["public"], array_map(
        function ($category) {
            return $category->slug;
        },
        $wpdb->get_results("SELECT * from wp_terms WHERE wp_terms.slug LIKE '%_public';")
    ));
    if (is_user_logged_in()) {
        $allowed_categories = array_merge($allowed_categories, ["parent"]);
        $current_user = wp_get_current_user();
        foreach ($current_user->roles as $role) {
            if (explode("_", $role)[1] == "parent") {
                $allowed_categories = array_merge($allowed_categories, [
                    (explode("_", $role)[0]) . "_parent"
                ]);
            } elseif (explode("_", $role)[1] == "leader") {
                $allowed_categories = array_merge($allowed_categories, [
                    (explode("_", $role)[0]) . "_parent",
                    (explode("_", $role)[0]) . "_leader",
                    "leader"
                ]);
            }
        }
    }
    if (!$strict) {
        $all_categories = get_categories();
        $allowed_categories = array_merge(
            $allowed_categories,
            array_map(
                function ($category) {
                    return $category->slug;
                },
                array_filter($all_categories, function ($category) {
                    return preg_match(
                        "/^(\d+_)?((public)|(parent)|(leader))$/",
                        $category->slug
                    );
                })
            )
        );
    }
    $allowed_categories = array_unique($allowed_categories, SORT_STRING);
    return $allowed_categories;
}

function limit_frontend_categories_to_allowed($query)
{
    // echo "<pre>POST Type = ".$query->query_vars['post_type']."</pre>";
    if ($query->query_vars['post_type'] == "nav_menu_item") {
        // echo "<pre>IS Nav Menu Item</pre>";
        return $query;
    }
    if (!is_admin() && !current_user_can("administrator")) {
        // Not a query for an admin page.
        // It's the main query for a front end page of your site.
        $allowed_categories = get_allowed_categories();
        $allowed_category_ids = array_map(function ($slug) {
            return get_category_by_slug($slug)->term_id;
        }, $allowed_categories);
        $query->query_vars['category__in'] = $allowed_category_ids;
        // $query->include = $allowed_categories;
        // echo ("<pre>" . print_r($query, true) . "</pre>");
        // echo "<pre>" . $GLOBALS['wp_query']->request . "</pre>";
        return $query;
    }
    return $query;
}

# Redirect pages and posts to /login if they don't have access
add_action('template_redirect', 'wpse_restrict_support');
function wpse_restrict_support()
{
    $allowed_categories = get_allowed_categories();
    if (!(has_category($allowed_categories) || current_user_can("administrator"))) {
        global $wp;
        wp_safe_redirect("/login?redirect_to=" . home_url($wp->request));
    }
}

# Generate Secure Links for uploaded media
add_filter('wp_handle_upload_prefilter', 'add_rand_str_to_upload_name');
function add_rand_str_to_upload_name($file)
{
    $file['name'] = random_str("32", '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') . "-" . $file['name'];
    return $file;
}

add_filter('wp_get_nav_menu_items', 'wpse31748_exclude_menu_items', null, 3);
function wpse31748_exclude_menu_items($items, $menu, $args)
{
    // Iterate over the items to search and destroy
    // print_r(count($items));
    $allowed_categories = get_allowed_categories();
    foreach ($items as $key => $item) {
        $post_categories = array_map(function ($term) {
            return $term->slug;
        }, get_the_category($item->object_id));
        if (count(array_intersect($post_categories, $allowed_categories)) === 0 && !current_user_can('administrator')) {
            unset($items[$key]);
        }
    }
    return $items;
}

function filter_posts_data($posts)
{
    if (!count($posts)) {
        return $posts;  // posts array is empty send it back with thanks.
    }
    $allowed_categories = get_allowed_categories();
    if (is_admin() || current_user_can("administrator")) {
        return $posts;
    }
    $posts = array_filter(
        $posts,
        function ($post) use ($allowed_categories) {
            $post_categories = get_the_category($post->ID);
            if (count($post_categories) > 0) {
                $post_categories = array_map(function ($term) {
                    return $term->slug;
                }, $post_categories);
            } else {
                $post_categories = ($post->ID);
            }
            return count(array_intersect($post_categories, $allowed_categories)) > 0;
        }
    );
    return $posts;
}
add_filter('the_posts', 'filter_posts_data');

add_action('after_setup_theme', 'remove_admin_bar');
function remove_admin_bar()
{
    $is_leader = false;
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $is_leader = array_filter($current_user->roles, function ($role) {
            $role_type = explode("_", $role)[1];
            return ($role_type == "leader" || $role_type == "admin");
        });
        $is_leader = count($is_leader) > 0;
    }
    if (!($is_leader || current_user_can('administrator'))) {
        show_admin_bar(false);
    }
}

add_action('init', 'wpse_77390_enable_media_categories', 1);
function wpse_77390_enable_media_categories()
{
    register_taxonomy_for_object_type('category', 'attachment');
    register_taxonomy_for_object_type('post_tag', 'attachment');
}

add_filter( 'the_content', 'filter_the_content_in_the_main_loop', 1 );
function filter_the_content_in_the_main_loop( $content ) {
 
    // Check if we're inside the main loop in a single Post.
    if ( is_singular() && in_the_loop() && is_main_query() ) {
        print_r($content);
        return $content . esc_html__( 'I’m filtering the content inside the main loop', 'wporg');
    }
 
    return $content;
}