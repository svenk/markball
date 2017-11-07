<?php

/**
 * The Markball class represents a parsed Markball document and provides an
 * API to access the elements. Available members:
 *   html: String
 *   fences: List of fences where each fence is an assoc array with 
 *           elements identifier, line_begin, line_end.
 *   Use the get_fence_content_by_...() functions to access the fences as
 *   strings.
 **/
class Markball {
	function __construct($markball_lines) {
		$this->lines = $markball_lines;
		
		// Parse document to HTML
		$html = "";
		$fencestack = array();
		$curfence = null;
		$this->fences = array();
		foreach($markball_lines as $linenum => $line) {
			if(preg_match('/^#\{(?P<tag>[^}]+)\}\s*(?P<payload>.*?)\s*$/i', $line, $res)) {
				extract($res); // $res['tag'] becomes $tag
				switch(strtolower($tag)) {
					case "head":	$html .= "<h1>$payload</h1>"; break;
					case "cmd":     $html .= "<pre class='cmd'>$payload</pre>"; break;
					case "begin":
						$html .= "<pre class='fence' title='$payload'>";
						$fencestack[] = $payload;
						$curfence = array('identifier'=>$payload, 'line_begin'=>$linenum);
						break;
					case "end":
						$cur_fence = array_pop($fencestack);
						// just to be safe...
						if($payload != $cur_fence) {
							$html .= "Debugging Error: Fence '$payload' ends but I expected '$cur_fence' to end";
						}
						$curfence['line_end'] = $linenum;
						$this->fences[] = $curfence;
						// end anyway:
						$html .= "</pre><!-- fence $payload -->";
						break;
					case "item":
						// kind of stylish:
						$html .= "<p class='item'>$payload</p>";
						// TODO: Proper <ul> and <li> handling
						break;
					default:
						# whatever
						$html .= "<pre>$payload</pre>";
				}
			} else $html .= $line;
		}
		$this->html = $html;
	}

	function get_fence_content_by_name($identifier) {
		foreach($this->fences as $fid => $fence) {
			if($identifier == $fence['identifier'])
				return $this->get_fence_content_by_id($fid);
		}
	}

	function get_fence_content_by_id($id) {
		extract($this->fences[$id]); // gives $line_begin, $line_end
		$tag_line = 1; // the lines including the #BEGIN and #END tag
		$fence_lines = array_slice($this->lines, $tag_line + $line_begin, $line_end - $line_begin - $tag_line);
		return implode($fence_lines);
	}
}

/**
 * A simple PHP web frontend for the Markball file
 **/
class MarkballViewer {
	function __construct() {
		$this->target_fieldname = 'target';
		$this->fence_fieldname = 'fence';
		$this->fence_mode_fieldname = 'mode';

		$this->target = '';
		if(!empty($_SERVER['PATH_INFO'])) $this->target = substr($_SERVER['PATH_INFO'],1); # remove "/"
		if(isset($_GET[$this->target_fieldname])) $this->target = $_GET[$this->target_fieldname];

		$this->fence = null;
		if(isset($_GET[$this->fence_fieldname])) $this->fence = $_GET[$this->fence_fieldname];

		$this->fence_mode = null;
		if(isset($_GET[$this->fence_mode_fieldname])) $this->fence_mode = $_GET[$this->fence_mode_fieldname];
	}


	function run() {
		if(empty($this->target)) {
			# display only the form
			$this->print_form();
			exit;
		}

		$this->downloadFile($this->target);

		if($this->fence !== null) {
			# display a single fence
			$this->displayFence($this->fence, $this->fence_mode);
		} else {
			# display a whole file
			$this->displayFile();
		}
	}

	function print_html_intro() {
		print '<html><meta charset="utf-8">';
		if(isset($this->css)) print $this->css;
	}

	function print_form() {
		$this->print_html_intro();
		?>
		<h1>PHP Markball parser service</h1>

		This is a parser service for the <a href="https://github.com/svenk/markball">markball</a>
		file format, as used in ExaHyPE. Just paste your URL here:

		<form method="get">
		<input type="url" name="<?php print $this->target_fieldname; ?>">
		<input type="submit" name="display">
		</form>
		<hr>
		<a href='?viewsource'>View my source</a>
		<?php
	} // print_form

