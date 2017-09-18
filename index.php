<?php

$errors = [];

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

	if(preg_match_all('|(\d+)\|(\d+)\|([XM]\d)\|[^\|]*\|([^\|]*)\|[^\n]*\n|', $content, $matches)){
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
	$TRAIT_URL = "https://api.guildwars2.com/v2/traits?lang=en&ids=";

	$professionTraits = [];
	$apiTraits = [];

	$json = json_decode(file_get_contents($PROFESSION_URL.ucfirst($profession)), true);
	$traitsIdsToSearch = [];
	foreach ($json[0]["training"] as $key => $training) {
		if(in_array($training["category"], ["Specializations", "EliteSpecializations"])){
			$traitLinesName[$training["id"]]["name"] = $training["name"];
			foreach ($training["track"] as $keyTrait => $value) {
				if($value["type"] === "Trait"){
					$traitsIdsToSearch[$value["trait_id"]] = $training["name"];
				}
			}
		}
	}

	$json = json_decode(file_get_contents($TRAIT_URL.implode(',', array_keys($traitsIdsToSearch))), true);
	foreach ($json as $key => $trait) {
		$apiTraits["line"][$traitsIdsToSearch[$trait["id"]]]["id"] = $trait["specialization"];
		$apiTraits["line"][$traitsIdsToSearch[$trait["id"]]]["traits"][$trait["id"]] = $trait["name"];
	}

	return $apiTraits;
}

function hexToGameCode($hex){
  $return = '';
  foreach(explode(" ", $hex) as $pair){
    $return .= chr(hexdec(str_replace('0x', '', $pair)));
  }
  return base64_encode($return);
}

function getSkillTemplateCode($profession, $skills){
	$PROFESSIONS_TEMPLATE = [
		"guardian" 	   => "0x01",
		"warrior" 	   => "0x02",
		"engineer"	   => "0x03",
		"ranger"	   => "0x04",
		"thief"		   => "0x05",
		"elementalist" => "0x06",
		"mesmer"	   => "0x07",
		"necromancer"  => "0x08",
		"revenant"     => "0x09",
	];

	if ($profession === "revenant"){
		return "";
	}

	$db = json_decode(file_get_contents("./dbs/".$profession.".json"), true);
	$hexArray = [];

	$hexArray[0] = "0x73";
	$hexArray[1] = $PROFESSIONS_TEMPLATE[$profession];
	$hexArray[2] = "0x00";
	$index = 3;

	ksort($skills);

	foreach ($skills as $key => $skill) {
		if(!empty($db['skills'][$skill["name"]])){

			$hexArray[$index] 	= $db['skills'][$skill["name"]][0];
			$hexArray[$index+1] = $db['skills'][$skill["name"]][1];
		}else{
			$hexArray[$index] = 0x00;
			$hexArray[$index+1] = 0x00;
		}
		$index += 2;
	}
	/*
	while($index <= 22){
		$hexArray[$index] = 0x00;
		$index++;
	}
	*/
	$templateCode = hexToGameCode(implode(' ', $hexArray));

	$templateCode = '[*'.$templateCode.']';

	return $templateCode;
}

function hexArrayFromId($id){
	$hex = [];
	$number = $id;

	$hex[0] = floor($number/16)%16;
	$hex[1] = floor($number/1)%16;
	$hex[2] = floor((($number/16)/16)/16)%16;
	$hex[3] = floor(($number/16)/16)%16;

	$result = [sprintf("0x%01x%01x", $hex[0],$hex[1]), sprintf("0x%01x%01x", $hex[2],$hex[3])];
	return $result;
}

