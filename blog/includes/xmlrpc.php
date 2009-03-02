<?php
	define('XML_RPC', true);
	require 'common.php';
	require INCLUDES_DIR.'/lib/ixr.php';
	if (!defined('XML_RPC_FEATHER')) define('XML_RPC_FEATHER', 'text');

	#
	# Class: XMLRPC
	# Provides functionality for using external clients, services, etc. for accessing and adding to Chyrp.
	#
	class XMLRPC extends IXR_Server {

		#
		# Function: __construct
		# Registers the various XMLRPC methods.
		#
		public function __construct() {
			set_error_handler('XMLRPC::error_handler');
			set_exception_handler('XMLRPC::exception_handler');

			$methods = array(
				'pingback.ping'             => 'this:pingback_ping',
				'metaWeblog.getRecentPosts' => 'this:metaWeblog_getRecentPosts',
				'metaWeblog.getCategories'  => 'this:metaWeblog_getCategories',
				'metaWeblog.newMediaObject' => 'this:metaWeblog_newMediaObject',
				'metaWeblog.newPost'        => 'this:metaWeblog_newPost',
				'metaWeblog.getPost'        => 'this:metaWeblog_getPost',
				'metaWeblog.editPost'       => 'this:metaWeblog_editPost',
				'blogger.deletePost'        => 'this:blogger_deletePost',
				'blogger.getUsersBlogs'     => 'this:blogger_getUsersBlogs',
				'blogger.getUserInfo'       => 'this:blogger_getUserInfo',
				'mt.getRecentPostTitles'    => 'this:mt_getRecentPostTitles',
				'mt.getCategoryList'        => 'this:mt_getCategoryList',
				'mt.getPostCategories'      => 'this:mt_getPostCategories',
				'mt.setPostCategories'      => 'this:mt_setPostCategories',
				'mt.supportedTextFilters'   => 'this:mt_supportedTextFilters',
				'mt.supportedMethods'       => 'this:listMethods');

			Trigger::current()->filter($methods, "xmlrpc_methods");

			$this->IXR_Server($methods);
		}

		#
		# Function: pingback_ping
		# Receive and register pingbacks. Calls the "pingback" trigger.
		#
		public function pingback_ping($args) {
			$config = Config::current();
			$linked_from = str_replace('&amp;', '&', $args[0]);
			$linked_to   = str_replace('&amp;', '&', $args[1]);
			$post_url    = str_replace($config->url, "", $linked_to);
			$url         = extract(parse_url($linked_to));

			$cleaned_url = str_replace(array("http://www.", "http://"), "", $config->url);

			if ($linked_to == $linked_from)
				return new IXR_ERROR(0, __("The from and to URLs cannot be the same."));

			if(!strpos($linked_to, $cleaned_url))
				return new IXR_Error(0, __("There doesn't seem to be a valid link in your request."));

			if (!$config->clean_urls) {
				# Figure out what they're grabbing by
				$count = 0;
				$get = array();
				$queries = explode("&", $query);
				foreach ($queries as $query) {
					list($key, $value) = explode("=", $query);
					$get[$count]["key"] = $key;
					$get[$count]["value"] = $value;
					$count++;
				}
				$where = $get[1]["key"]." = :val";
				$params = array(":val" => $get[1]["value"]);
			} else {
				$route = Route::current();
				$where = substr($route->parse_url_to_sql($linked_to, $post_url), 0, -4);
				$params = array();
			}

			$id = SQL::current()->select("posts", "id", $where, null, $params)->fetchColumn();
			if (!Post::exists($id))
				return new IXR_Error(33, __("I can't find a post from that URL."));

			# Wait for the "from" server to publish
			sleep(1);

			extract(parse_url($linked_from), EXTR_SKIP);
			if (!isset($host)) return false;

			if (!isset($scheme) or !in_array($scheme, array("http")))
				$linked_from = "http://".$linked_from;

			# Connect
			$content = get_remote($linked_from);

			$content = str_replace("<!DOC", "<DOC", $content);
			preg_match("/<title>([^<]+)<\/title>/i", $content, $title);
			$title = $title[1];

			if (empty($title))
				return new IXR_Error(32, __("There isn't a title on that page."));

			$content = strip_tags($content, "<a>");

			preg_match("|<a[^>]+?".preg_quote($linked_to)."[^>]*>([^>]+?)</a>|", $content, $context);
			$context[1] = truncate($context[1], 100, false);
			$excerpt = str_replace($context[0], $context[1], $content);
			$excerpt = strip_tags($excerpt);
			$excerpt = preg_replace("|.*?\s(.{0,100}".preg_quote($context[1]).".{0,100})\s.*|s", '$1', $excerpt);
			$excerpt = preg_replace("/[\s\n\r\t]+/", " ", $excerpt);
			$excerpt = "[...] ".trim($excerpt)." [...]";

			$linked_to = str_replace('&', '&amp;', $linked_to);
			$trigger = Trigger::current();
			$trigger->call("pingback", $id, $linked_to, $linked_from, $title, $excerpt);

			return _f("Pingback from %s to %s registered!", array($linked_from, $linked_to));
		}

		#
		# Function: metaWeblog_getRecentPosts
		# Returns a list of the most recent posts.
		#
		public function metaWeblog_getRecentPosts($args) {
			$this->auth($args[1], $args[2]);

			$config  = Config::current();
			$trigger = Trigger::current();
			$result  = array();

			foreach ($this->getRecentPosts($args[3]) as $post) {
				$struct = array(
					'postid'            => $post->id,
					'userid'            => $post->user_id,
					'title'             => $post->title,
					'dateCreated'       => new IXR_Date(when('Ymd\TH:i:s', $post->created_at)),
					'description'       => $post->body,
					'link'              => $post->url(),
					'permaLink'         => $post->url(),
					'mt_basename'       => $post->url,
					'mt_allow_pings'    => (int) $config->enable_trackbacking);

				$result[] = $trigger->filter($struct, 'metaWeblog_getPost', $post);
			}

			return $result;
		}

		#
		# Function: metaWeblog_getCategories
		# Returns a list of all categories to which the post is assigned.
		#
		public function metaWeblog_getCategories($args) {
			$this->auth($args[1], $args[2]);

			$categories = array();
			return Trigger::current()->filter($categories, 'metaWeblog_getCategories');
		}

		#
		# Function: metaWeblog_newMediaObject
		# Uploads a file to the server.
		#
		public function metaWeblog_newMediaObject($args) {
			$this->auth($args[1], $args[2]);

			$config = Config::current();
			$file = unique_filename(trim($args[3]['name'], ' /'));
			$path = MAIN_DIR.$config->uploads_path.$file;

			if (file_put_contents($path, $args[3]['bits']) === false)
				return new IXR_Error(500, __("Failed to write file."));

			$url = $config->chyrp_url.$config->uploads_path.str_replace('+', '%20', urlencode($file));
			Trigger::current()->filter($url, 'metaWeblog_newMediaObject', $path);

			return array('url' => $url);
		}

		#
		# Function: metaWeblog_getPost
		# Retrieves a specified post.
		#
		public function metaWeblog_getPost($args) {
			$this->auth($args[1], $args[2]);

			$post = new Post($args[0], array('filter' => false));
			$struct = array(
				'postid'            => $post->id,
				'userid'            => $post->user_id,
				'title'             => $post->title,
				'dateCreated'       => new IXR_Date(when('Ymd\TH:i:s', $post->created_at)),
				'description'       => $post->body,
				'link'              => $post->url(),
				'permaLink'         => $post->url(),
				'mt_basename'       => $post->url,
				'mt_allow_pings'    => (int) Config::current()->enable_trackbacking);

			Trigger::current()->filter($struct, 'metaWeblog_getPost', $post);
			return array($struct);
		}

		#
		# Function: metaWeblog_newPost
		# Creates a new post.
		#
		public function metaWeblog_newPost($args) {
			$this->auth($args[1], $args[2], 'add');
			global $user;

			# Support for extended body
			$body = $args[3]['description'];
			if (!empty($args[3]['mt_text_more']))
				$body .= '<!--more-->'.$args[3]['mt_text_more'];

			# Add excerpt to body so it isn't lost
			if (!empty($args[3]['mt_excerpt']))
				$body = $args[3]['mt_excerpt']."\n\n".$body;

			if (trim($body) === '')
				return new IXR_Error(500, __("Body can't be blank."));

			$clean = sanitize(fallback($args[3]['mt_basename'], $args[3]['title'], true));
			$url = Post::check_url($clean);

			$_POST['user_id'] = $user->id;
			$_POST['feather'] = XML_RPC_FEATHER;
			$_POST['created_at'] = fallback($this->convertFromDateCreated($args[3]), datetime(), true);

			if ($user->group()->can('add_post'))
				$_POST['status'] = ($args[4]) ? 'public' : 'draft';
			else
				$_POST['status'] = 'draft';

			$trigger = Trigger::current();
			$trigger->call('metaWeblog_newPost_preQuery', $args[3]);

			$post = Post::add(
				array(
					'title' => $args[3]['title'],
					'body'  => $body),
				$clean,
				$url);

			if ($post->no_results)
				return new IXR_Error(500, __("Post not found."));

			$trigger->call('metaWeblog_newPost', $args[3], $post);

			# Send any and all pingbacks to URLs in the body
			if (Config::current()->send_pingbacks)
				send_pingbacks($args[3]['description'], $post);

			return $post->id;
		}

		#
		# Function: metaWeblog_editPost
		# Updates a specified post.
		#
		public function metaWeblog_editPost($args) {
			$this->auth($args[1], $args[2], 'edit');
			global $user;

			if (!Post::exists($args[0]))
				return new IXR_Error(500, __("Post not found."));

			# Support for extended body
			$body = $args[3]['description'];
			if (!empty($args[3]['mt_text_more']))
				$body .= '<!--more-->'.$args[3]['mt_text_more'];

			# Add excerpt to body so it isn't lost
			if (!empty($args[3]['mt_excerpt']))
				$body = $args[3]['mt_excerpt']."\n\n".$body;

			if (trim($body) === '')
				return new IXR_Error(500, __("Body can't be blank."));

			$post = new Post($args[0], array('filter' => false));

			# More specific permission check
			if (!$post->editable($user))
				return new IXR_Error(500, __("You don't have permission to edit this post."));

			# Enforce post status when necessary
			if (!$user->group()->can('edit_own_post', 'edit_post'))
				$status = 'draft';
			else if ($post->status !== 'public' and $post->status !== 'draft')
				$status = $post->status;
			else
				$status = ($args[4]) ? 'public' : 'draft';

			$trigger = Trigger::current();
			$trigger->call('metaWeblog_editPost_preQuery', $args[3], $post);

			$post->update(
				array('title' => $args[3]['title'], 'body' => $body ),
				null,
				null,
				$status,
				sanitize(fallback($args[3]['mt_basename'], $args[3]['title'], true)),
				fallback($this->convertFromDateCreated($args[3]), $post->created_at, true));

			$trigger->call('metaWeblog_editPost', $args[3], $post);

			return true;
		}

		#
		# Function: blogger_deletePost
		# Deletes a specified post.
		#
		public function blogger_deletePost($args) {
			$this->auth($args[2], $args[3], 'delete');
			global $user;

			$post = new Post($args[1], array('filter' => false));

			if ($post->no_results)
				return new IXR_Error(500, __("Post not found."));
			else if (!$post->deletable($user))
				return new IXR_Error(500, __("You don't have permission to delete this post."));

			Post::delete($args[1]);
			return true;
		}

		#
		# Function: blogger_getUsersBlogs
		# Returns information about the Chyrp installation.
		#
		public function blogger_getUsersBlogs($args) {
			$this->auth($args[1], $args[2]);

			$config = Config::current();
			return array(array(
				'url'      => $config->url,
				'blogName' => $config->name,
				'blogid'   => 1));
		}

		#
		# Function: blogger_getUserInfo
		# Retrieves a specified user.
		#
		public function blogger_getUserInfo($args) {
			$this->auth($args[1], $args[2]);
			global $user;

			return array(array(
				'userid'    => $user->id,
				'nickname'  => $user->fullname,
				'firstname' => '',
				'lastname'  => '',
				'email'     => $user->email,
				'url'       => $user->website));
		}

		#
		# Function: mt_getRecentPostTitles
		# Returns a bandwidth-friendly list of the most recent posts.
		#
		public function mt_getRecentPostTitles($args) {
			$this->auth($args[1], $args[2]);

			$result = array();

			foreach ($this->getRecentPosts($args[3]) as $post) {
				$result[] = array(
					'postid'      => $post->id,
					'userid'      => $post->user_id,
					'title'       => $post->title,
					'dateCreated' => new IXR_Date(when('Ymd\TH:i:s', $post->created_at)));
			}

			return $result;
		}

		#
		# Function: mt_getCategoryList
		# Returns a list of categories.
		#
		public function mt_getCategoryList($args) {
			$this->auth($args[1], $args[2]);

			$categories = array();
			return Trigger::current()->filter(
				$categories,
				'mt_getCategoryList');
		}

		#
		# Function: mt_getPostCategories
		# Returns a list of all categories to which the post is assigned.
		#
		public function mt_getPostCategories($args) {
			$this->auth($args[1], $args[2]);

			if (!Post::exists($args[0]))
				return new IXR_Error(500, __("Post not found."));

			$categories = array();
			return Trigger::current()->filter(
				$categories,
				'mt_getPostCategories',
				new Post($args[0], array('filter' => false)));
		}

		#
		# Function: mt_setPostCategories
		# Sets the categories for a post.
		#
		public function mt_setPostCategories($args) {
			$this->auth($args[1], $args[2], 'edit');
			global $user;

			$post = new Post($args[0], array('filter' => false));

			if ($post->no_results)
				return new IXR_Error(500, __("Post not found."));
			else if (!$post->deletable($user))
				return new IXR_Error(500, __("You don't have permission to edit this post."));

			Trigger::current()->call('mt_setPostCategories', $args[3], $post);
			return true;
		}

		#
		# Function: mt_supportedTextFilters
		# Returns an empty array, as this is not applicable for Chyrp.
		#
		public function mt_supportedTextFilters() {
			return array();
		}

		#
		# Function: getRecentPosts
		# Returns an array of the most recent posts.
		#
		private function getRecentPosts($limit) {
			global $user;

			if (!in_array(XML_RPC_FEATHER, Config::current()->enabled_feathers))
				throw new Exception(_f("The %s feather is not enabled.", array(XML_RPC_FEATHER)));

			$where = array('feather = :feather');
			$params = array(':feather' => XML_RPC_FEATHER);

			if ($user->group()->can('view_own_draft', 'view_draft'))
				$where[] = 'status IN ( "public", "draft" )';
			else
				$where[] = 'status = "public"';

			if (!$user->group()->can('view_draft', 'edit_draft', 'edit_post', 'delete_draft', 'delete_post')) {
				$where[] = 'user_id = :user_id';
				$params[':user_id'] = $user->id;
			}

			return Post::find(
				array(
					'where'  => $where,
					'order'  => 'created_at DESC, id DESC',
					'limit'  => $limit,
					'params' => $params),
				array('filter' => false));
		}

		#
		# Function: convertFromDateCreated
		# Converts an IXR_Date (in $args['dateCreated']) to SQL date format.
		#
		private function convertFromDateCreated($args) {
			if (array_key_exists('dateCreated', $args))
				return when('Y-m-d H:i:s', $args['dateCreated']->getIso());
			else
				return null;
		}

		#
		# Function: auth
		# Authenticates a given login and password, and checks for appropriate permission
		#
		private function auth($login, $password, $do = 'add') {
			if (!Config::current()->enable_xmlrpc)
				throw new Exception(__("XML-RPC support is disabled for this site."));

			global $user;
			$user = new User(
				null,
				array(
					'where' => array(
						'login = :login',
						'password = :password'),
					'params' => array(
						':login' => $login,
						':password' => md5($password))));

			if ($user->no_results)
				throw new Exception(__("Login incorrect."));
			else if (!$user->group()->can("{$do}_own_post", "{$do}_post", "{$do}_draft", "{$do}_own_draft"))
				throw new Exception(_f("You don't have permission to %s posts/drafts.", array($do)));
		}

		#
		# Function: error_handler
		#
		public static function error_handler($errno, $errstr, $errfile, $errline) {
			if (error_reporting() === 0 or $errno == E_STRICT) return;
			throw new Exception(sprintf("%s in %s on line %s", $errstr, $errfile, $errline));
		}

		#
		# Function: exception_handler
		#
		public static function exception_handler($exception) {
			$ixr_error = new IXR_Error(500, $exception->getMessage());
			echo $ixr_error->getXml();
		}

	}
	$server = new XMLRPC();
?>