	function print_header($aux_stuff="") {
		$this->print_html_intro();
		print "<strong><a href='${_SERVER[SCRIPT_NAME]}'>Markball2HTML service</a></strong>";
		print ", rendering <a href='$this->target'>$this->target</a> ";

		$has_fences = !empty($this->markball->fences);
		$this->num_fences = count($this->markball->fences);
		if($has_fences) print "containing <a href='#fences'>$this->num_fences files</a>";

		print $aux_stuff;
		print "<hr>";
	}

	function err($str) {
		$this->print_form();
		print "<hr><strong>Error:</strong> $str";
		exit;
	}

	function downloadFile($target) {
		// fix path info problems
		$target = preg_replace('#^http:/([^/])#i', 'http://\1', $target);

		// do not display local files
		if(!preg_match('#^http://#i', $target))
			$this->err("Only HTTP downloads accepted, <em>$target</em> is bad");

		// download file from internet
		$content = file($target);
		if(!$content)
			$this->err("Could not download <em>$target</em>; probably broken link?");

		$this->markball = new Markball($content);
		return $target;
	}

	function displayFile() {
		$this->print_header();
		print $this->markball->html;
		$this->listFences();
	}

	function listFences() {
		$baseLink = "${_SERVER[SCRIPT_NAME]}?".http_build_query(array($this->target_fieldname => $this->target));
		print "<hr><h3 id='fences'><a name='fences'>$this->num_fences embedded files</a> in the <a hreF='$baseLink'>current document</a></h3>";
		print "<ul>";
		foreach($this->markball->fences as $id => $fence) {
			$max_chars = 60;
			$short_identifier = strlen($fence['identifier']) > $max_chars ? "...".substr($fence['identifier'],-1*$max_chars) : $fence['identifier'];

			print "<li><a href='".$this->linkForFence($id,'view')."' class='ellipsis'>$short_identifier</a>";
			print " [";
			print implode(", ",array_map(function($mode){
				return "<a href='".$this->linkForFence($id, $mode)."'>$mode</a>"; },
				array("raw","download")));
			print "]";
		}
		print "</ul>";
	}

	function linkForFence($id, $mode='view') {
		$query = array(
			$this->target_fieldname => $this->target,
			$this->fence_fieldname  => $id,
			$this->fence_mode_fieldname => $mode
		);
		return "${_SERVER[SCRIPT_NAME]}?".http_build_query($query);
	}

	function displayFence($id, $mode) {
		$fence_content = $this->markball->get_fence_content_by_id($id);
		$file_name = $this->markball->fences[$id]['identifier'];
		$file_name .= ".txt"; # for the time being
		$file_size = strlen($fence_content);
		$lines = substr_count($fence_content, "\n");

		if($mode=='download') {
			header('Content-Description: File Transfer');
			header('Content-Disposition: attachment; filename="'.$file_name.'"');
			header('Content-Transfer-Encoding: ascii');
			#header('Expires: 0');
			#header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			#header('Pragma: public');
			header('Content-Length: ' . $file_size); //Absolute URL
		}

		if($mode=='download' || $mode=='raw') {
			header('Content-Type: text/plain');
		}

		if($mode=='view') {
			$this->print_header();
			?>
			<h2>File display: <?php print $file_name; ?></h2>
			<p>Size: <?php print $file_size; ?> bytes, <?php print $lines; ?> lines.
			<?php
			foreach(array("raw", "download") as $mmode) 
				print "<a href='".$this->linkForFence($id, $mmode)."'>$mmode</a> ";
			?>
			<pre class='fence'>
			<?php
		}

		print $fence_content;

		if($mode=='view') {
			print '</pre>';
			$this->listFences();
		}
	}
} // class MarkballViewer



# markball parser service, see https://github.com/svenk/markball
# for file format. -- SvenK, 2017-11-06

if(isset($_GET['viewsource'])) {
	print '<h1>The Whoopsie viewer ('.basename(__FILE__).')</h1>';
	print 'Written by SvenK at 2011-11-06<hr>';
	show_source(__FILE__); exit;
}

$viewer = new MarkballViewer();
$viewer->css = <<<CSS
<style type="text/css">
body { font-family: Arial,sans-serif; }
form { margin: 1em 0; }
pre {
	border-left: .5em solid gray;
	padding: .5em;
}
.cmd {
	background-color: #FFE0B2;
	border-left-color: #E65100;
}
.fence {
	border-left-color: #01579B;
	background-color: #B3E5FC;
}
</style>
CSS;
$viewer->run();

