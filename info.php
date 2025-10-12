<?php
$theme = Array();

// Theme name
$theme['name'] = 'board thread RSS(dir:' . basename(dirname(__FILE__)) . ')';
// Description (you can use Tinyboard markup here)
$theme['description'] = 'RSS for each board and each thread.';
$theme['version'] = 'v0.5';

// Theme configuration	
$theme['config'] = Array();

$theme['config'][] = Array(
  'title' => 'Included boards',
  'name' => 'include',
  'type' => 'text',
  'default' => '',
  'comment' => '(space seperated. all if empty)'
);

$theme['config'][] = Array(
  'title' => 'Excluded boards',
  'name' => 'exclude',
  'type' => 'text',
  'comment' => '(space seperated)'
);

$theme['config'][] = Array(
    'name' => 'posts_limit',
    'type' => 'text',
    'default' => '100',
    'title' => 'number of recent posts',
    'comment' => '(maximum posts to feed)'
);

$theme['config'][] = Array(
    'name' => 'images_limit',
    'type' => 'text',
    'default' => '3',
    'title' => 'number of images',
    'comment' => '(maximum images per post)'
);

$theme['config'][] = Array(
    'name' => 'themedir',
    'type' => 'text',
    'default' => dirname(__FILE__),
    'title' => 'This theme dir',
    'comment' => '(don\'t change)'
);

$theme['config'][] = Array(
    'name' => 'thread_rss_suffix',
    'type' => 'text',
    'default' => '_rss20.xml',
    'title' => 'feed file suffix',
    'comment' => '(e.g. https://example.net/sub/vichan/b/res/{thread_id}{suffix} )'
);

$theme['config'][] = Array(
  'name' => 'thread_rss_template',
  'type' => 'text',
  'default' => 'thread.xml',
  'title' => 'thread rss template',
  'comment' => '(input. relative path from this theme directory.)'
);

$theme['config'][] = Array(
  'name' => 'snippet_length',
  'type' => 'text',
  'default' => '30',
  'title' => 'snippet',
  'comment' => '(length after shortening the body text of post)'
);

$theme['config'][] = Array(
    'name' => 'base_url',
    'type' => 'text',
    'default' => '-1',
    'title' => 'Base URL',
    'comment' => '(if -1, use $config[\'base_url\'] or $_SERVER[*] . eg. "https://test.com")'
);

// Unique function name for building everything
$theme['build_function'] = 'BoardThreadRSS::build';
$theme['install_callback'] = 'board_thread_rss_install';

if (!function_exists('board_thread_rss_install')) {
    function board_thread_rss_install($settings) {
        $pos_int = (static function ($name) use($settings) {
            if (!is_numeric($settings[$name]) || $settings[$name] < 0) {
                return Array(false, '<strong>' . utf8tohtml($settings[$name]) . '</strong>('.$name.') is not a non-negative integer.');
            };
            return false;
        });
        if ($r = $pos_int('posts_limit')) {return $r;};
        if ($r = $pos_int('snippet_length')) {return $r;};

        if ($settings['themedir'] != dirname(__FILE__)) {
            return Array(false, '<strong>' . utf8tohtml($settings['themedir']) . '</strong> is not a theme directory.');
        };
    };
};