function getTraitTemplateCode($profession, $traits, $apiTraits){
	$PROFESSIONS_TEMPLATE = [
		"guardian" 	   => "0x01",
		"warrior" 	   => "0x02",
		"engineer"	   => "0x03",
		"ranger"	   => "0x04",
		"thief"		   => "0x05", 
		"elementalist" => "0x06",
		"mesmer"	   => "0x07",
		"necromancer"  => "0x08",
		"revenant"     => "0x09",
	];
	$traitlines = json_decode(file_get_contents('./dbs/trait_lines.json'), true)["traitsLines"];
	
	$hexArray = [];
	$traitFinalLine = [];

	foreach ($traits as $key => $trait) {

		if(array_search($traitlines[$profession][$trait["branch"]][0], array_column($traitFinalLine, 'name')) === false){
			if($traitlines[$profession][$trait["branch"]][1] === true)
			{
				$traitFinalLine[2] = ["name" => $traitlines[$profession][$trait["branch"]][0], "fakeBranch" => $trait["branch"]];
			}else {
				array_unshift($traitFinalLine, ["name" => $traitlines[$profession][$trait["branch"]][0], "fakeBranch" => $trait["branch"]]);
			}
		}
		
	}

	foreach ($traitFinalLine as $key => $value) {
		foreach ($traits as $trait) {
			if($trait["branch"] === $value["fakeBranch"]){
				$traitFinalLine[$key]["line_id"] = $apiTraits["line"][$value["name"]]["id"];
				$traitFinalLine[$key]["traits"][] = array_search($trait["name"], $apiTraits["line"][$value["name"]]["traits"]);
			}
		}
	}

	$hexArray[0] = "0x74";
	$hexArray[1] = $PROFESSIONS_TEMPLATE[$profession];
	$hexArray[2] = "0x00";
	for($i = 0; $i<=2; $i++){
		$hexArray[3+($i*2)] = sprintf("0x%02x", $traitFinalLine[$i]["line_id"]);
		$hexArray[4+($i*2)] = sprintf("0x%02x", 0);
		for($j = 0; $j<=2; $j++){
			$id = $traitFinalLine[$i]["traits"][$j];
			$hexTrait = hexArrayFromId( $traitFinalLine[$i]["traits"][$j]);
			$hexArray[9+($j*2)+($i*6)] = $hexTrait[0];
			$hexArray[10+($j*2)+($i*6)] = $hexTrait[1];
		}
	}

	ksort($hexArray);

	$templateCode = hexToGameCode(implode(' ', $hexArray));

	$templateCode = '[*'.$templateCode.']';

	return $templateCode;
}

