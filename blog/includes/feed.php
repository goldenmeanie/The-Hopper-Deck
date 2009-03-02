<?php
	$title = (!empty($_GET['title'])) ? ": ".html_entity_decode(urldecode($_GET['title'])) : "" ;
	echo "<".'?xml version="1.0" encoding="utf-8"?'.">\r";
?>
<feed xmlns="http://www.w3.org/2005/Atom">
	<title><?php echo fix($config->name.$title); ?></title>
<?php if (!empty($config->description)): ?>
	<subtitle><?php echo fix($config->description); ?></subtitle>
<?php endif; ?>
	<id><?php echo fix(self_url()); ?></id>
	<updated><?php echo date("c", $latest_timestamp); ?></updated>
	<link href="<?php echo fix(self_url(), true); ?>" rel="self" type="application/atom+xml" />
	<link href="<?php echo fix($config->url, true); ?>" />
	<generator uri="http://chyrp.net/" version="<?php echo CHYRP_VERSION; ?>">Chyrp</generator>
<?php
	foreach ($posts->paginated as $post) {
		$updated = ($post->updated) ? $post->updated_at : $post->created_at ;

		$tagged = substr(strstr(url("id/".$post->id."/"), "//"), 2);
		$tagged = str_replace("#", "/", $tagged);
		$tagged = preg_replace("/(".preg_quote(parse_url($post->url(), PHP_URL_HOST)).")/", "\\1,".when("Y-m-d", $updated).":", $tagged, 1);

		$url = $post->url();
		$title = $post->title();

		if (!$post->user()->no_results)
			$author = fallback($post->user()->full_name, $post->user()->login, true);
		else
			$author = __("Guest");
?>
	<entry>
		<title type="html"><?php echo safe(fallback($title, ucfirst($post->feather), true)); ?></title>
		<id>tag:<?php echo $tagged; ?></id>
		<updated><?php echo when("c", $updated); ?></updated>
		<published><?php echo when("c", $post->created_at); ?></published>
		<link rel="alternate" type="<?php echo $theme->type; ?>" href="<?php echo $trigger->filter($url, "feed_url", $post); ?>" />
		<author>
			<name><?php echo safe($author); ?></name>
<?php if (!empty($post->user()->website)): ?>
			<uri><?php echo safe($post->user()->website); ?></uri>
<?php endif; ?>
		</author>
		<content type="html">
			<?php echo safe($post->feed_content()); ?>
		</content>
<?php $trigger->call("feed_item", $post->id); ?>
	</entry>
<?php
	}
?></feed>