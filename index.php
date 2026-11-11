<?php
/**
 * Render the README as the GitHub Pages homepage.
 */

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

$markdown = file_get_contents( __DIR__ . '/README.md' );
if ($markdown === false) {
	throw new RuntimeException( 'Unable to read README.md.' );
}

$converter = new League\CommonMark\GithubFlavoredMarkdownConverter();
?><!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Engage Columbus Scrape-to-RSS</title>
	<link rel="stylesheet" href="style.css">
	<link rel="alternate" type="application/rss+xml" title="Engage Columbus RSS feed" href="feed.xml">
</head>
<body>
	<header class="site-header">
		<div class="site-header__inner">
			<a class="site-name" href="./">Engage Columb<span style="color: var(--red);">us</span> RSS</a>
			<a class="feed-link" href="feed.xml">Subscribe to RSS <span aria-hidden="true">&#8599;</span></a>
		</div>
	</header>
	<main class="content">
		<?php echo $converter->convert( $markdown ); ?>
	</main>
	<footer class="site-footer">
		<div>Engage Columbus Scrape-to-RSS</div>
	</footer>
</body>
</html>