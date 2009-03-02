<?php
	/**
	 * Class: User
	 * The User model.
	 * See Also:
	 *     <Model>
	 */
	class User extends Model {
		/**
		 * Function: __construct
		 * See Also:
		 *     <Model::grab>
		 */
		public function __construct($user_id, $options = array()) {
			parent::grab($this, $user_id, $options);

			if ($this->no_results)
				return false;

			Trigger::current()->call("filter_user", $this);
		}

		/**
		 * Function: find
		 * See Also:
		 *     <Model::search>
		 */
		static function find($options = array(), $options_for_object = array()) {
			fallback($options["order"], "id ASC");
			return parent::search(get_class(), $options, $options_for_object);
		}

		/**
		 * Function: authenticate
		 * Checks to see if a given Login and Password match a user in the database.
		 *
		 * Parameters:
		 *     $login - The Login to check.
		 *     $password - The matching Password to check.
		 *
		 * Returns:
		 *     true - if a match is found.
		 */
		static function authenticate($login, $password) {
			$check = new self(null, array("where" => array("login = :login", "password = :password"),
			                              "params" => array(":login" => $login, ":password" => $password)));
			return !$check->no_results;
		}

		/**
		 * Function: add
		 * Adds a user to the database with the passed username, password, and e-mail.
		 *
		 * Calls the add_user trigger with the inserted ID.
		 *
		 * Parameters:
		 *     $login - The Login for the new user.
		 *     $password - The Password for the new user. Don't MD5 this, it's done in the function.
		 *     $email - The E-Mail for the new user.
		 *
		 * Returns:
		 *     $id - The newly created users ID.
		 *
		 * See Also:
		 *     <update>
		 */
		static function add($login, $password, $email, $full_name = "", $website = "", $joined_at = null, $group_id = null) {
			$config = Config::current();
			$sql = SQL::current();
			$sql->insert("users",
			             array(
			                 "login" => ":login",
			                 "password" => ":password",
			                 "email" => ":email",
			                 "full_name" => ":full_name",
			                 "website" => ":website",
			                 "group_id" => ":group_id",
			                 "joined_at" => ":joined_at"),
			             array(
			                 ":login" => strip_tags($login),
			                 ":password" => md5($password),
			                 ":email" => strip_tags($email),
			                 ":full_name" => strip_tags($full_name),
			                 ":website" => strip_tags($website),
			                 ":group_id" => ($group_id) ? intval($group_id) : $config->default_group,
			                 ":joined_at" => fallback($joined_at, datetime())
			            ));

			$user = new self($sql->latest());

			Trigger::current()->call("add_user", $user);

			return $user;
		}

		/**
		 * Function: update
		 * Updates the user with the given login, password, full name, e-mail, website, and <Group> ID.
		 *
		 * Passes all of the arguments to the update_user trigger.
		 *
		 * Parameters:
		 *     $login - The new Login to set.
		 *     $password - The new Password to set.
		 *     $full_name - The new Full Name to set.
		 *     $email - The new E-Mail to set.
		 *     $website - The new Website to set.
		 *     $group_id - The new <Group> to set.
		 *
		 * See Also:
		 *     <add>
		 */
		public function update($login, $password, $email, $full_name, $website, $group_id) {
			if ($this->no_results)
				return false;

			$sql = SQL::current();
			$sql->update("users",
			             "id = :id",
			             array(
			                 "login" => ":login",
			                 "password" => ":password",
			                 "email" => ":email",
			                 "full_name" => ":full_name",
			                 "website" => ":website",
			                 "group_id" => ":group_id"),
			             array(
			                 ":login" => strip_tags($login),
			                 ":password" => $password,
			                 ":email" => strip_tags($email),
			                 ":full_name" => strip_tags($full_name),
			                 ":website" => strip_tags($website),
			                 ":group_id" => $group_id,
			                 ":id" => $this->id
			            ));

			Trigger::current()->call("update_user", $this, $login, $password, $full_name, $email, $website, $group_id);
		}

		/**
		 * Function: delete
		 * Deletes a given user. Calls the "delete_user" trigger and passes the <User> as an argument.
		 *
		 * Parameters:
		 *     $id - The user to delete.
		 */
		static function delete($id) {
			parent::destroy(get_class(), $id);
		}

		/**
		 * Function: group
		 * Returns a user's group. Example: $user->group()->can("do_something")
		 */
		public function group() {
			if ($this->no_results)
				return false;

			return new Group($this->group_id);
		}

		/**
		 * Function: posts
		 * Returns all the posts of the user.
		 */
		public function posts() {
			if ($this->no_results)
				return false;

			return Post::find(array("where" => "user_id = :user_id",
			                        "params" => array(":user_id" => $this->id)));
		}

		/**
		 * Function: pages
		 * Returns all the pages of the user.
		 */
		public function pages() {
			if ($this->no_results)
				return false;

			return Page::find(array("where" => "user_id = :user_id",
			                        "params" => array(":user_id" => $this->id)));
		}

		/**
		 * Function: edit_link
		 * Outputs an edit link for the user, if they can edit_user.
		 *
		 * Parameters:
		 *     $text - The text to show for the link.
		 *     $before - If the link can be shown, show this before it.
		 *     $after - If the link can be shown, show this after it.
		 */
		public function edit_link($text = null, $before = null, $after = null) {
			if ($this->no_results or !Visitor::current()->group()->can("edit_user"))
				return false;

			fallback($text, __("Edit"));

			echo $before.'<a href="'.Config::current()->chyrp_url.'/admin/?action=edit_user&amp;id='.$this->id.'" title="Edit" class="user_edit_link edit_link" id="user_edit_'.$this->id.'">'.$text.'</a>'.$after;
		}

		/**
		 * Function: delete_link
		 * Outputs an delete link for the user, if they can delete_user.
		 *
		 * Parameters:
		 *     $text - The text to show for the link.
		 *     $before - If the link can be shown, show this before it.
		 *     $after - If the link can be shown, show this after it.
		 */
		public function delete_link($text = null, $before = null, $after = null) {
			if ($this->no_results or !Visitor::current()->group()->can("delete_user"))
				return false;

			fallback($text, __("Delete"));

			echo $before.'<a href="'.Config::current()->chyrp_url.'/admin/?action=delete_user&amp;id='.$this->id.'" title="Delete" class="user_delete_link delete_link" id="user_delete_'.$this->id.'">'.$text.'</a>'.$after;
		}
	}
