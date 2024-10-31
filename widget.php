<?php

/*
 * PayPress Widget
 */

add_action('widgets_init', 'paypress_widget_load');

function paypress_widget_load() {
    wp_register_sidebar_widget('paypress_widget_1', 'PayPress Paid Widget', 'paypress_widget_display', array('description' => "PayPress Widget"));
    wp_register_sidebar_widget('paypress_widget_2', 'PayPress Stats', 'paypress_stats_widget_display', array('description' => 'PayPress Stats'));
}

/*
 *  PayPress Paid Content Widget
 */

function paypress_widget_display() {
    global $wpdb;

    if (!isset($_SESSION['paypress_paidposts'])) {
        $_SESSION['paypress_paidposts'] = array();
    }

    if (!isset($_SESSION['paypress_paidgroups'])) {
        $_SESSION['paypress_paidgroups'] = array();
    }

    $paidposts = $_SESSION['paypress_paidposts'];
    $paidgroups = $_SESSION['paypress_paidgroups'];

    if (count($paidposts) > 0) {
        echo '<h3 class="widget-title">PayPress</h3>';
        echo '<h4>U heeft betaald voor: </h4>';
        echo '<ul>';
        foreach ($paidposts as $paidpost) {
            $post = get_post($paidpost);
            echo '<li><a href="' . get_permalink($paidpost) . '">' . $post->post_title . '</a></li>';
        }
        echo '</ul>';
    } else {
        
    }

    if (count($paidgroups) > 0) {
        foreach ($paidgroups as $group) {
            $term = term_exists($group, PAYPRESS_TAXONOMY);
            $posts = get_objects_in_term($term['term_taxonomy_id'], PAYPRESS_TAXONOMY);
            if (count($posts) > 0) {
                echo '<li id="paypress-paid-widget"><h4>U heeft ook toegang tot:</h4>';
                echo '<ul>';
                foreach ($posts as $post_id) {

                    $post = get_post($post_id);
                    if ($post != null) {
                        if ($post->post_status == 'publish' && !in_array($post->ID, $paidposts) && get_post_meta($post->ID, '_paypress_paidcontent', true) == 'true') {
                            echo '<li><a href="' . get_permalink($post->ID) . '">' . $post->post_title . "</a></li>";
                        }
                    }
                }
                echo '</ul><br /></li>';
            }
        }
    }
}

/*
 * PayPress Statistics Widget
 */

function paypress_stats_widget_display() {
    global $post;
    ?>
<li id="paypress-statistieken">
<h3 class="widget-title">PayPress Statistieken</h3>
<ul>
    <li>Aantal Betalingen: <?php echo count(get_post_meta($post->ID, '_paypress_transaction_number')); ?></li>
</ul>
<br />
</li>
    <?php
}

?>
