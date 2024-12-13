<?php
/*
Plugin Name: YouTube Subscribe Link Generator
Plugin URI: https://www.linkedin.com/in/kingsley-james-hart-93679b184/?originalSubdomain=ng
Description: A plugin to generate random subscribe links from a CSV of YouTube channels, ensuring each channel is shown only once per week.
Version: 1.0
Author: James-Hart Kingsley
Author URI: https://www.linkedin.com/in/kingsley-james-hart-93679b184/?originalSubdomain=ng
*/

// Hook to add admin menu
function yt_subscribe_menu() {
    add_menu_page(
        'YouTube Subscribe Link Generator', // Page title
        'YouTube Subscribe', // Menu title
        'manage_options', // Capability
        'yt-subscribe-link-generator', // Menu slug
        'yt_subscribe_page', // Function to display settings page
        'dashicons-video-alt', // Icon
        90 // Position
    );
}
add_action('admin_menu', 'yt_subscribe_menu');

// Display settings page
function yt_subscribe_page() {
    ?>
    <div class="wrap">
        <h1>YouTube Subscribe Link Generator</h1>
        <form method="post" enctype="multipart/form-data">
            <label for="csv_upload">Upload CSV of Channels</label><br>
            <input type="file" name="csv_upload" id="csv_upload" accept=".csv" required>
            <input type="submit" name="upload_csv" value="Upload CSV" class="button-primary">
        </form>

        <?php
        if (isset($_POST['upload_csv'])) {
            if ($_FILES['csv_upload']['type'] == 'text/csv') {
                $csv_file = $_FILES['csv_upload']['tmp_name'];
                $channels = parse_csv($csv_file);
                update_option('yt_subscribe_channels', $channels);
                echo '<div class="updated"><p>CSV uploaded successfully!</p></div>';
            } else {
                echo '<div class="error"><p>Invalid file type. Please upload a CSV file.</p></div>';
            }
        }
        ?>

        <h2>Generated Link</h2>
        <p><strong>Share this link with your Hyplancers to subscribe to a random channel:</strong></p>
        <p><a href="<?php echo esc_url(get_site_url() . '/random-subscribe-link'); ?>" target="_blank">Click here to get a random subscribe link</a></p>
    </div>
    <?php
}

// Parse CSV and return an array of channels
function parse_csv($file) {
    $channels = array();
    if (($handle = fopen($file, 'r')) !== FALSE) {
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $channels[] = array(
                'name' => $data[0],
                'url' => $data[1],
            );
        }
        fclose($handle);
    }
    return $channels;
}

// Generate subscribe links for each channel
function yt_generate_subscribe_links($channels) {
    $subscribe_links = array();
    foreach ($channels as $channel) {
        $subscribe_links[] = array(
            'name' => $channel['name'],
            'subscribe_url' => $channel['url'] . '?sub_confirmation=1',
        );
    }
    return $subscribe_links;
}

// Randomly shuffle channels and mark them as viewed once per week
function yt_get_random_channel_to_subscribe() {
    $user_id = get_current_user_id();
    $viewed_channels = get_user_meta($user_id, '_yt_viewed_channels', true);
    if (!$viewed_channels) {
        $viewed_channels = array();
    }

    $channels = get_option('yt_subscribe_channels');
    $channels = yt_generate_subscribe_links($channels);

    // Filter out the channels already viewed
    $remaining_channels = array_filter($channels, function($channel) use ($viewed_channels) {
        return !in_array($channel['subscribe_url'], $viewed_channels);
    });

    if (empty($remaining_channels)) {
        return '<div style="background-color: purple; color: black; padding: 10px; text-align: center;">You have subscribed to all available channels. Please check back next week!</div>';
    }

    // Shuffle remaining channels
    shuffle($remaining_channels);
    $random_channel = array_pop($remaining_channels);

    // Mark this channel as viewed
    $viewed_channels[] = $random_channel['subscribe_url'];
    update_user_meta($user_id, '_yt_viewed_channels', $viewed_channels);

    // Reset viewed channels every week
    if (date('l') == 'Sunday') {
        update_user_meta($user_id, '_yt_viewed_channels', array());
    }

    return $random_channel['subscribe_url'];
}

// Add a rewrite rule for the random subscribe link
function yt_subscribe_rewrite_rule() {
    add_rewrite_rule('^random-subscribe-link/?$', 'index.php?yt_random_subscribe=1', 'top');
}
add_action('init', 'yt_subscribe_rewrite_rule');

// Handle the request for random subscribe link
function yt_random_subscribe_link() {
    if (get_query_var('yt_random_subscribe')) {
        $subscribe_url = yt_get_random_channel_to_subscribe();
        if (strpos($subscribe_url, 'http') === 0) {
            wp_redirect($subscribe_url);
            exit;
        } else {
            echo $subscribe_url;
        }
    }
}
add_action('template_redirect', 'yt_random_subscribe_link');

// Register query var to detect the random subscribe request
function yt_subscribe_query_vars($vars) {
    $vars[] = 'yt_random_subscribe';
    return $vars;
}
add_filter('query_vars', 'yt_subscribe_query_vars');