//MAIN
try{
	if(isset($_POST["gw2SkillsUrl"])){
		$PROFESSIONS = [
			1 => "elementalist",
			2 => "warrior",
			3 => "ranger",
			4 => "necromancer",
			5 => "guardian",
			6 => "thief",
			7 => "engineer",
			8 => "mesmer",
			9 => "revenant",
		];

		$url = $_POST["gw2SkillsUrl"];
		
		$htmlContent = file_get_contents($url);
		$skills = [];
		$traits = [];
		
		if(!preg_match('|gw2skills.net/editor|', $url)){
			$errors[] = ["error", "This link is not form <a href='http://en.gw2skills.net/editor/'>gw2Skills.net</a>."];
		}
		elseif(preg_match('|preload\[\'p\'\] ?= ?(\d+)|', $htmlContent, $matches)){
			$profession = $matches[1];
			$traitlines = json_decode(file_get_contents('./dbs/trait_lines.json'));
			$db = getDB($PROFESSIONS[$profession]);

			if(preg_match_all('|preload\[\'s\'\]\[(\d+)\] ?= ?([^;]*)|', $htmlContent, $matches)){
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
			
			$apiSkills = getApiSkills($PROFESSIONS[$profession]);
			foreach ($skills as $key => $skill) {
				$skills[$key]["OfficialId"] = $apiSkills[$skill["name"]];
			}
			$apiTraits = getApiTraits($PROFESSIONS[$profession]);

			if($PROFESSIONS[$profession] === "revenant"){
				$skillCode = "";
				$errors[] =  ["warning", "Skills for Revenant don't work yet."];
			}elseif(empty($skills)){
				$skillCode = "";
				$errors[] =  ["warning", "There are not skills in this link."];
			}
			else{
				$skillCode = getSkillTemplateCode($PROFESSIONS[$profession], $skills);
			}

			if(empty($traits)){
				$traitCode = "";
				$errors[] =  ["warning", "There are not traits in this link."];
			}
			else{
				$traitCode = getTraitTemplateCode($PROFESSIONS[$profession], $traits, $apiTraits);
			}
			
		
		}else{
			$errors[] = ["error", "Looks like that this link doesn't has a profession. Please check again."];
		}
		
	}
}catch(Exception $e){
	$errors[] = ["error", "Internal Server Error"];
}

?>

<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/css/bootstrap.min.css" integrity="sha384-/Y6pD6FV/Vv2HJnA6t+vslU6fwYXjCFtcEpHbNJ0lyAFsXTsjBbfaDjzALeQsN6M" crossorigin="anonymous">
    
    <!--<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    -->
    <link href="./css/style.css" rel="stylesheet" id="bootstrap-css">
    <title>GW2 Template Generator</title>
  </head>
  <body id="body">
    <div class="container" style="margin-top: 8%;">
    <div class="row col-md-12">
		<div class="col-md-12">     
			<div class="row">
				<div id="logo" class="text-center">
					<h1>GW2 Template Generator</h1><p>From <a href="http://en.gw2skills.net/editor/">Gw2Skills.net</a> to GW2 <a href="https://www.deltaconnected.com/arcdps/">Arcdps build templates</a> add-on</p>
				</div>
				<div class="col-md-12">
					<form role="form" id="form-buscar" method="POST">
						<div class="form-group">
							<div class="input-group">
								<input id="1" class="form-control col-md-12" type="url" name="gw2SkillsUrl" placeholder="Write Gw2Skills.net URL..." required/>
								<span class="input-group-btn">
								<button class="btn btn-success" type="submit" style="cursor: pointer">
								<i class="glyphicon glyphicon-search" aria-hidden="true"></i> Generate
								</button>
								</span>
							</div>
						</div> 
					</form>
					<?php foreach ($errors as $key => $error) : ?>
						<div class="alert alert-<?php echo ($error[0] === "error") ? "danger" : $error[0]; ?> col-md-12">
							<strong><?php echo ucfirst($error[0]).": "; ?></strong><?php echo $error[1]; ?>
						</div>
					<?php endforeach ?>
				</div>
			</div>
		</div>
	</div>
	<div class="row col-md-12">
	<?php if (isset($traitCode)): ?>
		<div class="col-sm-12 col-md-6">
			<div class="card">
			  <div class="card-body">
			    <h4 class="card-title">Traits Template</h4>
			    <h6 class="card-subtitle mb-2 text-muted">Paste it in the first row.</h6>
			    <div>
				    <div class="input-group">
				      <input type="text" class="form-control" id="traits_text" value="<?php echo $traitCode; ?>" readonly>
				      <span class="input-group-btn">
				        <button class="btn btn-primary" type="button" id="traits_btn" style="cursor:pointer">Copy</button>
				      </span>
				    </div>
				</div>
			  </div>
			</div>
		</div>
		<script type="text/javascript">
			document.getElementById("traits_btn").addEventListener("click", function() {
	    		copyToClipboard(document.getElementById("traits_text"));
			});
		</script>
	<?php endif ?>
	<?php if (isset($skillCode)): ?>
		<div class="col-sm-12 col-md-6">
			<div class="card">
			  <div class="card-body">
			    <h4 class="card-title">Skills Template</h4>
			    <h6 class="card-subtitle mb-2 text-muted">Paste it in the second row.</h6>
			    <div>
				    <div class="input-group">
				      <input type="text" class="form-control" id="skills_text" value="<?php echo $skillCode; ?>" readonly>
				      <span class="input-group-btn">
				        <button class="btn btn-primary" type="button" id="skills_btn" style="cursor:pointer">Copy</button>
				      </span>
				    </div>
				</div>
			  </div>
			</div>
		</div>
		<script type="text/javascript">
			document.getElementById("skills_btn").addEventListener("click", function() {
    			copyToClipboard(document.getElementById("skills_text"));
			});
		</script>
		<?php endif ?>
		</div>             
	</div>
<div style="text-align: right;position: fixed;z-index:99999999;bottom: 0; width: 100%;line-height: 10; height:30px; background:white;">
</div>
	
    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js" integrity="sha384-b/U6ypiBEHpOf/4+1nzFpr53nxSS+GLCkfwBdFNTxtclqqenISfwAzpKaMNFNmj4" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0-beta/js/bootstrap.min.js" integrity="sha384-h0AbiXch4ZDo7tp9hKZ4TsHbi047NrKGLO3SEJAg45jXxnGIfYzk4Si90RDIqNm1" crossorigin="anonymous"></script>

	<script type="text/javascript">
    	function copyToClipboard(elem) {
	  // create hidden text element, if it doesn't already exist
	    var targetId = "_hiddenCopyText_";
	    var isInput = elem.tagName === "INPUT" || elem.tagName === "TEXTAREA";
	    var origSelectionStart, origSelectionEnd;
	    if (isInput) {
	        // can just use the original source element for the selection and copy
	        target = elem;
	        origSelectionStart = elem.selectionStart;
	        origSelectionEnd = elem.selectionEnd;
	    } else {
	        // must use a temporary form element for the selection and copy
	        target = document.getElementById(targetId);
	        if (!target) {
	            var target = document.createElement("textarea");
	            target.style.position = "absolute";
	            target.style.left = "-9999px";
	            target.style.top = "0";
	            target.id = targetId;
	            document.body.appendChild(target);
	        }
	        target.textContent = elem.textContent;
	    }
	    // select the content
	    var currentFocus = document.activeElement;
	    target.focus();
	    target.setSelectionRange(0, target.value.length);
	    
	    // copy the selection
	    var succeed;
	    try {
	    	  succeed = document.execCommand("copy");
	    } catch(e) {
	        succeed = false;
	    }
	    // restore original focus
	    if (currentFocus && typeof currentFocus.focus === "function") {
	        currentFocus.focus();
	    }
	    
	    if (isInput) {
	        // restore prior selection
	        elem.setSelectionRange(origSelectionStart, origSelectionEnd);
	    } else {
	        // clear temporary content
	        target.textContent = "";
	    }
	    return succeed;
	}
  	</script>
  </body>
  
</html>
