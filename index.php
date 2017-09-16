<?php

function getDB($profession){
	$GW2SKILLS_RAW_URL_PROFESSION = "http://en.gw2skills.net/ajax/editorSkillListFull/?p=";
	$db = [];
	$content = file_get_contents($GW2SKILLS_RAW_URL_PROFESSION.$profession);

	if(preg_match_all('|(\d+);[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;([^;]*);[^\n]*\n|',
	 $content, $matches)){
		foreach ($matches[1] as $key => $match) {
			$db["skills"][$match] = $matches[2][$key];
		}
	} 

	if(preg_match_all('|(\d+)\|(\d+)\|([^\|]*)\|[^\|]*\|([^\|]*)\|[^\n]*\n|', $content, $matches)){
		foreach ($matches[1] as $key => $match) {
			$db["traits"][$match] = array("branch" => $matches[2][$key], "position" => $matches[3][$key], "name" => $matches[4][$key]);
		}
	}

	return $db;
}

function findTraits($traitsId, $allTraits){
	$traits = [];

	foreach ($traitsId as $key => $trait) {
		for($i=1;$i<sizeof($trait);$i++){
			$traits [] = $allTraits[$trait[$i]];
		}
	}
	
	return $traits;
}


function getApiSkills($profession){
	$PROFESSION_URL = "https://api.guildwars2.com/v2/professions?lang=en&ids=";
	$SKILL_URL = "https://api.guildwars2.com/v2/skills?lang=en&ids=";

	$professionSkills = [];
	$json = json_decode(file_get_contents($PROFESSION_URL.ucfirst($profession)), true);

	foreach ($json[0]["skills"] as $skill) {
		$professionSkills[] = $skill["id"];
	}

	$apiSkills = [];
	$json = json_decode(file_get_contents($SKILL_URL.implode(',', $professionSkills)), true);

	foreach ($json as $key => $skill) {
		$apiSkills[$skill["name"]] = $skill["id"];
	}

	return $apiSkills;
}

function getApiTraits($profession){
	$PROFESSION_URL = "https://api.guildwars2.com/v2/professions?lang=en&ids=";
	$SKILL_URL = "https://api.guildwars2.com/v2/skills?lang=en&ids=";
}

function getSkillTemplateCode($profession){
	$PROFESSIONS_TEMPLATE = [
		4 => 8
	];
}

if(isset($_POST["gw2SkillsUrl"])){
	$PROFESSIONS = [
		1 => "elementalist",
		4 => "necromancer"
	];
	echo "<pre>";
	$url = $_POST["gw2SkillsUrl"];
	$htmlContent = file_get_contents($url);
	$skills = [];
	$traits = [];

	if(preg_match('|preload\[\'p\'\] ?= ?(\d+)|', $htmlContent, $matches)){
		$profession = $matches[1];
		$db = getDB($PROFESSIONS[$profession]);
	}

	if(preg_match_all('|preload\[\'s\'\]\[(\d)\] ?= ?([^;]*)|', $htmlContent, $matches)){
		foreach ($matches[1] as $key => $match) {
			$skills[$match]["name"] = $db["skills"][$matches[2][$key]];
		}
	}

	if(preg_match_all('|preload\[\'t\'\]\[(\d+)\] ?= ?\[([^;]*)\]|', $htmlContent, $matches)){
		foreach ($matches[1] as $key => $match) {
			$traits[$match] = explode(',',trim($matches[2][$key]));
		}
		$traits = findTraits($traits, $db["traits"]);	
	}

	
	echo "<br>Traits:<br>";
	print_r($traits);
	echo "<br>";
	echo "Skills:<br>";
	$apiSkills = getApiSkills($PROFESSIONS[$profession]);
	foreach ($skills as $key => $skill) {
		$skills[$key]["OfficialId"] = $apiSkills[$skill["name"]];
	}
	print_r($skills);

	echo "</pre>";
}

?>
<html>
<body>
	<div>
		<form method="POST">
			Insert the Gw2Skills URL:<br>
			<input type="url" name="gw2SkillsUrl"><br>
			<input type="submit" name="Submit">
		</form>
	</div>
</body>
</html>