<?php

	class Validator {

		// check on a valid integer range
		public static function range($value, $definition){
			if($value >= $definition['min'] and $value <= $definition['max']){
				return $value;
			}
			else {
				return $definition['value'];
			}
		}

		// check on a valid datetime
		public static function datetime($value, $definition){
			try {
				new DateTime($value);
				return $value;
			}
			catch(Exception $e){
				return $definition['value'];
			}
		}

		// validates an integer
		public static function atleast($value, $definition){
			return max($value, $definition['min']);
		}

	}

	class Gitsee {

		// merge e.g. _COOKIE and _POST against default settings
		protected function mergeSettings($settings){
			$merged = array_shift($settings);
			foreach($settings as $setting){
				foreach($setting as $key => $value){
					if(isset($merged[$key], $merged[$key]['validates'])){
						$merged[$key]['value'] = Validator::{$merged[$key]['validates']}($value, $merged[$key]);
					}
				}
			}
			return $merged;
		}

		// set skip by actions
		protected function processSkip($setting, $settings){
			foreach($settings as $comparesetting){
				if(!empty($comparesetting['prev'])){
					$setting['skip']['value'] = Validator::atleast($setting['skip']['value'] - $setting['number']['value'], $setting['skip']);
				}
				if(!empty($comparesetting['next'])){
					$setting['skip']['value'] += $setting['number']['value'];
				}
			}
			return $setting;
		}

		// set config and valid settings
		public function __construct($config, $settings){
			$this->config = $config;
			$this->settings = $settings;
			$this->setting = $this->mergeSettings($settings); // validate incoming values
			$this->setting = $this->processSkip($this->setting, $settings); // react on actions that sould not be saved
		}

		// get absolute repo path
		public function getPath(){
			return realpath($this->config['path']);
		}

		// combine raw command with settings
		public function getCmd(){
			$command = $this->config['cmd'];
			$setting = $this->setting;
			if(!empty($setting['number']['value'])){
				$command .= ' -n '.$setting['number']['value'];
			}
			if(!empty($setting['since']['value'])){
				$command .= ' --since="'.$setting['since']['value'].'"';
			}
			if(!empty($setting['skip']['value'])){
				$command .= ' --skip='.$setting['skip']['value'];
			}
			$this->setting['command'] = $command;
			return $command;
		}

		// parse a git-log into a well formed array
		public function getCommits(){
			$log = [];
			$commits = [];
			$stats = [];
			$keys = explode($this->config['delimiter'], $this->config['fields']); // get valid output fields
			chdir($this->config['path']); // change to repo
			$cmd = $this->getCmd();
			exec($cmd, $log); // get the log
			$logs = array_chunk($log, 3); // chunk to a set of single messages
			foreach($logs as $i => $message){
				$commits[$i] = array_combine($keys, explode($this->config['delimiter'], $message[0])); // merge into output fields
				$commits[$i]['subject'] = preg_replace($this->config['link_replacement'][0], $this->config['link_replacement'][1],$commits[$i]['subject']);
				$commits[$i]['stats_message'] = $message[2];
				preg_match_all($this->config['stats_regex'], $commits[$i]['stats_message'], $allstats); // grab stats by regex
				$stats = array_intersect_key($allstats, array_flip($this->config['stats_fields']));
				$commits[$i]['stats'] = array_map(function($stat){ return (int)$stat[0]; }, $stats); // convert to integer

			}
			return $commits;
		}

	}

	$config = [
		'path'  => '.', // where the repo is located
		'delimiter' => ';', // parse log- and output fields
		'cmd' => 'git log --format="%h;%cn;%ce;%cr;%ci;%s;%b" --relative-date --abbrev-commit --shortstat', // command to run incl. format
		'fields' => 'hash;name;email;dater;date;subject;body', // fields from the command
		'stats_regex' => '`((?P<changed>\d+) file(s)? changed){1}(, (?P<insertions>\d+) insertion(s)?...){0,}(, (?P<deletions>\d+) deletion(s)?...){0,}`', // parse stats
		'stats_fields' => ['changed', 'insertions', 'deletions'], // stats fields
		'link_replacement' => ['`(#(\d+))`', '<a href="$2">$1</a>'], // generate links e.g. by ticket-ids
	];

	// define settings that may be validated and overwritte by users choice
	$defaultSettings = [
		'git' => [
			'number' => [ // count of commits that will be showen
				'type' => 'range',
				'min' => 10,
				'max' => 100,
				'step' => 10,
				'value' => 10,
				'validates' => 'range',
			],
			'since' => [ // starting date since when commits are showen
				'type' => 'select',
				'values' => [
					'' => 'init',
					'1 day ago' => '1 day ago',
					'1 week ago' => '1 week ago',
					'1 month ago' => '1 month ago',
				],
				'value' => '',
				'validates' => 'datetime',
			],
			'skip' => [ // skipped items for pagination
				'value' => 0,
				'min' => 0,
				'validates' => 'atleast',
			],
		]
	];
	$cookiegit = !empty($_COOKIE['git']) ? $_COOKIE['git'] : [];
	$postgit = !empty($_POST['git']) ? $_POST['git'] : [];
	
	$Git = new Gitsee($config, [$defaultSettings['git'], $cookiegit, $postgit]);
	$Git->commits = $Git->getCommits();


