<?php

$errors = [];

function getDB($profession){
	$db = json_decode(file_get_contents('./dbs/db_'.$profession.'.json'), true);

	return $db;
}

function getProfessions(){
	$professions = json_decode(file_get_contents('./dbs/inGame/professions.json'), true)["professions"];

	return $professions;
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

function hexToGameCode($hex){
  $return = '';
  foreach(explode(" ", $hex) as $pair){
    $return .= chr(hexdec(str_replace('0x', '', $pair)));
  }
  return base64_encode($return);
}

function getSkillTemplateCode($info, $db, &$errors = []){
	$hexArray = [];

	if ($info["profession"] === "revenant"){
		$errors[] = ["warning", "Revenant doesn't have utility skills and the legends don't work yet."];
		return "";
	}
	if(empty($info["skills"])){
		$errors[] = ["warning", "This link doesn't have skills."];
		return "";
	}

	$hexArray[0] = "0x73";
	$hexArray[1] = $db["profession"]["InternalID"];
	$hexArray[2] = "0x00";
	$index = 3;

	foreach ($info["skills"] as $key => $skill) {
		if(!empty($db['skills'][$skill] && isset($db['skills'][$skill]["InternalID"]))){
			$hexArray[$index] 	= $db['skills'][$skill]["InternalID"][0];
			$hexArray[$index+1] = $db['skills'][$skill]["InternalID"][1];
		}elseif (!empty($db['skills'][$skill] && !isset($db['skills'][$skill]["InternalID"]))) {
			$errors[] = ["error", "The skill \"".$skill."\" is not mapped to game code yet. We will try to add it as soon as posible."];
			$hexArray[$index] 	= 0x00;
			$hexArray[$index+1] = 0x00;
		}
		else{
			$hexArray[$index] 	= 0x00;
			$hexArray[$index+1] = 0x00;
		}
		$index += 2;
	}

	$templateCode = hexToGameCode(implode(' ', $hexArray));

	$templateCode = '[*'.$templateCode.']';

	return $templateCode;
}

function getTraitTemplateCode($info, $db, &$errors = []){
	$traitFinalLine = [];

	if(empty($info["traits"])){
		$erros[] = ["warning", "This link doesn't have traits."];
		return "";
	}

	foreach ($info["traitLines"] as $key => $traitLine) {
		if(isset($db["traitsLines"][$traitLine])){
			$traits = [];

			foreach ($info["traits"] as $traitName => $trait) {
				if(isset($db["traitsLines"][$traitLine]["traits"][$trait])){
					$traits[] = hexArrayFromId($db["traitsLines"][$traitLine]["traits"][$trait]["Gw2OfficialId"]);
				}
			}

			if($db["traitsLines"][$traitLine]["isElite"]){
				$traitFinalLine[2] = ["line_id" => $db["traitsLines"][$traitLine]["Gw2OfficialId"], "traits" => $traits];
			}else{
				array_unshift($traitFinalLine, ["line_id" => $db["traitsLines"][$traitLine]["Gw2OfficialId"], "traits" => $traits]);
			} 

		}
	}

	$hexArray = [];
	$hexArray[0] = "0x74";
	$hexArray[1] =  $db["profession"]["InternalID"];
	$hexArray[2] = "0x00";
	for($i = 0; $i<=2; $i++){
		$hexArray[3+($i*2)] = sprintf("0x%02x", $traitFinalLine[$i]["line_id"]);
		$hexArray[4+($i*2)] = sprintf("0x%02x", 0);
		for($j = 0; $j<=2; $j++){
			$id = $traitFinalLine[$i]["traits"][$j];
			$hexArray[9+($j*2)+($i*6)] = $traitFinalLine[$i]["traits"][$j][0];
			$hexArray[10+($j*2)+($i*6)] = $traitFinalLine[$i]["traits"][$j][1];
		}
	}

	ksort($hexArray);

	$templateCode = hexToGameCode(implode(' ', $hexArray));

	$templateCode = '[*'.$templateCode.']';

	return $templateCode;
}

function gw2SkillsNetSkills($db, $htmlContent){
	$skills = [];

	if(preg_match_all('|preload\[\'s\'\]\[(\d+)\] ?= ?([^;]*)|', $htmlContent, $matches)){
		foreach ($matches[1] as $key => $match) {
			if(isset($db["skills"][$matches[2][$key]])){
				$skills[$match] = $db["skills"][$matches[2][$key]];
			}
		}
	}
	ksort($skills);

	return $skills;
}

function gw2SkillsNetTraits($db, $htmlContent){
	$result = [];

	if(preg_match_all('|preload\[\'t\'\]\[(\d+)\] ?= ?\[ ?\d+,([^;]*)\]|', $htmlContent, $matches)){
		foreach ($matches[1] as $key => $match) {
			foreach(explode(",",trim($matches[2][$key])) as $trait){
				if(isset($db["traits"][$trait])){
					$result["traits"][] = $db["traits"][$trait];
				}
			}
			if(isset($db["traitLines"][$match])){
				$result["traitLines"][] = $db["traitLines"][$match];
			}
		}
	}

	return $result;
}

function gw2SkillsNetParser($url, $professions, &$errors){
	$htmlContent = file_get_contents($url);
	$gw2SkillsNetDb = json_decode(file_get_contents("./dbs/gw2SkillsNet/database.json"), true);
	$result = [];
	if(preg_match('|preload\[\'p\'\] ?= ?(\d+)|', $htmlContent, $matches)){
		if(isset($gw2SkillsNetDb["professions"][$matches[1]])){
			
			$result["profession"] = $gw2SkillsNetDb["professions"][$matches[1]];

			$result["skills"] = gw2SkillsNetSkills($gw2SkillsNetDb, $htmlContent);

			$result = array_merge($result, gw2SkillsNetTraits($gw2SkillsNetDb, $htmlContent));
			
		}else{
			$errors[] = ["error", "Looks like that this link has a not implemented profession. We will try to add it ASAP."];
		}

	}else{
		$errors[] = ["error", "Looks like that this link doesn't have a profession. Please check again."];
	}

	return $result;
}

//MAIN
try{
	echo "<pre>";

	if(isset($_POST["gw2SkillsUrl"])){
		$url = $_POST["gw2SkillsUrl"];
		
		if(!preg_match('|gw2skills.net/editor|', $url)){
			$errors[] = ["error", "This link is not from <a href='http://en.gw2skills.net/editor/' rel=\"noopener\">gw2Skills.net</a>."];
		}
		else{
			$professions = getProfessions();
			$result = gw2SkillsNetParser($url, $professions, $errors);

			if(isset($result["profession"]) && (isset($result["skills"]) || isset(result["traits"]))){
				$db = json_decode(file_get_contents("./dbs/db_".$result["profession"].".json"), true);

				$skillCode = getSkillTemplateCode($result, $db, $errors);

				$traitCode = getTraitTemplateCode($result, $db, $errors);
			}
		}	
			
		
	}


	echo "</pre>";
}catch(Exception $e){
	$errors[] = ["error", "Internal Server Error."];
} 
?>

<!DOCTYPE html>
<html lang="en" style="height: 100%;">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    
    <link rel="stylesheet" href="./bootstrap/css/bootstrap.min.css">
    
    <!--<link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.0/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    -->
    <link href="./css/style.css" rel="stylesheet" id="bootstrap-css">
    <title>GW2 Template Generator</title>
  </head>
  <body id="body" style="height: 100%;">
    <div class="container" style="margin-top: 8%;">
    <div class="row col-md-12">
		<div class="col-md-12">     
			<div class="row">
				<div id="logo" class="text-center">
					<h1>GW2 Template Generator</h1><p>From <a href="http://en.gw2skills.net/editor/"  rel="noopener">Gw2Skills.net</a> to GW2 <a href="https://www.deltaconnected.com/arcdps/" rel="noopener">Arcdps build templates</a> add-on</p>
				</div>
				<div class="col-md-12">
					<form role="form" id="form-buscar" method="POST">
						<div class="form-group">
							<div class="input-group">
								<input id="1" class="form-control col-md-12" type="url" name="gw2SkillsUrl" placeholder="Paste Gw2Skills.net URL..." required/>
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
	
    <script src="./bootstrap/js/jquery-3.2.1.slim.min.js"></script>
    <script src="./bootstrap/js/popper.min.js"></script>
    <script src="./bootstrap/js/bootstrap.min.js"></script>

	<script type="text/javascript" src="./js/copyToClipboard.js"></script>
  </body>
  
</html>
