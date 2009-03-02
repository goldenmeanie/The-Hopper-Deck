<?php
	class Photo extends Feathers implements Feather {
		public function __init() {
			$this->setField(array("attr" => "photo",
			                      "type" => "file",
			                      "label" => __("Photo", "photo")));
			$this->setField(array("attr" => "from_url",
			                      "type" => "text",
			                      "label" => __("From URL?", "photo"),
			                      "optional" => true,
			                      "no_value" => true));
			$this->setField(array("attr" => "caption",
			                      "type" => "text_block",
			                      "label" => __("Caption", "photo"),
			                      "optional" => true,
			                      "preview" => true,
			                      "bookmarklet" => "selection"));

			$this->setFilter("caption", "markup_post_text");
			$this->respondTo("delete_post", "delete_file");
			$this->respondTo("filter_post", "filter_post");
			$this->respondTo("new_post_options", "alt_text_field");
			$this->respondTo("edit_post_options", "alt_text_field");
			$this->respondTo("admin_write_post", "swfupload");
			$this->respondTo("admin_edit_post", "swfupload");

			if (isset($_GET['url']) and
			    preg_match("/http:\/\/(www\.)?flickr\.com\/photos\/([^\/]+)\/([0-9]+)/", $_GET['url'])) {
				$this->bookmarkletSelected();

				$page = get_remote($_GET['url']);
				preg_match("/class=\"photoImgDiv\">\n<img src=\"([^\?\"]+)/", $page, $image);

				$this->setField(array("attr" => "from_url",
				                      "type" => "text",
				                      "label" => __("From URL?", "photo"),
				                      "optional" => true,
				                      "value" => $image[1]));
			}

			if (isset($_GET['url']) and preg_match("/\.(jpg|jpeg|png|gif|bmp)$/", $_GET['url'])) {
				$this->bookmarkletSelected();

				$this->setField(array("attr" => "from_url",
				                      "type" => "text",
				                      "label" => __("From URL?", "photo"),
				                      "optional" => true,
				                      "value" => $_GET['url']));
			}
		}
		public function swfupload($admin, $post = null) {
			if (isset($post) and $post->feather != "photo" or
			    isset($_GET['feather']) and $_GET['feather'] != "photo") return;

			Trigger::current()->call("prepare_swfupload", "photo", "*.jpg;*.jpeg;*.png;*.gif;*.bmp");
		}
		public function submit() {
			if (!isset($_POST['filename'])) {
				if (isset($_FILES['photo']) and $_FILES['photo']['error'] == 0)
					$filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "bmp"));
				elseif (!empty($_POST['from_url']))
					$filename = upload_from_url($_POST['from_url'], array("jpg", "jpeg", "png", "gif", "bmp"));
				else
					error(__("Error"), __("Couldn't upload photo."));
			} else
				$filename = $_POST['filename'];

			return Post::add(array("filename" => $filename,
			                        "caption" => $_POST['caption']),
			                 $_POST['slug'],
			                 Post::check_url($_POST['slug']));
		}
		public function update() {
			$post = new Post($_POST['id']);

			if (!isset($_POST['filename']))
				if (isset($_FILES['photo']) and $_FILES['photo']['error'] == 0) {
					$this->delete_file($post);
					$filename = upload($_FILES['photo'], array("jpg", "jpeg", "png", "gif", "tiff", "bmp"));
				} elseif (!empty($_POST['from_url'])) {
					$this->delete_file($post);
					$filename = upload_from_url($_POST['from_url'], array("jpg", "jpeg", "png", "gif", "tiff", "bmp"));
				} else
					$filename = $post->filename;
			else {
				$this->delete_file($post);
				$filename = $_POST['filename'];
			}

			$post->update(array("filename" => $filename,
			                    "caption" => $_POST['caption']));
		}
		public function title($post) {
			$caption = $post->title_from_excerpt();
			return fallback($caption, $post->filename, true);
		}
		public function excerpt($post) {
			return $post->caption;
		}
		public function feed_content($post) {
			return self::image_tag_for($post, 500, 500)."<br /><br />".$post->caption;
		}
		public function delete_file($post) {
			if ($post->feather != "photo") return;
			unlink(MAIN_DIR.Config::current()->uploads_path.$post->filename);
		}
		public function filter_post($post) {
			if ($post->feather != "photo") return;
			$post->image = $this->image_tag_for($post);
		}
		public function image_tag_for($post, $max_width = 500, $max_height = null, $more_args = "quality=100") {
			$filename = $post->filename;
			$config = Config::current();
			return '<a href="'.fallback($post->source, $config->chyrp_url.$config->uploads_path.$filename, true).'"><img src="'.$config->chyrp_url.'/includes/thumb.php?file=..'.$config->uploads_path.urlencode($filename).'&amp;max_width='.$max_width.'&amp;max_height='.$max_height.'&amp;'.$more_args.'" alt="'.fallback($post->alt_text, $filename, true).'" /></a>';
		}
		public function alt_text_field($post = null) {
			if (isset($post) and $post->feather != "photo") return;
			if (!isset($_GET['feather']) and Config::current()->enabled_feathers[0] != "photo" or
			    isset($_GET['feather']) and $_GET['feather'] != "photo") return;
?>
					<p>
						<label for="option_alt_text"><?php echo __("Alt-Text", "photo"); ?></label>
						<input class="text" type="text" name="option[alt_text]" value="<?php echo fix(fallback($post->alt_text, "", true)); ?>" id="alt_text" />
					</p>
<?php
		}
	}
