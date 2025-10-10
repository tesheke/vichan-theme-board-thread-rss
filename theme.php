<?php
require 'info.php';

if (!class_exists('BoardThreadRSS')) {
    // Wrap functions in a class so they don't interfere with normal Tinyboard operations
    class BoardThreadRSS {

        // this is used to reference the theme settings from event handlers.
        // it's dirty but there is no other way.
        static $settings_static;

		public static function build($action, $settings, $board) {
			// Possible values for $action:
			//	- all (rebuild everything, initialization)
			//	- news (news has been updated)
			//	- boards (board list changed)
			//	- post (a post has been made)
			//	- post-thread (a thread has been made)
            //  - post-delete

            // - 板RSSが未実装.
            // - スレッドが落ちた時にrss xmlを削除する処理が未実装.

            self::$settings_static = $settings;

			try {
                if ($action == 'all') {
                    $b = new self($settings);
                    $b->build_all();
                } else {
                    // do nothing.
                };
			}
			catch (Exception $e) {
				error_log("board thread RSS:build: " . $e->getMessage());
			};
		}

        public static function event_handler($thread_id) {
            global $board, $config;
			try {
                $b = new self(self::$settings_static);
                $b->build_thread_rss($config, $board, $thread_id);
			}
			catch (Exception $e) {
				error_log("board thread RSS:event_handler: " . $e->getMessage());
			};
        }

        public function __construct($settings) {
            $this->excluded = self::explode_noempty($settings['exclude'] or '');
            $this->included = self::explode_noempty($settings['include'] or '');
            $this->settings = $settings;
        }

        function is_target_board($b_uri) {
            //     0: 全てのboardsが対象.
            // non-0: 指定されたboardsだけが対象.
            if (0 !== count($this->included)) {
                if (!in_array($b_uri, $this->included)) {
                    return false;
                };
            };
            if (in_array($b_uri, $this->excluded)) {
                return false;
            };
            return true;
        }

        public function build_all() {
			global $config;

            foreach (listBoards() as $board) {
				$b_uri = $board['uri'];
                if (!$this->is_target_board($b_uri)) {
                    continue;
                };
                foreach ($this->list_threads($b_uri) as $thread_id) {
                    $this->build_thread_rss($config, $board, $thread_id);
                };
            };
        }

		static public function get_theme_path($config, $settings) {
			// compute relative path from vichan-root/templates/.

			$path = $settings['themedir'];
			$pos = strrpos($path, '/templates/themes/');
			if ($pos === false) {
                // 不明な状態. 回避策.
				return 'templates/themes/board_thread_rss/';
			};

			$p = substr($path, $pos + strlen('/templates/'));
			return $p;
		}

        public function list_threads($board_uri) {
            // $board_uri: 'b', 'jp', 'pol', etc...
            $query = sprintf("SELECT id from ``posts_%s`` WHERE thread is NULL",
                             $board_uri, $board_uri);
            if (!($query = query($query))) {
                error_log('board_thread_rss::list_threads ' . db_error());
            };
            $result = [];
            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                $result[] = $post['id'];
            };
            return $result;
        }

        static function explode_noempty($str) {
            return array_filter(explode(' ', $str), function($x) {
                return $x !== '';
            });
        }

        static function join_path() {
            $paths = array();

            foreach (func_get_args() as $arg) {
                if ($arg !== '') { $paths[] = $arg; }
            };

            return preg_replace('#/+#','/',join('/', $paths));
        }


        public function build_thread_rss($config, $board, $thread_id) {
            // $board['uri']: 'b', 'jp', 'pol', etc...
            $query = 'SELECT * FROM ``posts_%s``'
                .' WHERE `thread` IS NULL AND `id` = :thread_id'
                .' LIMIT 1';
            $query = sprintf($query, $board['uri']);
            $query = prepare($query) or error(db_error());
            $query->bindValue(':thread_id', $thread_id, PDO::PARAM_INT);
            $query->execute();

            $original_post = $query->fetch(PDO::FETCH_ASSOC);
            if (!$original_post) {
                error_log('board_thread_rss::build_thread_rss '
                          . $config['error']['nonexistant']
                          . ' thread id['.$thread_id.']'
                          . ' boarduri['.$board['uri'].']');
                return;
            };

            $query = 'SELECT * FROM ``posts_%s``'
                .' WHERE `thread` = :thread_id'
                .' ORDER BY `id` DESC'
                .' LIMIT :limit';
            $query = sprintf($query, $board['uri']);
            $query = prepare($query) or error(db_error());
            $query->bindValue(':thread_id', $thread_id, PDO::PARAM_INT);
            $query->bindValue(':limit', $this->settings['posts_limit'], PDO::PARAM_INT);
            $query->execute();

            $post_list = [];
            while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
                $this->preprocess_post($post, $board, $config);
                $post_list[] = $post;
            };
            $this->preprocess_post($original_post, $board, $config);

            $datetime_now = new DateTime('@'.time());
            $template_path = self::join_path(
                self::get_theme_path($config, $this->settings),
                $this->settings['thread_rss_template']
            );
            $output = Element($template_path, Array(
				'settings' => $this->settings,
				'config' => $config,
                'board' => $board,
				'post_list' => $post_list,
                'op' => $original_post,
                'theme_name' => 'BoardThreadRSS',
                'now_rfc822' => $datetime_now->format(DateTime::RFC822)
			));

            $rss_path = self::join_path(
                $board['uri'],
                $config['dir']['res'],
                $thread_id . $this->settings['thread_rss_suffix']
            );
            file_write($rss_path, $output);
        }

        function get_base_url($config) {
            if ($this->settings['base_url'] == '-1') {
                return $config['base_url'];
            };
            return $this->settings['base_url'];
        }
        
        function preprocess_post(&$post, $board, $config) {
            // default:
            //   $config['file_page'] = '%d.html';
            //   $config['dir']['res'] = 'res/';
            $base_url = $this->get_base_url($config);
            $file_page = sprintf($config['file_page'],
                                 ($post['thread'] ? $post['thread'] : $post['id']));
            $post['board_uri'] = $board['uri'];
            $post['link'] = self::join_path(
                $base_url,
                $config['root'],
                $board['uri'],
                $config['dir']['res'],
                $file_page) . '#' . $post['id'];
            $post['board_title'] = $board['title'];

            $post['snippet'] = pm_snippet($post['body'], $this->settings['snippet_length']);
            $datetime = new DateTime('@'.$post['time']);
            $post['time_rfc822'] = $datetime->format( DateTime::RFC822 );

            // - prepare 'computed_thumb'.
            // - By calculates 'computed_thumb' in advance, you can avoid
            //   writing complex code in the template.
            $prefix = self::join_path($base_url, $config['root']);
            $post['files'] = json_decode($post['files']);
            if (!$post['files']) {
                $post['files'] = [];
            };
            $post['files'] = array_slice($post['files'],
                                         0, $this->settings['images_limit']);
            foreach ($post['files'] as $file) {
                if ($file->file == 'deleted') {
                    $file->computed_thumb = $config['image_deleted'];
                    continue;
                };
                if ($file->thumb == 'spoiler') {
                    $file->computed_thumb = $config['spoiler_image'];
                    continue;
                };
                // default: $config['file_thumb'] = 'static/%s';
                if ($file->thumb == 'file') {
                    if (array_key_exists($file->extension, $config['file_icons'])) {
                        $file_icon = $config['file_icons'][$file->extension];
                        $file->computed_thumb = sprintf($config['file_thumb'], $file_icon);
                        continue;
                    };
                    
                    $file->computed_thumb =
                        sprintf($config['file_thumb'], $config['file_icons']['default']);
                    continue;
                };
                $file->computed_thumb = $file->thumb;
            };
            foreach ($post['files'] as $file) {
                $file->file_link = self::join_path($prefix, $file->file_path);
                $file->computed_thumb_link =
                    self::join_path($prefix, $file->computed_thumb);
            };
        }
    };

    event_handler('build-thread',
                  function($thread_id) {
                      return BoardThreadRSS::event_handler($thread_id);
                  });
};
