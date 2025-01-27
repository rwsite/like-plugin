<?php

namespace likes;

class Settings {

	public PostLike $like_plugin;
	public function __construct() {
		$this->like_plugin = new PostLike();
	}

	public function add_actions() {
		add_filter('manage_posts_columns', [$this, 'add_likes_column']);
		add_action('manage_posts_custom_column', [$this, 'render_likes_column'], 10, 2);
		add_filter('manage_edit-post_sortable_columns', [$this, 'likes_column_sortable']);
		add_action('pre_get_posts', [$this, 'sort_likes_column']);
	}

	// Добавляем колонку "Likes" в админку постов
	public function add_likes_column($columns) {
		$columns['likes'] = __('Likes', 'likes'); // Замените 'textdomain' на ваш текстовый домен
		return $columns;
	}

	public function render_likes_column($column, $post_id) {
		if ($column === 'likes') {
			$likes = get_post_likes($post_id);
			$icon = $this->like_plugin->get_liked_icon();
			if(!empty($likes))
			echo '<span style="
			color: #607D8B;
		    background: #EEEEEE;
		    padding: 5px 10px;
		    border-radius: 4px;
		    font-weight: 600;
    		">'. $likes . ' ' . $icon . '</span>';
		}
	}

	// Возможность сортировки по колонке "Likes"
	public function likes_column_sortable($columns) {
		$columns['likes'] = 'likes';
		return $columns;
	}

	// Сортировка по колонке "Likes"
	public function sort_likes_column($query) {
		if (!is_admin() || !$query->is_main_query()) {
			return;
		}

		if ('likes' === $query->get('orderby')) {
			$query->set('meta_key', '_post_like_count'); // Замените на ваш ключ мета-данных
			$query->set('orderby', 'meta_value_num'); // Сортируем по числовому значению
		}
	}
}