?>
<!DOCTYPE html>
<html>
	<head>
		<link href="https://fonts.googleapis.com/css?family=Hind|Inconsolata" rel="stylesheet">
		<style rel="stylesheet">
			* { font-family: 'Hind', sans-serif; }
			body { font-size: 90%; width:85%; max-width:800px; margin:25px auto; }
			h1 { padding:5px 0; font-size:130%; }
			h1 span { padding:3px 7px; border-radius:5px; font-family: 'Inconsolata', monospace; font-size:90%; color:#c10d3b; background-color:#f9f2f4; }table.commits { width:100%; margin:35px 0; }
			form.selector { margin-top:25px; text-align:center; font-size:110%; }
			form.selector, form.selector * { vertical-align:middle; }
			form.selector .numbercommits {display:inline-block; width:130px; text-align:right;}
			form.selector input, form.selector select, form.selector .input { margin-right:10px; }
			form.selector select { padding:1px 5px 0px; border:none; border-radius:15px; font-size:80%; font-weight:600; text-transform:uppercase; color:#5f3c5f; border:2px solid #5f3c5f; background-color:transparent; outline: none; }
			form.selector input[type="range"]{ display:inline; width:15%; }
			form.selector input[type=range] { -webkit-appearance: none; height:3px; border-radius:3px; background-color: #5f3c5f; }
			form.selector input[type=range]:focus { outline: none; }
			form.selector input[type=range]::-webkit-slider-thumb { -webkit-appearance: none; width: 18px; height: 18px; border:2px solid #5f3c5f; border-radius:9px; background-color: #fff; }
			form.selector input[type=submit] { border:none; border-radius:15px; font-size:80%; font-weight:600; text-transform:uppercase; color:#5f3c5f; border:2px solid #5f3c5f; background-color:transparent; outline: none; }
			form.selector input.prev[type=submit] { padding:3px 7px 0px 15px; background-position:left 5px center; background-repeat: no-repeat; background-size: 8px 8px; background-image: url("data:image/svg+xml;utf8,<svg version='1.1' xmlns='http://www.w3.org/2000/svg' width='8' height='16'><polygon points='1,8 8,1 8,16' style='fill:#5f3c5f' /></svg>"); }
			form.selector input.next[type=submit] { padding:3px 15px 0px 7px; background-position:right 5px center; background-repeat: no-repeat; background-size: 8px 8px; background-image: url("data:image/svg+xml;utf8,<svg version='1.1' xmlns='http://www.w3.org/2000/svg' width='8' height='16'><polygon points='1,1 1,16 8,8' style='fill:#5f3c5f' /></svg>"); }
			code { display:block; margin-top:25px; font-family: 'Inconsolata', monospace; font-size:85%; color:#777; text-align:center; }
			table.commits tr th, table.commits tr td { padding:5px 10px; font-weight:normal; text-align:left; }
			table.commits tr th { font-style:italic; }
			table.commits tr td { border-bottom:1px solid #ccc; background-color:#fafafa; }
			table.commits tr td:first-child { border-color:#5f3c5f !important; background-color:#5f3c5f !important; color:#fff !important; }
			table.commits tr:first-child td:first-child { border-top-left-radius: 10px; }
			table.commits tr:first-child td:last-child { border-top-right-radius: 10px; }
			table.commits tr:last-child td:first-child { border-bottom-left-radius: 10px; }
			table.commits tr:last-child td:last-child { border-bottom-right-radius: 10px; }
			table.commits tr td.hash { font-family: 'Inconsolata', monospace; color:#c7254e; text-align:center; }
			table.commits tr td.subject { width:50%; font-weight:600; color:#696969; }
			table.commits tr td.subject a { color:#3a98e0; }
			table.commits tr td.time, table.commits tr td.author, table.commits tr td.stats { color:#adadad; }
			table.commits tr abbr { border-bottom:1px dotted #eee; border-bottom: 1px dotted; }
			table.commits tr .changed { color:#ffc723; }
			table.commits tr .insertions { color:#2bcc0f; }
			table.commits tr .deletions { color:#d21648; }
			p.nocommits { margin-top:25px; text-align:center; text-transform:uppercase; color:#696969; }
		</style>
	</head>
	<body>
		<div class="container">

			<h1>gitsee on repo <span><?=$Git->getPath()?></span></h1>

			<form method="post" class="selector">
				<div class="choice">
					<span class="numbercommits">show <output id="numberout"><?=(int)$Git->setting['number']['value']?></output> commits</span> <input type="range" id="number" name="git[number]" min="<?=(int)$Git->setting['number']['min']?>" max="<?=(int)$Git->setting['number']['max']?>" step="<?=(int)$Git->setting['number']['step']?>" value="<?=(int)$Git->setting['number']['value']?>" oninput="numberout.value = number.value" />
					since <select name="git[since]">
						<?php foreach($Git->setting['since']['values'] as $key => $value): ?>
							<option value="<?=$key?>" <?php if($value == $Git->setting['since']['value']): ?>selected="selected"<?php endif; ?>><?=$value?></option>
						<?php endforeach; ?>
					</select>
					<span class="input">start @ <?=(int)$Git->setting['skip']['value']?> <input type="hidden" name="git[skip]" value="<?=(int)$Git->setting['skip']['value']?>" /></span>
					<input type="submit" name="git[prev]" value="prev" class="prev" />
					<input type="submit" name="git[next]" value="next" class="next" />
				</div>
			</form>

			<code>&gt; <?=$Git->getCmd()?></code>

			<?php if(count($Git->commits)): ?>
				<table class="commits">
					<thead>
						<tr>
							<th class="hash">
							hash
							</th>
							<th class="subject">
							subject
							</th>
							<th class="time">
							time
							</th>
							<th class="author">
							author
							</th>
							<th class="stats">
							stats
							</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($Git->commits as $commit): ?>
						<tr>
							<td class="hash"><?=$commit['hash']?></td>
							<td class="subject"><?=$commit['subject']?></td>
							<td class="time"><abbr title="<?=$commit['date']?>"><?=$commit['dater']?></abbr></td>
							<td class="author">by <abbr title="<?=$commit['email']?>"><?=$commit['name']?></abbr></td>
							<td class="stats">
								<abbr title="<?=$commit['stats_message']?>">
									<span class="changed"><?=$commit['stats']['changed']?></span>
									/
									<span class="insertions"><?=$commit['stats']['insertions']?></span>
									/
									<span class="deletions"><?=$commit['stats']['deletions']?></span>
								</abbr>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else: ?>
				<p class="nocommits">no commits</p>
			<?php endif; ?>

		</div>

		<script src="https://code.jquery.com/jquery-3.1.1.min.js"></script>
		<script>
			$('input[type="range"],select').on('change', function () {
				$(this).closest('form').submit();
			});
		</script>

	</body>
</html>