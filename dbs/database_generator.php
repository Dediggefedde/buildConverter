<?php
//returns file content of the target. 
function getContent($url){
	//curl fallback for servers with limits for file_get_contents
	$data=file_get_contents($url);
	if($data==""){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$data = curl_exec($ch);
		curl_close($ch);
	}
	return $data;
}

//TODO: error output if links are outdated. 
//also check internal generated file structure and date.
//perhaps this can be called via cronjob...

function getDB($profession){
    $PROFESSION_URL = "https://api.guildwars2.com/v2/professions?lang=en&ids=";
    $SKILL_URL = "https://api.guildwars2.com/v2/skills?lang=en&ids=";
    $TRAIT_URL = "https://api.guildwars2.com/v2/traits?lang=en&ids=";

    $db = [];
    $professionSkills = [];
    $apiSkills = [];

    $apiJson = json_decode(getContent($PROFESSION_URL.ucfirst($profession["name"])), true);
    $internalIdSkillsJson = json_decode(getContent("./inGame/".$profession["name"].".json"), true);

    //Get Skills from GW2 API for profession
    foreach ($apiJson[0]["skills"] as $skill) {
        $professionSkills[] = $skill["id"];
    }

    $apiSkillsJson = json_decode(getContent($SKILL_URL.implode(',', $professionSkills)), true);

    //Get TraitLines and Traits from GW2 API for profession
    foreach ($apiJson[0]["training"] as $key => $training) {
        if(in_array($training["category"], ["Specializations", "EliteSpecializations"])){
            $traitLinesName[$training["id"]]["isElite"] = ($training["category"] === "EliteSpecializations");
            foreach ($training["track"] as $keyTrait => $value) {
                if($value["type"] === "Trait"){
                    $traitsIdsToSearch[$value["trait_id"]]["name"] = $training["name"];
                    $traitsIdsToSearch[$value["trait_id"]]["isElite"] = ($training["category"] === "EliteSpecializations");
                }
            }
        }
    }

    $apiTraitsJson = json_decode(getContent($TRAIT_URL.implode(',', array_keys($traitsIdsToSearch))), true);

    //Fill profesion
    $db["profession"] = $profession;

    //Fill array with Gw2 API Skills
    foreach ($apiSkillsJson as $key => $skill) {
        $db["skills"][$skill["name"]]["GW2OfficialId"] = $skill["id"];
    }

    //Get Skills from intenal Game ID (manual work)
    foreach ($internalIdSkillsJson["skills"] as $key => $value) {
        //Revenant doesn't have codes yet
        if($profession["name"] !== "revenant"){
            $db["skills"][$key]["InternalID"] = $value;
        }
        
    }

    //Fill array with traitlines and traits from GW2 API (is the same than the Game ID)
    foreach ($apiTraitsJson as $key => $trait) {
        $db["traitsLines"][$traitsIdsToSearch[$trait["id"]]["name"]]["Gw2OfficialId"] = $trait["specialization"];
        $db["traitsLines"][$traitsIdsToSearch[$trait["id"]]["name"]]["isElite"] = $traitsIdsToSearch[$trait["id"]]["isElite"];
        $db["traitsLines"][$traitsIdsToSearch[$trait["id"]]["name"]]["traits"][$trait["name"]]["Gw2OfficialId"] = $trait["id"];
    }


    return $db;
}

function getDbGw2SkillsNet(){
    $GW2SKILLS_RAW_URL_PROFESSION = "http://en.gw2skills.net/ajax/editorSkillListFull/?p=";
    $GW2SKILLS_RAW_URL_TRAITS_LINES = "http://js.gw2skills.net/db/en.1548027013.js";
    $db = [];
    $professions = json_decode(getContent("./gw2SkillsNet/professions.json"), true);

    foreach ($professions["professions"] as $professionId => $professionName) {
        $content = getContent($GW2SKILLS_RAW_URL_PROFESSION.$professionName);

         //Get Skills id from Gw2Skills
        if(preg_match_all('|(\d+);[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;[^;]*;([^;]*);[^\n]*\n|',
         $content, $matches)){
            foreach ($matches[1] as $key => $match) {
                $db["skills"][$match] = $matches[2][$key];
            }
        }

        $traitsLinesInfo = getContent($GW2SKILLS_RAW_URL_TRAITS_LINES);
        // Fill array with trait lines from Gw2 Skills
        if(preg_match_all('|TLinesDB\[\'([^\']*)\'\]\[\d+\] = \[(\d+),"([^"]*)|', $traitsLinesInfo, $matches)){
            foreach ($matches[1] as $key => $value) {
                if($value === $professionName){
                    $db["traitLines"][$matches[2][$key]] = $matches[3][$key];
                }
            }
        }

        //Fill array with tratis from GW2 Skills
        if(preg_match_all('|(\d+)\|(\d+)\|([XM]\d)\|[^\|]*\|([^\|]*)\|[^\n]*\n|', $content, $matches)){
            foreach ($matches[1] as $key => $match) {
                 $db["traits"][$match] = $matches[4][$key];
            }
        }
    }
      
	
    $db = array_merge($db, $professions);

    return $db;
}

//Output
echo "<pre>";
$professions = json_decode(getContent("./inGame/professions.json"), true);
$gwSkillsNetDb = getDbGw2SkillsNet();
print_r($gwSkillsNetDb);
file_put_contents("./gw2SkillsNet/database.json", json_encode($gwSkillsNetDb));

foreach ($professions["professions"] as $profession) {
    $db = getDB($profession);
    echo "=========================================<br><br>";
    echo $profession["name"]."<br>";
    print_r($db);
    file_put_contents("./db_".$profession["name"].".json", json_encode($db));
}

echo "</pre>";