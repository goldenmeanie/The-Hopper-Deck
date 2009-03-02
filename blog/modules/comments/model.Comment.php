<?php
	/**
	 * Class: Comment
	 * The model for the Comments SQL table.
	 */
	class Comment extends Model {
		public $no_results = false;

		/**
		 * Function: __construct
		 * See Also:
		 *     <Model::grab>
		 */
		public function __construct($comment_id, $options = array()) {
			parent::grab($this, $comment_id, $options);

			$this->body_unfiltered = $this->body;
			$group = ($this->user_id and !$this->user()->no_results) ? $this->user()->group() : new Group(Config::current()->guest_group) ;

			if (isset($options["filter"]) and !$options["filter"])
				return;

			if (($this->status != "pingback" and !$this->status != "trackback") and !$group->can("code_in_comments"))
				$this->body = strip_tags($this->body, "<".join("><", Config::current()->allowed_comment_html).">");

			$this->body_unfiltered = $this->body;
			Trigger::current()->filter($this->body, "markup_comment_text");

			Trigger::current()->filter($this, "filter_comment");
		}

		/**
		 * Function: find
		 * See Also:
		 *     <Model::search>
		 */
		static function find($options = array(), $options_for_object = array()) {
			return parent::search(get_class(), $options, $options_for_object);
		}

		/**
		 * Function: create
		 * Attempts to create a comment using the passed information. If a Defensio API key is present, it will check it.
		 *
		 * Parameters:
		 *     $author - The name of the commenter.
		 *     $email - The commenter's email.
		 *     $url - The commenter's website.
		 *     $body - The comment.
		 *     $post - The <Post> they're commenting on.
		 *     $type - The type of comment. Optional, used for trackbacks/pingbacks.
		 */
		static function create($author, $email, $url, $body, $post, $type = null) {
			if (!self::user_can($post->id)) return;

			$config = Config::current();
			$route = Route::current();
			$visitor = Visitor::current();

			if (!$type) {
				$status = ($post->user_id == $visitor->id) ? "approved" : $config->default_comment_status ;
				$type = "comment";
			} else
				$status = $type;

			if (!empty($config->defensio_api_key)) {
				$comment = array("owner-url" => $config->url,
				                 "user-ip" => $_SERVER['REMOTE_ADDR'],
				                 "article-date" => when("Y/m/d", $post->created_at),
				                 "comment-author" => $author,
				                 "comment-type" => $type,
				                 "comment-content" => $body,
				                 "comment-author-email" => $email,
				                 "comment-author-url" => $url,
				                 "permalink" => $post->url(),
				                 "referrer" => $_SERVER['HTTP_REFERER'],
				                 "user-logged-in" => logged_in());

				$defensio = new Defensio($config->url, $config->defensio_api_key);
				list($spam, $spaminess, $signature) = $defensio->auditComment($comment);

				if ($spam) {
					self::add($body,
					          $author,
					          $url,
					          $email,
					          $_SERVER['REMOTE_ADDR'],
					          $_SERVER['HTTP_USER_AGENT'],
					          "spam",
					          $signature,
					          null,
					          null,
					          $post,
					          $visitor->id);
					error(__("Spam Comment"), __("Your comment has been marked as spam. It will have to be approved before it will show up.", "comments"));
				} else {
					$comment = self::add($body,
					                     $author,
					                     $url,
					                     $email,
					                     $_SERVER['REMOTE_ADDR'],
					                     $_SERVER['HTTP_USER_AGENT'],
					                     $status,
					                     $signature,
					                     null,
					                     null,
					                     $post,
					                     $visitor->id);

					if (isset($_POST['ajax']))
						exit("{ comment_id: ".$comment->id." }");

					Flash::notice(__("Comment added."), $post->url()."#comment_".$comment->id);
				}
			} else {
				$comment = self::add($body,
					                 $author,
					                 $url,
					                 $email,
					                 $_SERVER['REMOTE_ADDR'],
					                 $_SERVER['HTTP_USER_AGENT'],
					                 $status,
					                 "",
					                 null,
					                 null,
					                 $post,
					                 $visitor->id);

				if (isset($_POST['ajax']))
					exit("{ comment_id: ".$comment->id." }");

				Flash::notice(__("Comment added."), $post->url()."#comment_".$comment->id);
			}
		}

		/**
		 * Function: add
		 * Adds a comment to the database.
		 *
		 * Parameters:
		 *     $body - The comment.
		 *     $author - The name of the commenter.
		 *     $url - The commenter's website.
		 *     $email - The commenter's email.
		 *     $ip - The commenter's IP address.
		 *     $agent - The commenter's user agent.
		 *     $status - The new comment's status.
		 *     $signature - Defensio's data signature of the comment, generated when it is checked
		 *                  if it's spam in <Comment.create>. Optional.
		 *     $created_at - The new comment's "created" timestamp.
		 *     $updated_at - The new comment's "last updated" timestamp.
		 *     $post - The <Post> they're commenting on.
		 *     $user_id - The ID of this <User> this comment was made by.
		 */
		static function add($body, $author, $url, $email, $ip, $agent, $status, $signature, $created_at = null, $updated_at = null, $post, $user_id) {
			if (!empty($url)) # Add the http:// if it isn't there.
				if (!@parse_url($url, PHP_URL_SCHEME))
					$url = "http://".$url;

			$ip = ip2long($ip);
			if ($ip === false)
				$ip = 0;

			$sql = SQL::current();
			$sql->insert("comments",
			             array("body" => ":body",
			                   "author" => ":author",
			                   "author_url" => ":author_url",
			                   "author_email" => ":author_email",
			                   "author_ip" => ":author_ip",
			                   "author_agent" => ":author_agent",
			                   "status" => ":status",
			                   "signature" => ":signature",
			                   "post_id" => ":post_id",
			                   "user_id" => ":user_id",
			                   "created_at" => ":created_at",
			                   "updated_at" => ":updated_at"),
			             array(":body" => $body,
			                   ":author" => strip_tags($author),
			                   ":author_url" => strip_tags($url),
			                   ":author_email" => strip_tags($email),
			                   ":author_ip" => $ip,
			                   ":author_agent" => $agent,
			                   ":status" => $status,
			                   ":signature" => $signature,
			                   ":post_id" => $post->id,
			                   ":user_id"=> $user_id,
			                   ":created_at" => fallback($created_at, datetime()),
			                   ":updated_at" => fallback($updated_at, "0000-00-00 00:00:00")
			             ));
			$new = new self($sql->latest());;

			Trigger::current()->call("add_comment", $new);
			return $new;
		}

		public function update($author, $author_email, $author_url, $body, $status, $timestamp, $update_timestamp = true) {
			$sql = SQL::current();
			$sql->update("comments",
			             "id = :id",
			             array("body" => ":body",
			                   "author" => ":author",
			                   "author_email" => ":author_email",
			                   "author_url" => ":author_url",
			                   "status" => ":status",
			                   "created_at" => ":created_at",
			                   "updated_at" => ":updated_at"),
			             array(":body" => $body,
			                   ":author" => strip_tags($author),
			                   ":author_email" => strip_tags($author_email),
			                   ":author_url" => strip_tags($author_url),
			                   ":status" => $status,
			                   ":created_at" => $timestamp,
			                   ":updated_at" => ($update_timestamp) ? datetime() : $this->updated_at,
			                   ":id" => $this->id));

			Trigger::current()->call("update_comment", $this, $author, $author_email, $author_url, $body, $status, $timestamp, $update_timestamp);
		}

		static function delete($comment_id) {
			$trigger = Trigger::current();
			if ($trigger->exists("delete_comment"))
				$trigger->call("delete_comment", new self($comment_id));

			SQL::current()->delete("comments", "id = :id", array(":id" => $comment_id));
		}

		public function editable() {
			$visitor = Visitor::current();
			return ($visitor->group()->can("edit_comment") or ($visitor->group()->can("edit_own_comment") and $visitor->id == $this->user_id));
		}

		public function deletable() {
			$visitor = Visitor::current();
			return ($visitor->group()->can("delete_comment") or ($visitor->group()->can("delete_own_comment") and $visitor->id == $this->user_id));
		}

		/**
		 * Function: any_editable
		 * Checks if the <Visitor> can edit any comments.
		 */
		static function any_editable() {
			$visitor = Visitor::current();

			# Can they edit comments?
			if ($visitor->group()->can("edit_comment"))
				return true;

			# Can they edit their own comments, and do they have any?
			if ($visitor->group()->can("edit_own_comment") and
			    self::find(array("where" => "user_id = :visitor_id", "params" => array(":visitor_id" => $visitor->id))))
				return true;

			return false;
		}

		/**
		 * Function: any_deletable
		 * Checks if the <Visitor> can delete any comments.
		 */
		static function any_deletable() {
			$visitor = Visitor::current();

			# Can they delete comments?
			if ($visitor->group()->can("delete_comment"))
				return true;

			# Can they delete their own comments, and do they have any?
			if ($visitor->group()->can("delete_own_comment") and
			    self::find(array("where" => "user_id = :visitor_id", "params" => array(":visitor_id" => $visitor->id))))
				return true;

			return false;
		}

		public function edit_link($text = null, $before = null, $after = null) {
			$visitor = Visitor::current();
			if (!$this->editable()) return;
			fallback($text, __("Edit"));
			$config = Config::current();
			echo $before.'<a href="'.$config->chyrp_url.'/admin/?action=edit_comment&amp;id='.$this->id.'" title="Edit" class="comment_edit_link edit_link" id="comment_edit_'.$this->id.'">'.$text.'</a>'.$after;
		}

		public function delete_link($text = null, $before = null, $after = null) {
			$visitor = Visitor::current();
			if (!$this->deletable()) return;
			fallback($text, __("Delete"));
			$config = Config::current();
			echo $before.'<a href="'.$config->chyrp_url.'/admin/?action=delete_comment&amp;id='.$this->id.'" title="Delete" class="comment_delete_link delete_link" id="comment_delete_'.$this->id.'">'.$text.'</a>'.$after;
		}

		public function author_link() {
			if ($this->author_url != "") # If a URL is set
				return '<a href="'.$this->author_url.'">'.$this->author.'</a>';
			else # If not, just return their name
				return $this->author;
		}

		static function user_can($post) {
			$visitor = Visitor::current();
			if (!$visitor->group()->can("add_comment")) return false;

			// assume allowed comments by default
			return empty($post->comment_status) or
			       !($post->comment_status == "closed" or
			        ($post->comment_status == "registered_only" and !logged_in()) or
			        ($post->comment_status == "private" and !$visitor->group()->can("add_comment_private")));
		}

		static function user_count($user_id) {
			$sql = SQL::current();
			$count = $sql->count("comments", "user_id = :user_id",
			                     array(":user_id" => $user_id));
			return $count;
		}

		public function post() {
			return new Post($this->post_id);
		}

		public function user() {
			if ($this->user_id)
				return new User($this->user_id);
			else
				return false;
		}
	}
