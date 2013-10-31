<?php

error_reporting(E_ALL &~ E_DEPRECATED);

function cut(array $lines, array $specs) {
	$delim = "[[:space:]]+";
	$bytes = [];
	$fields= [];
	
	foreach ($specs as $spec => $value) {
		switch ($spec) {
		case "d":
			$delim = $value;
			break;
		case "b":
			$bytes = $value;
			break;
		case "f":
			$fields = $value;
			break;
		}
	}
	
	$result = [];
	if ($bytes) {
		$func = "substr";
	} else {
		$func = function($a, $o = 0, $l = 0) {
			return join(" ", array_slice($a, $o, $l ? $l+1 : count($a)-$o));
		};
	}
	foreach ($lines as $line) {
		if ($bytes) {
			$spec = $bytes;
		} else {
			$line = split($delim, $line);
			$spec = $fields;
		}
		
		if ($spec[0] == "-") {
			$result[] = $func($line, 0, $spec[1]);
		} elseif ($spec[1] == "-") {
			if (empty($spec[2])) {
				$result[] = $func($line, $spec[0]);
			} else {
				$result[] = $func($line, $spec[0], $spec[2]-$spec[0]);
			}
		} else {
			$result[] = $line{$spec[0]};
		}
	}
	return $result;
}

function head($file, $lines = 1) {
	$ld = [];
	if (is_resource($file) || ($file = fopen($file, "r"))) {
		while ($lines--) {
			$ld[] = fgets($file);
		}
	}
	return $ld;
}

function ns($file) {
	return str_replace("/", "\\", str_replace("//", "/", trim($file, "/.")));
}

function urlpath($dir, $file) {
	return (strlen($dir) ? $dir . "/" : "") . basename($file, ".md");
}

function ls($dir) {
	$dir = rtrim(is_dir($dir) ? $dir : dirname($dir) ."/". basename($dir, ".md"), "/");
	printf("<ul>\n");
	printf("<li>&lArr; <a href=/>Home</a></li>\n");
	if ($dir !== "." && ($dn = dirname($dir)) !== ".") {
		printf("<li>&uArr; <a href=/%s>%s</a></li>\n", 
			urlpath($dir, ".."),
			ns($dn));
	}
	if (is_dir($dir)) {
		if ($dir !== ".") {
			printf("<ul>\n<li>&nbsp; %s</li>\n", ns($dir));
		}
		printf("<ul>\n");
		foreach (scandir($dir) as $file) {
			/* ignore dot-files */
			if ($file{0} === ".") {
				continue;
			}
			
			$path = "$dir/$file";
			
			if (is_file($path)) {
				$pi = pathinfo($path);
				/* ignore files not ending in .md */
				if (!isset($pi["extension"]) || $pi["extension"] != "md") {
					continue;
				}
				if (!is_dir("$dir/".$pi["filename"])) {
					continue;
				}
			} else {
				/* ignore directories where an companying file exists */
				if (is_file("$path.md")) {
					continue;
				}
			}
			
			printf("<li>&rArr; <a href=\"/%s\">%s</a></li>\n", 
				urlpath($dir, $file),
				ns("$dir/".basename($file, ".md")));
		}
		printf("</ul>\n");
		if ($dir !== ".") {
			printf("</ul>\n");
		}
	}
	
	printf("</ul>\n");
}

function ml($file) {
	$pi = pathinfo($file);
	if (!isset($pi["extension"])) {
		return;
	}
	if ($pi["extension"] !== "md") {
		return;
	}
	if (!ctype_upper($pi["filename"][0])) {
		return;
	}
	$dir = $pi["dirname"] . "/" . $pi["filename"];
	if (is_dir($dir)) {
		printf("<h2>Methods:</h2>\n");
		printf("<ul>\n");
		foreach (scandir($dir) as $file) {
			if (!is_file("$dir/$file") || ctype_upper($file{0})) {
				continue;
			}
			printf("<li><h3><a href=\"/%s\">%s</a></h3><p>%s</p><p>%s</p></li>\n",
				urlpath($dir, $file),
				basename($file, ".md"),
				@end(head("$dir/$file", 3)),
				join(" ", cut(head("$dir/$file"), ["f"=>"1-"]))
			);
		}
		printf("</ul>\n");
	}
}

function md($file) {
	$file = rtrim($file, "/");
	if (is_file($file) || is_file($file .= ".md")) {
		$pi = pathinfo($file);
		
		switch (@$pi["extension"]) {
		case "md":
			$r = fopen($file, "r");
			$md = MarkdownDocument::createFromStream($r);
			$md->compile(MarkdownDocument::AUTOLINK);
			print str_replace("<br/>","<br />",$md->getHtml());
			fclose($r);
			ml($file);
			break;
		case null:
			printf("<h1>%s</h1>", basename($file));
			printf("<pre>%s</pre>\n", htmlspecialchars(file_get_contents($file)));
			break;
		}
	} else {
		http\Env::setResponseCode(404);
		printf("<h1>Not Found</h1>\n");
		printf("<blockquote><p>Sorry, I could not find <code>%s/%s</code>.</p></blockquote>", dirname($file), basename($file, ".md"));
	}
}

function index($pn) {
	?>
	<?php
}

chdir($_SERVER["DOCUMENT_ROOT"]);
$t = ["css"=>"text/css", "js"=>"application/javascript"];
$r = new http\Env\Request;
$u = new http\Url($r->getRequestUrl());
$s = new http\Env\Response;
$p = ".". $u->path;

switch($p) {
case "./index.php":
	exit;
case "./index.js":
case "./index.css":
	$s->setHeader("Content-type", $t[pathinfo($p, PATHINFO_EXTENSION)]);
	$s->setBody(new http\Message\Body(fopen($p, "r")));
	$s->send();
	exit;
}

ob_start($s);

?>
<!doctype html>
<html>
<head>
	<meta charset="utf-8">
	<title><?=ns($p)?></title>
	<link rel="stylesheet" href="/index.css">
	<script src="//ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js"></script> 
	<?php if (!extension_loaded("discount") || !getenv("DISCOUNT")) : ?>
	<script src="/markdown.js"></script>
	<?php endif; ?>
</head>
<body>
	<div class="sidebar">
		<?php ls($p); ?>
	</div>
	<?php if ($p === "./") : ?>
		<h1>Quick Markdown Documentation Browser</h1>
		<p>v<?php readfile("VERSION")?></p>
		<pre><?php
			ob_start(function($s) {
				return htmlspecialchars($s);
			});
			readfile("LICENSE");
			ob_end_flush();
		?></pre>
	<?php else: ?>
		<?php if (!md($p)): ?>
		<?php endif; ?>
	<?php endif; ?>
	<footer>
		<a href="/VERSION">Version</a>
		<a href="/AUTHORS">Authors</a>
		<a href="/LICENSE">License</a>
	</footer>
	<script src="/index.js"></script>
</body>
</html>
<?php

ob_end_flush();
$s->send();
